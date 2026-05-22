<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\Vendedor;
use App\Models\Venta;
use App\Services\CobranzaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class VentaImportController extends Controller
{
    private array $tiposDoc = [
        'B'  => 'boleta',
        'F'  => 'factura',
        'P'  => 'proforma',
        'PR' => 'proforma',
        'NC' => 'nota_credito',
    ];

    public function form()
    {
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        if ($vendedores->isEmpty()) {
            return redirect('/casadets/vendedores/create')
                ->with('error', 'Primero registra al menos un vendedor.');
        }

        $vendedorDefault = $vendedores->first(fn ($v) => stripos($v->nombre, 'jovi') !== false)
            ?? $vendedores->first();

        return view('casadets.ventas.import', compact('vendedores', 'vendedorDefault'));
    }

    public function preview(Request $request)
    {
        ini_set('memory_limit', '256M');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:102400',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('archivo')->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Exception $e) {
            return back()->with('error', 'No se pudo leer el archivo: ' . $e->getMessage());
        }

        if (count($rows) < 2) {
            return back()->with('error', 'El archivo está vacío o no tiene datos.');
        }

        $headersOriginales = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $headers = array_map(fn ($h) => $this->normalizarTexto($h), $headersOriginales);
        $mapa = $this->mapearColumnas($headers);

        \Log::info('IMPORT: headers originales del Excel', $headersOriginales);
        \Log::info('IMPORT: mapa de columnas detectado', array_map(
            fn ($idx) => $idx !== null ? ($headersOriginales[$idx] ?? "idx=$idx") : 'NO DETECTADO',
            $mapa
        ));

        $camposObligatorios = ['fecha', 'doc', 'serie', 'nro', 'producto', 'precio', 'cantidad', 'total', 'razon_social', 'ruc'];
        $faltantes = array_keys(array_filter(
            array_intersect_key($mapa, array_flip($camposObligatorios)),
            fn ($v) => $v === null
        ));
        if (!empty($faltantes)) {
            return back()->with('error', 'Faltan columnas en el Excel: ' . implode(', ', $faltantes));
        }

        $grupos = [];
        $sinDocContador = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $doc    = trim((string) ($r[$mapa['doc']] ?? ''));
            $serie  = trim((string) ($r[$mapa['serie']] ?? ''));
            $numero = trim((string) ($r[$mapa['nro']] ?? ''));
            $producto = trim((string) ($r[$mapa['producto']] ?? ''));

            if ($producto === '') continue;

            if ($doc === '' && $serie === '' && $numero === '') {
                $key = '__sinDoc__' . $sinDocContador++;
            } else {
                $key = $doc . '|' . $serie . '|' . $numero;
            }

            if (!isset($grupos[$key])) {
                $razonSocial = trim((string) ($mapa['razon_social'] !== null ? ($r[$mapa['razon_social']] ?? '') : ''));
                $ruc         = trim((string) ($mapa['ruc'] !== null ? ($r[$mapa['ruc']] ?? '') : ''));
                $canjeRaw    = trim((string) ($mapa['canje'] !== null ? ($r[$mapa['canje']] ?? '') : ''));
                $grupos[$key] = [
                    'fecha'        => $this->parseFecha($r[$mapa['fecha']] ?? null),
                    'doc'          => $doc,
                    'serie'        => $serie,
                    'numero'       => $numero,
                    'razon_social' => $razonSocial,
                    'ruc'          => $ruc,
                    'canje_raw'    => $canjeRaw,
                    'canjeada'     => false,
                    'canjes'       => [],
                    'detalles'     => [],
                    'total'        => 0,
                ];
            }

            $cantidad = $this->parseNumero($r[$mapa['cantidad']] ?? 0);
            $precio   = $this->parseNumero($r[$mapa['precio']] ?? 0);
            $total    = $this->parseNumero($r[$mapa['total']] ?? ($cantidad * $precio));
            $codigo   = trim((string) ($mapa['codigo'] !== null ? ($r[$mapa['codigo']] ?? '') : ''));

            $grupos[$key]['detalles'][] = [
                'producto'        => $producto,
                'codigo'          => $codigo,
                'cantidad'        => $cantidad,
                'precio_unitario' => $precio,
                'subtotal'        => $total,
            ];
            $grupos[$key]['total'] += $total;
        }

        if (empty($grupos)) {
            return back()->with('error', 'No se encontraron filas válidas en el archivo.');
        }

        // Resolver canjes: marcar proformas que ya fueron canjeadas
        $grupos = $this->resolverCanjes($grupos);

        $grupos = array_values($grupos);

        usort($grupos, function ($a, $b) {
            $serie = strcmp($a['serie'], $b['serie']);
            if ($serie !== 0) return $serie;
            return intval($a['numero']) - intval($b['numero']);
        });

        $grupos = array_values($grupos);
        [$gruposNuevos, $omitidos] = $this->filtrarDuplicados($grupos);

        if (empty($gruposNuevos)) {
            return back()->with('error', 'Todos los documentos del Excel ya existen en el sistema. No hay nada nuevo para importar.');
        }

        $importId  = uniqid('import_', true);
        $rutaTemp  = storage_path("app/imports/{$importId}.json");
        if (!is_dir(storage_path('app/imports'))) {
            mkdir(storage_path('app/imports'), 0755, true);
        }
        file_put_contents($rutaTemp, json_encode($gruposNuevos));
        session(['import_id' => $importId]);

        $nombresLegibles = [
            'fecha'        => 'Fecha',
            'doc'          => 'Tipo doc.',
            'serie'        => 'Serie',
            'nro'          => 'Nro. documento',
            'producto'     => 'Producto',
            'precio'       => 'Precio unitario',
            'cantidad'     => 'Cantidad',
            'total'        => 'Total',
            'razon_social' => 'Razón social',
            'ruc'          => 'RUC',
            'codigo'       => 'Código producto',
            'canje'        => 'Canje',
        ];
        $columnasInfo = [];
        foreach ($mapa as $campo => $idx) {
            $columnasInfo[$campo] = [
                'label'       => $nombresLegibles[$campo] ?? $campo,
                'detectada'   => $idx !== null,
                'header_real' => $idx !== null ? ($headersOriginales[$idx] ?? '?') : null,
            ];
        }

        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        if ($vendedores->isEmpty()) {
            return redirect('/casadets/vendedores/create')
                ->with('error', 'No hay vendedores activos. Registra uno antes de importar.');
        }

        $vendedorDefault = $vendedores->first(fn ($v) => stripos($v->nombre, 'jovi') !== false)
            ?? $vendedores->first();

        return view('casadets.ventas.import_preview', [
            'grupos'               => $gruposNuevos,
            'vendedores'           => $vendedores,
            'vendedor_id_default'  => $vendedorDefault->id,
            'metodo_pago_default'  => 'ninguno',
            'duplicadosExistentes' => [],
            'omitidos'             => $omitidos,
            'columnasInfo'         => $columnasInfo,
        ]);
    }

    public function confirm(Request $request)
    {
        $importId  = session('import_id');
        $rutaTemp  = $importId ? storage_path("app/imports/{$importId}.json") : null;
        if (!$rutaTemp || !file_exists($rutaTemp)) {
            return redirect('/casadets/ventas/import')
                ->with('error', 'La sesión expiró o el archivo temporal fue eliminado. Vuelve a subir el archivo.');
        }
        $sessionGrupos = json_decode(file_get_contents($rutaTemp), true) ?? [];

        $ventasJson = $request->input('ventas_json', '');
        $submitted  = json_decode($ventasJson, true);

        if (empty($submitted) || !is_array($submitted)) {
            return back()->with('error', 'No se recibieron datos del formulario. Intenta de nuevo.');
        }

        $metodosValidos = ['ninguno', 'efectivo', 'tarjeta', 'yape', 'plin', 'transferencia'];

        $grupos = [];
        foreach ($submitted as $item) {
            $idx  = (int) ($item['session_idx'] ?? -1);
            $base = $sessionGrupos[$idx] ?? null;
            if (!$base) continue;

            $detalles = $item['detalles'] ?? [];
            if (empty($detalles)) continue;

            $vendedorId = (int) ($item['vendedor_id'] ?? 0);
            if (!$vendedorId) continue;

            $pagos = array_filter($item['pagos'] ?? [], fn ($p) =>
                in_array($p['metodo'] ?? '', $metodosValidos) && isset($p['monto'])
            );

            $grupos[] = array_merge($base, [
                'vendedor_id' => $vendedorId,
                'pagos'       => array_values($pagos),
                'detalles'    => $detalles,
            ]);
        }

        if (empty($grupos)) {
            return back()->with('error', 'No hay ventas válidas para importar.');
        }

        $errores = $this->validarFacturasUnicas($grupos);
        if (!empty($errores)) {
            return back()->with('error', 'No se importó nada. ' . implode(' ', $errores));
        }

        $totalCreadas       = 0;
        $totalDetalles      = 0;
        $totalCanjeadas     = 0;
        $totalNotasCredito  = 0;

        DB::transaction(function () use ($grupos, &$totalCreadas, &$totalDetalles, &$totalCanjeadas, &$totalNotasCredito) {
            $cobranza = app(CobranzaService::class);
            $todosProductosAfectados = [];

            foreach ($grupos as $g) {
                $tipoLetra = strtoupper(trim($g['doc'] ?? ''));
                if ($tipoLetra === 'PROFORMA') $tipoLetra = 'P';

                $tipoDoc   = $this->tiposDoc[$tipoLetra] ?? null;
                $esNC      = $tipoDoc === 'nota_credito';
                $esCanjeada = (bool) ($g['canjeada'] ?? false);

                $detallesCalc = array_map(function ($d) {
                    return [
                        'producto'        => $d['producto'],
                        'codigo'          => $d['codigo'] ?? null,
                        'cantidad'        => (float) $d['cantidad'],
                        'precio_unitario' => (float) $d['precio_unitario'],
                        'subtotal'        => round((float) $d['cantidad'] * (float) $d['precio_unitario'], 2),
                    ];
                }, $g['detalles']);

                $totalReal = (float) collect($detallesCalc)->reduce(
                    fn ($carry, $d) => bcadd($carry, (string) $d['subtotal'], 2),
                    '0'
                );

                // Notas de crédito se almacenan con total negativo
                if ($esNC) {
                    $totalReal = -abs($totalReal);
                }

                $numero = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');

                // Construir observaciones con info de canje
                $observaciones = 'Importado desde Excel';
                if ($esCanjeada) {
                    $observaciones .= ' — Proforma canjeada';
                    if (!empty($g['canjes'])) {
                        $observaciones .= ' por: ' . implode(', ', $g['canjes']);
                    }
                } elseif (!empty($g['canjes'])) {
                    $observaciones .= ' — Canjea proformas: ' . implode(', ', $g['canjes']);
                }
                if ($esNC) {
                    $observaciones .= ' — Nota de crédito';
                }

                // Buscar o crear cliente
                $clienteId   = null;
                $razonSocial = trim($g['razon_social'] ?? '');
                $ruc         = trim($g['ruc'] ?? '');
                if ($razonSocial !== '') {
                    $cliente = \App\Models\Cliente::whereRaw('LOWER(nombre) = ?', [strtolower($razonSocial)])->first();
                    if (!$cliente) {
                        $cliente = \App\Models\Cliente::create([
                            'nombre'    => $razonSocial,
                            'documento' => $ruc ?: null,
                            'activo'    => true,
                        ]);
                    } elseif ($ruc !== '' && empty($cliente->documento)) {
                        $cliente->update(['documento' => $ruc]);
                    }
                    $clienteId = $cliente->id;
                }

                // Estado inicial según tipo de documento
                if ($esCanjeada) {
                    $estadoInicial = 'canjeada';
                } elseif ($esNC) {
                    $estadoInicial = 'pagado'; // NC no genera deuda pendiente
                } else {
                    $estadoInicial = 'pendiente';
                }

                $venta = Venta::create([
                    'vendedor_id'      => $g['vendedor_id'],
                    'cliente_id'       => $clienteId,
                    'total'            => $totalReal,
                    'estado'           => $estadoInicial,
                    'documento_tipo'   => $tipoDoc,
                    'documento_numero' => $numero ?: null,
                    'observaciones'    => $observaciones,
                    'fecha'            => $g['fecha'],
                ]);

                // Crear detalles
                // Proformas canjeadas y NC usan 'entrada' (devolucion/anulacion), ventas normales 'salida'
                $tipoMovStock = ($esNC) ? 'entrada' : 'salida';
                // Proformas canjeadas no mueven stock (la factura resultante ya lo hace)
                $moverStock = !$esCanjeada;

                $productosDeEstaVenta = [];
                foreach ($detallesCalc as $d) {
                    $producto = Producto::firstOrCreate(
                        ['nombre' => trim($d['producto'])],
                        ['precio_venta' => $d['precio_unitario']]
                    );

                    if (!$esNC && $d['precio_unitario'] > (float) $producto->precio_venta) {
                        $producto->update(['precio_venta' => $d['precio_unitario']]);
                    }

                    $venta->detalles()->create([
                        'producto_id'     => $producto->id,
                        'producto'        => $d['producto'],
                        'codigo'          => $d['codigo'] ?: null,
                        'cantidad'        => $d['cantidad'],
                        'precio_unitario' => $d['precio_unitario'],
                        'subtotal'        => $d['subtotal'],
                    ]);

                    if ($moverStock) {
                        StockMovimiento::create([
                            'producto_id'     => $producto->id,
                            'tipo'            => $tipoMovStock,
                            'cantidad'        => $d['cantidad'],
                            'precio_unitario' => $d['precio_unitario'],
                            'referencia_tipo' => 'venta',
                            'referencia_id'   => $venta->id,
                            'fecha'           => $g['fecha'],
                        ]);
                        $productosDeEstaVenta[] = $producto->id;
                    }

                    $totalDetalles++;
                }

                foreach (array_unique($productosDeEstaVenta) as $pid) {
                    $todosProductosAfectados[$pid] = true;
                }

                // Registrar pagos solo para ventas normales (no canjeadas ni NC)
                if (!$esCanjeada && !$esNC) {
                    $pagosParaService = collect($g['pagos'])
                        ->filter(fn ($p) => ($p['metodo'] ?? 'ninguno') !== 'ninguno' && ($p['monto'] ?? 0) > 0)
                        ->map(fn ($p) => ['metodo' => $p['metodo'], 'monto' => (float) $p['monto']])
                        ->values()
                        ->toArray();

                    if (!empty($pagosParaService)) {
                        $cobranza->registrarPago($venta, $pagosParaService);
                    }
                }

                if ($esCanjeada) {
                    $totalCanjeadas++;
                } elseif ($esNC) {
                    $totalNotasCredito++;
                } else {
                    $totalCreadas++;
                }
            }

            foreach (array_keys($todosProductosAfectados) as $pid) {
                Producto::find($pid)?->recalcularStock();
            }
        });

        if ($rutaTemp && file_exists($rutaTemp)) {
            unlink($rutaTemp);
        }
        session()->forget('import_id');

        $partes = [];
        if ($totalCreadas > 0)      $partes[] = "$totalCreadas venta(s) con $totalDetalles producto(s)";
        if ($totalCanjeadas > 0)    $partes[] = "$totalCanjeadas proforma(s) canjeada(s)";
        if ($totalNotasCredito > 0) $partes[] = "$totalNotasCredito nota(s) de crédito";

        return redirect('/casadets/ventas')->with('success',
            'Importación completada: ' . implode(', ', $partes) . '.'
        );
    }

    /**
     * Detecta y marca las proformas canjeadas usando el campo Proforma_Canjeada.
     *
     * Lógica:
     * - Si una FACTURA/BOLETA tiene canje = "|PR-0006-65513|PR-0006-65543", esas proformas fueron
     *   reemplazadas por esta factura → marcar esas proformas como canjeadas.
     * - Si una PROFORMA tiene canje = "F-F006-43957", fue reemplazada por esa factura
     *   → marcarla como canjeada directamente.
     */
    private function resolverCanjes(array $grupos): array
    {
        // Paso 1: construir índice por tipo|serie|numero para búsqueda rápida
        $indice = [];
        foreach ($grupos as $key => $g) {
            $doc   = strtoupper(trim($g['doc'] ?? ''));
            $serie = trim($g['serie'] ?? '');
            $nro   = trim($g['numero'] ?? '');
            $indice[$doc . '|' . $serie . '|' . $nro] = $key;
        }

        foreach ($grupos as $key => $g) {
            $canjeRaw = trim($g['canje_raw'] ?? '');
            if ($canjeRaw === '') continue;

            $docLetra = strtoupper(trim($g['doc'] ?? ''));

            // Facturas/Boletas: el campo lista las proformas que canjean
            // Formato: "|PR-0006-65513|PR-0006-65543" o "PR-0006-65513"
            if (in_array($docLetra, ['F', 'B'])) {
                $refs = array_filter(explode('|', $canjeRaw));
                $canjesResueltos = [];
                foreach ($refs as $ref) {
                    $ref = trim($ref);
                    if ($ref === '') continue;
                    // Formato: PR-0006-65513 → doc=PR, serie=0006, nro=65513
                    $partes = explode('-', $ref, 3);
                    if (count($partes) >= 2) {
                        $refDoc   = strtoupper($partes[0]);
                        $refSerie = $partes[1] ?? '';
                        $refNro   = $partes[2] ?? '';
                        $refKey   = $refDoc . '|' . $refSerie . '|' . $refNro;
                        if (isset($indice[$refKey])) {
                            $grupos[$indice[$refKey]]['canjeada'] = true;
                            $grupos[$indice[$refKey]]['canjes'][] = $docLetra . '-' . $g['serie'] . '-' . $g['numero'];
                        }
                        $canjesResueltos[] = $ref;
                    }
                }
                if (!empty($canjesResueltos)) {
                    $grupos[$key]['canjes'] = $canjesResueltos;
                }
            }

            // Proformas: el campo indica la factura/boleta por la que fue reemplazada
            // Formato: "F-F006-43957"
            if (in_array($docLetra, ['PR', 'P'])) {
                $partes = explode('-', $canjeRaw, 3);
                if (count($partes) >= 2) {
                    $grupos[$key]['canjeada'] = true;
                    $grupos[$key]['canjes'][] = $canjeRaw;
                }
            }
        }

        return $grupos;
    }

    private function filtrarDuplicados(array $grupos): array
    {
        $tipoMap = [
            'B'  => 'boleta',
            'F'  => 'factura',
            'P'  => 'proforma',
            'PR' => 'proforma',
            'NC' => 'nota_credito',
        ];

        $buscar = [];
        foreach ($grupos as $g) {
            $docLetra = strtoupper(trim($g['doc'] ?? ''));
            $tipo = $tipoMap[$docLetra] ?? null;
            $num  = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');
            if ($tipo && $num !== '') {
                $buscar[$tipo][] = $num;
            }
        }

        $existentes = [];
        foreach ($buscar as $tipo => $numeros) {
            $encontrados = Venta::where('documento_tipo', $tipo)
                ->whereIn('documento_numero', array_unique($numeros))
                ->pluck('documento_numero')
                ->toArray();
            foreach ($encontrados as $n) {
                $existentes[$tipo . '|' . $n] = true;
            }
        }

        $nuevos   = [];
        $omitidos = [];
        foreach ($grupos as $g) {
            $docLetra = strtoupper(trim($g['doc'] ?? ''));
            $tipo = $tipoMap[$docLetra] ?? null;
            $num  = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');

            if ($tipo && $num !== '' && isset($existentes[$tipo . '|' . $num])) {
                $omitidos[] = $num ?: '(sin número)';
            } else {
                $nuevos[] = $g;
            }
        }

        return [$nuevos, $omitidos];
    }

    private function validarFacturasUnicas(array $ventas): array
    {
        $errores = [];
        $vistos  = [];

        foreach ($ventas as $g) {
            $docLetra = strtoupper(trim($g['doc'] ?? ''));
            $num = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');
            if ($num === '') continue;

            $key = $docLetra . '|' . $num;
            if (isset($vistos[$key])) {
                $errores[] = "El documento $num está duplicado en el archivo.";
            }
            $vistos[$key] = true;
        }

        return $errores;
    }

    private function mapearColumnas(array $headers): array
    {
        $alias = [
            'fecha'        => ['fecha_emisi', 'fecha_emision', 'fecha'],
            'doc'          => ['doc', 'tipo_doc', 'tipo'],
            'serie'        => ['serie'],
            'nro'          => ['nrodocumen', 'nro_documento', 'nrodocumento', 'numero'],
            'producto'     => ['producto', 'descripcion', 'detalle'],
            'precio'       => ['precio_unitario', 'preciounit', 'precio'],
            'cantidad'     => ['cantidad', 'unidades', 'cant'],
            'total'        => ['total', 'subtotal', 'importe'],
            'razon_social' => ['nombrerazonsocial', 'razon_social', 'razon social', 'denominacion',
                               'nombre_cliente', 'nombre cliente', 'razonsocial', 'cliente'],
            'ruc'          => ['ruc', 'ruc_cliente', 'nro_ruc', 'nroruc', 'documento'],
            'codigo'       => ['codigo', 'cod', 'codigo_producto', 'codigoproducto', 'sku', 'code',
                               'clave', 'referencia', 'ref', 'item', 'part', 'codbarr', 'codbien',
                               'codprod', 'id_producto', 'idproducto', 'numero_parte', 'nroparte'],
            'canje'        => ['proforma_canjeada', 'canje', 'canjeada', 'canje_proforma', 'canjeado'],
        ];

        $excluir = [
            'codigo' => ['sunat', 'aduanero', 'arancelario', 'catalogo'],
        ];

        $mapa = [];
        foreach ($alias as $campo => $posibles) {
            $mapa[$campo] = null;
            $palabrasExcluidas = $excluir[$campo] ?? [];

            foreach ($headers as $idx => $h) {
                $hNorm    = $this->normalizarTexto($h);
                $excluido = false;
                foreach ($palabrasExcluidas as $ex) {
                    if (str_contains($hNorm, $ex)) { $excluido = true; break; }
                }
                if ($excluido) continue;

                foreach ($posibles as $p) {
                    if ($hNorm === $p || str_starts_with($hNorm, $p)) {
                        $mapa[$campo] = $idx;
                        break 2;
                    }
                }
            }
        }
        return $mapa;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $desde = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù'];
        $hacia  = ['a','e','i','o','u','u','n','a','e','i','o','u'];
        return str_replace($desde, $hacia, $texto);
    }

    private function parseFecha($valor): string
    {
        if (empty($valor)) return Carbon::today()->toDateString();

        if (is_numeric($valor)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valor))->toDateString();
            } catch (\Exception $e) {}
        }

        $valor    = trim((string) $valor);
        $formatos = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'];
        foreach ($formatos as $f) {
            try {
                return Carbon::createFromFormat($f, $valor)->toDateString();
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (\Exception $e) {
            return Carbon::today()->toDateString();
        }
    }

    private function parseNumero($valor): float
    {
        if (is_numeric($valor)) return (float) $valor;
        $limpio = preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', (string) $valor));
        return (float) ($limpio ?: 0);
    }
}
