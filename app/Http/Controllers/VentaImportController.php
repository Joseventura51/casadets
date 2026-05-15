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
    private array $tiposDoc = ['B' => 'boleta', 'F' => 'factura', 'P' => 'proforma'];

    public function form()
    {
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        if ($vendedores->isEmpty()) {
            return redirect('/casadets/vendedores/create')
                ->with('error', 'Primero registra al menos un vendedor.');
        }

        return view('casadets.ventas.import', compact('vendedores'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',      
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

        $headers = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
        $mapa = $this->mapearColumnas($headers);

        $faltantes = array_keys(array_filter($mapa, fn($v) => $v === null));
        if (!empty($faltantes)) {
            return back()->with('error', 'Faltan columnas en el Excel: ' . implode(', ', $faltantes));
        }

        $grupos = [];
        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $doc = trim((string) ($r[$mapa['doc']] ?? ''));
            $serie = trim((string) ($r[$mapa['serie']] ?? ''));
            $numero = trim((string) ($r[$mapa['nro']] ?? ''));
            $producto = trim((string) ($r[$mapa['producto']] ?? ''));

            if ($producto === '' || ($doc === '' && $serie === '' && $numero === '')) {
                continue;
            }

            $key = $doc . '|' . $serie . '|' . $numero;

            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'fecha' => $this->parseFecha($r[$mapa['fecha']] ?? null),
                    'doc' => $doc,
                    'serie' => $serie,
                    'numero' => $numero,
                    'detalles' => [],
                    'total' => 0,
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

        $existentes = $this->buscarFacturasExistentes($grupos);

        // Detectar facturas que YA existen en BD para advertir antes de confirmar
        $existentes = $this->buscarFacturasExistentes(array_values($grupos));

        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        return view('casadets.ventas.import_preview', [
            'grupos' => $grupos,
            'vendedores' => $vendedores,
            'vendedor_id_default' => 1,
            'metodo_pago_default' => 'efectivo',
            'duplicadosExistentes' => $existentes,
        ]);
    }

    public function confirm(Request $request)
    {
        $data = $request->validate([
            'ventas' => 'required|array|min:1',
            'ventas.*.fecha' => 'required|date',
            'ventas.*.doc' => 'nullable|string|max:10',
            'ventas.*.serie' => 'nullable|string|max:50',
            'ventas.*.numero' => 'nullable|string|max:50',
            'ventas.*.vendedor_id' => 'required|exists:vendedores,id',
            'ventas.*.metodo_pago' => 'required|in:efectivo,tarjeta,yape,plin,transferencia',
            'ventas.*.total_cobrado' => 'required|numeric|min:0',
            'ventas.*.detalles' => 'required|array|min:1',
            'ventas.*.detalles.*.producto' => 'required|string|max:255',
            'ventas.*.detalles.*.cantidad' => 'required|numeric|min:0',
            'ventas.*.detalles.*.precio_unitario' => 'required|numeric|min:0',
        ]);

        // Validar unicidad de facturas (entre sí y vs BD)
        $errores = $this->validarFacturasUnicas($data['ventas']);
        if (!empty($errores)) {
            return back()->withInput()->with('error', 'No se importó nada. ' . implode(' ', $errores));
        }

        $totalCreadas = 0;
        $totalDetalles = 0;

        DB::transaction(function () use ($data, &$totalCreadas, &$totalDetalles) {
            foreach ($data['ventas'] as $g) {
                $detallesCalc = array_map(function ($d) {
                    $sub = round((float) $d['cantidad'] * (float) $d['precio_unitario'], 2);
                    return [
                        'producto' => $d['producto'],
                        'cantidad' => $d['cantidad'],
                        'precio_unitario' => $d['precio_unitario'],
                        'subtotal' => $sub,
                    ];
                }, $g['detalles']);

                $totalReal = round(array_sum(array_column($detallesCalc, 'subtotal')), 2);
                $totalCobrado = (float) $g['total_cobrado'];
                $ajuste = round($totalCobrado - $totalReal, 2);
                $tipoLetra = strtoupper(trim($g['doc']??''));
                if ($tipoLetra === 'PROFORMA'){
                    $tipoLetra = 'P';
                }
                $numero = trim(($g['doc'] ?? '').($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');

                $venta = Venta::create([
                    'vendedor_id' => $g['vendedor_id'],
                    'total' => $totalReal,
                    'ajuste' => $ajuste,
                    'metodo_pago' => $g['metodo_pago'],
                    'documento_tipo' => $this->tiposDoc[$tipoLetra] ?? null,
                    'documento_numero' => $numero ?: null,
                    'observaciones' => 'Importado desde Excel',
                    'fecha' => $g['fecha'],
                ]);

                foreach ($detallesCalc as $d) {
                    $venta->detalles()->create($d);
                    $totalDetalles++;
                }
                $totalCreadas++;
            }
        });

        return redirect('/casadets/ventas')->with('success',
            "Importación completada: $totalCreadas venta(s) con $totalDetalles producto(s)."
        );
    }

    private function buscarFacturasExistentes(array $grupos): array
    {
        $numeros = [];
        foreach ($grupos as $g) {
            if (strtoupper($g['doc'] ?? '') === 'F') {
                $num = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');
                if ($num !== '') $numeros[] = $num;
            }
        }
        if (empty($numeros)) return [];

        return Venta::where('documento_tipo', 'factura')
            ->whereIn('documento_numero', $numeros)
            ->pluck('documento_numero')
            ->toArray();
    }

    private function validarFacturasUnicas(array $ventas): array
    {
        $errores = [];
        $vistos = [];
        $aBuscar = [];

        foreach ($ventas as $g) {
            if (strtoupper($g['doc'] ?? '') !== 'F') continue;
            $num = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');
            if ($num === '') continue;

            if (isset($vistos[$num])) {
                $errores[] = "La factura $num está duplicada en el archivo.";
            }
            $vistos[$num] = true;
            $aBuscar[] = $num;
        }

        if (!empty($aBuscar)) {
            $existentes = Venta::where('documento_tipo', 'factura')
                ->whereIn('documento_numero', $aBuscar)
                ->pluck('documento_numero')
                ->toArray();
            foreach ($existentes as $e) {
                $errores[] = "La factura $e ya existe en el sistema.";
            }
        }
        return $errores;
    }

    private function mapearColumnas(array $headers): array
    {
        $alias = [
            'fecha' => ['fecha_emisi', 'fecha_emision', 'fecha'],
            'doc' => ['doc', 'tipo', 'tipo_doc'],
            'serie' => ['serie'],
            'nro' => ['nrodocumen', 'numero', 'nro_documento', 'nrodocumento'],
            'producto' => ['producto', 'descripcion', 'descripción'],
            'precio' => ['precio', 'precio_unitario', 'preciounit'],
            'cantidad' => ['cantidad', 'unidades'],
            'total' => ['total', 'subtotal', 'importe'],
        ];

        $mapa = [];
        foreach ($alias as $campo => $posibles) {
            $mapa[$campo] = null;
            foreach ($headers as $idx => $h) {
                foreach ($posibles as $p) {
                    if (str_starts_with($h, $p)) {
                        $mapa[$campo] = $idx;
                        break 2;
                    }
                }
            }
        }
        return $mapa;
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
