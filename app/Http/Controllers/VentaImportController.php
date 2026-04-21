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
    public function form()
    {
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        if ($vendedores->isEmpty()) {
            return redirect('/casadets/vendedores/create')
                ->with('error', 'Primero registra al menos un vendedor.');
        }

        return view('casadets.ventas.import', compact('vendedores'));
    }

    public function process(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'vendedor_id' => 'required|exists:vendedores,id',
            'metodo_pago' => 'required|in:efectivo,tarjeta,yape,plin,transferencia',
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

        // Detectar columnas por nombre del encabezado
        $headers = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
        $mapa = $this->mapearColumnas($headers);

        $faltantes = array_keys(array_filter($mapa, fn($v) => $v === null));
        if (!empty($faltantes)) {
            return back()->with('error', 'Faltan columnas en el Excel: ' . implode(', ', $faltantes));
        }

        // Agrupar por documento (Doc + Serie + NroDocumen)
        $grupos = [];
        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $doc = trim((string) ($r[$mapa['doc']] ?? ''));
            $serie = trim((string) ($r[$mapa['serie']] ?? ''));
            $numero = trim((string) ($r[$mapa['nro']] ?? ''));
            $producto = trim((string) ($r[$mapa['producto']] ?? ''));

            if ($producto === '' || ($doc === '' && $serie === '' && $numero === '')) {
                continue; // fila vacía
            }

            $key = $doc . '|' . $serie . '|' . $numero;

            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'fecha' => $this->parseFecha($r[$mapa['fecha']] ?? null),
                    'doc' => $doc,
                    'serie' => $serie,
                    'numero' => $numero,
                    'detalles' => [],
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
        }

        if (empty($grupos)) {
            return back()->with('error', 'No se encontraron filas válidas en el archivo.');
        }

        $tiposDoc = ['B' => 'boleta', 'F' => 'factura', 'P' => 'proforma'];
        $vendedorId = $request->vendedor_id;
        $metodoPago = $request->metodo_pago;
        $totalCreadas = 0;

        DB::transaction(function () use ($grupos, $vendedorId, $metodoPago, $tiposDoc, &$totalCreadas) {
            foreach ($grupos as $g) {
                $totalVenta = array_sum(array_column($g['detalles'], 'subtotal'));
                $tipoLetra = strtoupper($g['doc']);

                $venta = Venta::create([
                    'vendedor_id' => $vendedorId,
                    'total' => $totalVenta,
                    'metodo_pago' => $metodoPago,
                    'documento_tipo' => $tiposDoc[$tipoLetra] ?? null,
                    'documento_numero' => trim($g['serie'] . '-' . $g['numero'], '-'),
                    'observaciones' => 'Importado desde Excel',
                    'fecha' => $g['fecha'],
                ]);

                foreach ($g['detalles'] as $d) {
                    $venta->detalles()->create($d);
                }
                $totalCreadas++;
            }
        });

        $totalDetalles = array_sum(array_map(fn($g) => count($g['detalles']), $grupos));

        return redirect('/casadets/ventas')->with('success',
            "Importación completada: $totalCreadas venta(s) con $totalDetalles producto(s)."
        );
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

        // Si viene como número de Excel
        if (is_numeric($valor)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valor))->toDateString();
            } catch (\Exception $e) {
                // sigue intentando como texto
            }
        }

        $valor = trim((string) $valor);

        // Formatos comunes: dd/mm/YYYY, dd-mm-YYYY, YYYY-mm-dd
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
