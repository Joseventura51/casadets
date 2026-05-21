<?php

namespace App\Http\Controllers;

use App\Models\Vendedor;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class VentaImportController extends Controller
{
    private array $tiposDoc = ['B' => 'boleta', 'F' => 'factura', 'P' => 'proforma', 'PR' => 'proforma'];

    public function form()
    {
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        if ($vendedores->isEmpty()) {
            return redirect('/casadets/vendedores/create')
                ->with('error', 'Primero registra al menos un vendedor.');
        }

        $vendedorDefault = $vendedores->first(fn($v) => stripos($v->nombre, 'jovi') !== false)
            ?? $vendedores->first();

        return view('casadets.ventas.import', compact('vendedores', 'vendedorDefault'));
    }

    public function preview(Request $request)
    {
        ini_set('memory_limit', '256M');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:20480',
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

        $headers = array_map(fn($h) => $this->normalizarTexto((string) $h), $rows[0]);
        $mapa = $this->mapearColumnas($headers);

        $camposObligatorios = ['fecha', 'doc', 'serie', 'nro', 'producto', 'precio', 'cantidad', 'total'];
        $faltantes = array_keys(array_filter(
            array_intersect_key($mapa, array_flip($camposObligatorios)),
            fn($v) => $v === null
        ));
        if (!empty($faltantes)) {
            return back()->with('error', 'Faltan columnas en el Excel: ' . implode(', ', $faltantes));
        }

        $grupos = [];
        $sinDocContador = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $doc = trim((string) ($r[$mapa['doc']] ?? ''));
            $serie = trim((string) ($r[$mapa['serie']] ?? ''));
            $numero = trim((string) ($r[$mapa['nro']] ?? ''));
            $producto = trim((string) ($r[$mapa['producto']] ?? ''));

            if ($producto === '') {
                continue;
            }

            // Si no hay ningún campo de documento, cada fila es su propio grupo
            if ($doc === '' && $serie === '' && $numero === '') {
                $key = '__sinDoc__' . $sinDocContador++;
            } else {
                $key = $doc . '|' . $serie . '|' . $numero;
            }

            if (!isset($grupos[$key])) {
                $razonSocial = trim((string) ($mapa['razon_social'] !== null ? ($r[$mapa['razon_social']] ?? '') : ''));
                $ruc = trim((string) ($mapa['ruc'] !== null ? ($r[$mapa['ruc']] ?? '') : ''));
                $grupos[$key] = [
                    'fecha'        => $this->parseFecha($r[$mapa['fecha']] ?? null),
                    'doc'          => $doc,
                    'serie'        => $serie,
                    'numero'       => $numero,
                    'razon_social' => $razonSocial,
                    'ruc'          => $ruc,
                    'detalles'     => [],
                    'total'        => 0,
                ];
            }

            $cantidad = $this->parseNumero($r[$mapa['cantidad']] ?? 0);
            $precio = $this->parseNumero($r[$mapa['precio']] ?? 0);
            $total = $this->parseNumero($r[$mapa['total']] ?? ($cantidad * $precio));

            $grupos[$key]['detalles'][] = [
                'producto' => $producto,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $total,
            ];
            $grupos[$key]['total'] += $total;
        }

        if (empty($grupos)) {
            return back()->with('error', 'No se encontraron filas válidas en el archivo.');
        }

        $grupos = array_values($grupos);

        usort($grupos, function ($a,$b){

            $serie = strcmp($a['serie'], $b['serie']);

            if ($serie !== 0){
                return $serie;
            }
            return intval($a['numero']) - intval($b['numero']);
        });

        // Detectar documentos que YA existen en BD y auto-excluirlos
        $grupos = array_values($grupos);
        [$gruposNuevos, $omitidos] = $this->filtrarDuplicados($grupos);

        if (empty($gruposNuevos)) {
            return back()->with('error', 'Todos los documentos del Excel ya existen en el sistema. No hay nada nuevo para importar.');
        }

        // Guardar grupos en sesión para no reenviarlos como inputs
        session(['import_grupos' => $gruposNuevos]);

        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        if ($vendedores->isEmpty()) {
            return redirect('/casadets/vendedores/create')
                ->with('error', 'No hay vendedores activos. Registra uno antes de importar.');
        }

        $vendedorDefault = $vendedores->first(fn($v) => stripos($v->nombre, 'jovi') !== false)
            ?? $vendedores->first();

        return view('casadets.ventas.import_preview', [
            'grupos'             => $gruposNuevos,
            'vendedores'         => $vendedores,
            'vendedor_id_default'=> $vendedorDefault->id,
            'metodo_pago_default'=> 'ninguno',
            'duplicadosExistentes' => [],
            'omitidos'           => $omitidos,
        ]);
    }

    public function confirm(Request $request)
    {
        // Los datos fijos (fecha, doc, serie, numero, razon_social, ruc, detalles)
        // vienen de la sesión para no exceder max_input_vars con formularios grandes.
        $sessionGrupos = session('import_grupos', []);
        if (empty($sessionGrupos)) {
            return redirect('/casadets/ventas/import')
                ->with('error', 'La sesión expiró. Vuelve a subir el archivo.');
        }

        $data = $request->validate([
            'ventas'                      => 'required|array|min:1',
            'ventas.*.session_idx'        => 'required|integer',
            'ventas.*.vendedor_id'        => 'required|exists:vendedores,id',
            'ventas.*.total_cobrado'      => 'required|numeric|min:0',
            'ventas.*.pagos'              => 'required|array|min:1',
            'ventas.*.pagos.*.metodo'     => 'required|in:ninguno,efectivo,tarjeta,yape,plin,transferencia',
            'ventas.*.pagos.*.monto'      => 'required|numeric|min:0',
            'ventas.*.detalles_json'      => 'required|string',
        ]);

        // Reconstruir grupos completos mezclando sesión + inputs del form
        $grupos = [];
        foreach ($data['ventas'] as $submitted) {
            $idx = (int) $submitted['session_idx'];
            $base = $sessionGrupos[$idx] ?? null;
            if (!$base) continue;

            $detalles = json_decode($submitted['detalles_json'], true) ?? [];
            if (empty($detalles)) continue;

            $grupos[] = array_merge($base, [
                'vendedor_id'   => $submitted['vendedor_id'],
                'total_cobrado' => $submitted['total_cobrado'],
                'pagos'         => $submitted['pagos'],
                'detalles'      => $detalles,
            ]);
        }

        if (empty($grupos)) {
            return back()->with('error', 'No hay ventas válidas para importar.');
        }

        // Validar unicidad de documentos
        $errores = $this->validarFacturasUnicas($grupos);
        if (!empty($errores)) {
            return back()->with('error', 'No se importó nada. ' . implode(' ', $errores));
        }

        $totalCreadas = 0;
        $totalDetalles = 0;

        DB::transaction(function () use ($grupos, &$totalCreadas, &$totalDetalles) {
            foreach ($grupos as $g) {
                $detallesCalc = array_map(function ($d) {
                    $sub = round((float) $d['cantidad'] * (float) $d['precio_unitario'], 2);
                    return [
                        'producto'        => $d['producto'],
                        'cantidad'        => $d['cantidad'],
                        'precio_unitario' => $d['precio_unitario'],
                        'subtotal'        => $sub,
                    ];
                }, $g['detalles']);

                $totalReal  = round(array_sum(array_column($detallesCalc, 'subtotal')), 2);
                $tipoLetra  = strtoupper(trim($g['doc'] ?? ''));
                if ($tipoLetra === 'PROFORMA') $tipoLetra = 'P';
                // Número de documento: solo serie-número (ej: "B001-00001234")
                $numero = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');

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

                $pagosReales  = collect($g['pagos'])->filter(fn($p) => $p['metodo'] !== 'ninguno');
                $metodosPago  = $pagosReales->pluck('metodo')->unique()->values()->implode(',') ?: '';
                $totalCobrado = round($pagosReales->sum(fn($p) => (float) $p['monto']), 2);
                $ajuste       = $metodosPago !== '' ? round($totalCobrado - $totalReal, 2) : 0;
                $estado       = $metodosPago !== '' ? 'pagado' : 'pendiente';

                $venta = Venta::create([
                    'vendedor_id'      => $g['vendedor_id'],
                    'cliente_id'       => $clienteId,
                    'total'            => $totalReal,
                    'ajuste'           => $ajuste,
                    'metodo_pago'      => $metodosPago,
                    'estado'           => $estado,
                    'documento_tipo'   => $this->tiposDoc[$tipoLetra] ?? null,
                    'documento_numero' => $numero ?: null,
                    'observaciones'    => 'Importado desde Excel',
                    'fecha'            => $g['fecha'],
                ]);

                foreach ($detallesCalc as $d) {
                    $venta->detalles()->create($d);
                    $totalDetalles++;
                }
                $totalCreadas++;
            }
        });

        session()->forget('import_grupos');

        return redirect('/casadets/ventas')->with('success',
            "Importación completada: $totalCreadas venta(s) con $totalDetalles producto(s)."
        );
    }

    /**
     * Separa grupos en [nuevos, omitidos].
     * Omitidos = documentos con número que ya existen en la BD.
     * Grupos sin número de documento siempre se consideran nuevos.
     */
    private function filtrarDuplicados(array $grupos): array
    {
        $tipoMap = ['B' => 'boleta', 'F' => 'factura', 'P' => 'proforma', 'PR' => 'proforma'];

        // Construir lista de (tipo, numero) a buscar
        $buscar = [];
        foreach ($grupos as $g) {
            $docLetra = strtoupper(trim($g['doc'] ?? ''));
            $tipo = $tipoMap[$docLetra] ?? null;
            $num  = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');
            if ($tipo && $num !== '') {
                $buscar[$tipo][] = $num;
            }
        }

        // Consultar BD agrupado por tipo
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
        // Todos los alias ya están normalizados (sin tildes, minúsculas, sin espacios extra)
        $alias = [
            'fecha'        => ['fecha_emisi', 'fecha_emision', 'fecha'],
            'doc'          => ['doc', 'tipo_doc', 'tipo'],
            'serie'        => ['serie'],
            'nro'          => ['nrodocumen', 'nro_documento', 'nrodocumento', 'numero'],
            'producto'     => ['producto', 'descripcion', 'detalle'],
            'precio'       => ['precio_unitario', 'preciounit', 'precio'],
            'cantidad'     => ['cantidad', 'unidades', 'cant'],
            'total'        => ['total', 'subtotal', 'importe'],
            'razon_social' => ['nombrerazonsocial', 'razon_social', 'razon social', 'denominacion', 'nombre_cliente', 'nombre cliente', 'razonsocial', 'cliente'],
            'ruc'          => ['ruc', 'ruc_cliente', 'nro_ruc', 'nroruc', 'documento'],
        ];

        $mapa = [];
        foreach ($alias as $campo => $posibles) {
            $mapa[$campo] = null;
            foreach ($headers as $idx => $h) {
                $hNorm = $this->normalizarTexto($h);
                foreach ($posibles as $p) {
                    // Coincidencia exacta o por prefijo
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
        // Reemplazar vocales con tilde
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

        $valor = trim((string) $valor);
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
