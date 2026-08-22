<?php

namespace App\Http\Controllers;

use App\Models\AplicacionPagoProveedor;
use App\Models\CuentaPorPagar;
use App\Models\LetraProveedor;
use App\Models\Movimiento;
use App\Models\PagoProveedor;
use App\Models\ProveedorCxp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CuentasPorPagarController extends Controller
{
    public function index(Request $request)
    {
        $query = CuentaPorPagar::with('proveedor')->withSum('aplicaciones', 'monto_aplicado');
        if ($request->filled('buscar')) {
            $term = '%' . trim($request->buscar) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('documento_numero', 'like', $term)
                  ->orWhere('serie', 'like', $term)
                  ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', $term)->orWhere('ruc', 'like', $term));
            });
        }
        if ($request->filled('serie')) $query->where('serie', strtoupper(trim($request->serie)));
        if ($request->input('estado') === 'pendientes') {
            $query->whereRaw('(cuentas_por_pagar.total > COALESCE((SELECT SUM(app.monto_aplicado) FROM aplicaciones_pago_proveedor app WHERE app.cuenta_por_pagar_id = cuentas_por_pagar.id), 0))');
        }
        if ($request->input('estado') === 'vencidas') $query->whereDate('fecha_vencimiento', '<', today());
        $cuentas = $query->orderByRaw('fecha_vencimiento IS NULL, fecha_vencimiento asc')->paginate(40)->withQueryString();
        $base = CuentaPorPagar::query();
        $totales = [
            'documentos' => (clone $base)->count(),
            'total' => (float) (clone $base)->sum('total'),
            'f001' => (clone $base)->where('serie', 'F001')->count(),
        ];
        return view('zendy.letras', compact('cuentas', 'totales'));
    }

    public function importForm()
    {
        return view('zendy.letras_import');
    }

    public function analizar(Request $request)
    {
        $request->validate(['archivo' => 'required|file|max:20480']);
        $archivo = $request->file('archivo');
        if (!in_array(strtolower($archivo->getClientOriginalExtension()), ['xlsx', 'xls', 'csv'], true)) {
            return back()->with('error', 'El archivo debe tener formato XLSX, XLS o CSV.');
        }
        $dir = storage_path('app/cxp-imports');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $path = $dir . '/reporte-' . bin2hex(random_bytes(8)) . '.' . strtolower($archivo->getClientOriginalExtension());
        $archivo->move($dir, basename($path));
        [$rows, $cols] = $this->leerReporte($path);
        if (!$cols['razon'] || !$cols['numero'] || !$cols['total']) {
            @unlink($path);
            return back()->with('error', 'El archivo no tiene las columnas requeridas: Razón Social, N° Comprobante y Total.');
        }

        $empresas = [];
        foreach (array_slice($rows, 1) as $row) {
            $razon = trim((string) ($row[$cols['razon']] ?? ''));
            $ruc = preg_replace('/\D+/', '', (string) ($cols['ruc'] ? ($row[$cols['ruc']] ?? '') : '')) ?: null;
            $numero = $this->normalizarDocumento($row[$cols['numero']] ?? '');
            $total = $this->numero($row[$cols['total']] ?? null);
            if (!$razon || !$numero || $total <= 0) continue;
            $key = $this->claveEmpresa($razon, $ruc);
            if (!isset($empresas[$key])) {
                $empresas[$key] = ['clave' => $key, 'razon_social' => $razon, 'ruc' => $ruc, 'documentos' => 0, 'f001' => 0, 'total' => 0];
            }
            $empresas[$key]['documentos']++;
            $empresas[$key]['f001'] += str_starts_with($numero, 'F001-') ? 1 : 0;
            $empresas[$key]['total'] += $total;
        }
        session(['cxp_import_path' => $path]);
        return view('zendy.letras_preview', ['empresas' => collect($empresas)->sortByDesc('total')->values()]);
    }

    public function importar(Request $request)
    {
        $archivo = $request->file('archivo');
        if ($archivo) {
            $request->validate(['archivo' => 'required|file|max:20480']);
            $path = $archivo->getRealPath();
        } else {
            $path = session('cxp_import_path');
            if (!$path || !is_file($path)) return redirect('/zendy/letras/importar')->with('error', 'La vista previa expiró. Sube nuevamente el archivo.');
        }
        [$rows, $cols] = $this->leerReporte($path);
        $seleccionadas = array_filter((array) $request->input('empresas', []));
        if (!$cols['razon'] || !$cols['numero'] || !$cols['total']) {
            return back()->with('error', 'El archivo no tiene las columnas requeridas: Razón Social, N° Comprobante y Total.');
        }
        $creadas = $duplicadas = $invalidas = 0;
        DB::transaction(function () use ($rows, $cols, $seleccionadas, &$creadas, &$duplicadas, &$invalidas) {
            foreach (array_slice($rows, 1) as $row) {
                $razon = trim((string) ($row[$cols['razon']] ?? ''));
                $numero = $this->normalizarDocumento($row[$cols['numero']] ?? '');
                $total = $this->numero($row[$cols['total']] ?? null);
                if (!$razon || !$numero || $total <= 0) { $invalidas++; continue; }
                $ruc = preg_replace('/\D+/', '', (string) ($cols['ruc'] ? ($row[$cols['ruc']] ?? '') : '')) ?: null;
                if ($seleccionadas && !in_array($this->claveEmpresa($razon, $ruc), $seleccionadas, true)) continue;
                $proveedor = $ruc
                    ? ProveedorCxp::firstOrCreate(['ruc' => $ruc], ['razon_social' => $razon])
                    : ProveedorCxp::firstOrCreate(['razon_social' => $razon, 'ruc' => null], ['activo' => true]);
                if ($proveedor->razon_social !== $razon && !$proveedor->wasRecentlyCreated) $proveedor->update(['razon_social' => $razon]);
                $partes = explode('-', $numero, 2);
                $cuenta = CuentaPorPagar::firstOrCreate(
                    ['proveedor_id' => $proveedor->id, 'tipo_documento' => strtolower(trim((string) ($cols['tipo'] ? ($row[$cols['tipo']] ?? 'factura') : 'factura'))), 'documento_numero' => $numero],
                    [
                        'serie' => strtoupper($partes[0] ?? ''), 'correlativo' => $partes[1] ?? null,
                        'fecha_emision' => $this->fecha($cols['emision'] ? ($row[$cols['emision']] ?? null) : null),
                        'fecha_vencimiento' => $this->fecha($cols['vencimiento'] ? ($row[$cols['vencimiento']] ?? null) : null),
                        'moneda' => strtoupper(trim((string) ($cols['moneda'] ? ($row[$cols['moneda']] ?? 'PEN') : 'PEN'))) ?: 'PEN',
                        'total' => $total, 'condicion_pago' => strtoupper(trim((string) ($cols['condicion'] ? ($row[$cols['condicion']] ?? '') : ''))),
                        'origen' => 'reporte_recibidos_2026-08-22',
                    ]
                );
                $cuenta->wasRecentlyCreated ? $creadas++ : $duplicadas++;
            }
        });
        if (!$archivo && is_file($path)) @unlink($path);
        session()->forget('cxp_import_path');
        return redirect('/zendy/letras')->with('success', "Importación completada: {$creadas} nuevas, {$duplicadas} duplicadas omitidas y {$invalidas} filas inválidas.");
    }

    public function show(CuentaPorPagar $cuenta)
    {
        $cuenta->load(['proveedor', 'letras', 'aplicaciones.pago']);
        return view('zendy.letra_show', compact('cuenta'));
    }

    public function crearLetra(Request $request, CuentaPorPagar $cuenta)
    {
        $data = $request->validate([
            'numero' => 'required|string|max:60', 'fecha_emision' => 'nullable|date',
            'fecha_vencimiento' => 'required|date', 'monto' => 'required|numeric|min:.01',
            'observaciones' => 'nullable|string|max:500',
        ]);
        if ((float) $data['monto'] > $cuenta->saldo + .01) return back()->with('error', 'El monto de la letra no puede superar el saldo pendiente.');
        $cuenta->letras()->create($data);
        return back()->with('success', 'Letra registrada y asociada a la factura.');
    }

    public function registrarPago(Request $request, CuentaPorPagar $cuenta)
    {
        $data = $request->validate([
            'fecha' => 'required|date', 'monto' => 'required|numeric|min:.01',
            'metodo_pago' => 'required|string|max:50', 'referencia' => 'nullable|string|max:120',
            'observaciones' => 'nullable|string|max:500', 'letra_id' => 'nullable|exists:letras_proveedor,id',
        ]);
        if ((float) $data['monto'] > $cuenta->saldo + .01) return back()->with('error', 'El pago supera el saldo pendiente de la factura.');
        if (!empty($data['letra_id']) && !$cuenta->letras()->whereKey($data['letra_id'])->exists()) {
            return back()->with('error', 'La letra seleccionada no pertenece a esta factura.');
        }
        DB::transaction(function () use ($data, $cuenta) {
            $pago = PagoProveedor::create([
                'proveedor_id' => $cuenta->proveedor_id, 'caja_id' => session('caja_id'),
                'user_id' => auth()->id(), 'fecha' => $data['fecha'], 'monto' => $data['monto'],
                'moneda' => $cuenta->moneda, 'metodo_pago' => $data['metodo_pago'],
                'referencia' => $data['referencia'] ?? null, 'observaciones' => $data['observaciones'] ?? null,
            ]);
            AplicacionPagoProveedor::create([
                'pago_proveedor_id' => $pago->id, 'cuenta_por_pagar_id' => $cuenta->id,
                'letra_proveedor_id' => $data['letra_id'] ?? null, 'monto_aplicado' => $data['monto'],
            ]);
            Movimiento::create([
                'tipo' => 'salida',
                'subtipo' => 'pago_proveedor',
                'origen' => 'auto',
                'estado' => 'activo',
                'empresa' => 'zendy',
                'caja_id' => session('caja_id'),
                'categoria' => 'Pago a proveedor',
                'metodo_pago' => $data['metodo_pago'],
                'referencia_tipo' => 'pago_proveedor',
                'referencia_id' => $pago->id,
                'user_id' => auth()->id(),
                'documento_tipo' => $cuenta->tipo_documento,
                'documento_numero' => $cuenta->documento_numero,
                'monto' => $data['monto'],
                'fecha' => $data['fecha'],
                'observaciones' => 'Pago de ' . $cuenta->documento_numero . ' a ' . $cuenta->proveedor->razon_social,
            ]);
        });
        return back()->with('success', 'Pago registrado correctamente.');
    }

    public function vaciar(Request $request)
    {
        abort_if(app()->environment('production'), 403, 'La limpieza masiva está deshabilitada en producción.');
        $request->validate(['confirmacion' => 'required|in:BORRAR TODO']);

        DB::transaction(function () {
            $pagoIds = PagoProveedor::query()->pluck('id');
            if ($pagoIds->isNotEmpty()) {
                Movimiento::where('referencia_tipo', 'pago_proveedor')
                    ->whereIn('referencia_id', $pagoIds)
                    ->delete();
            }
            AplicacionPagoProveedor::query()->delete();
            PagoProveedor::query()->delete();
            LetraProveedor::query()->delete();
            CuentaPorPagar::query()->delete();
            ProveedorCxp::query()->delete();
        });

        return redirect('/zendy/letras')->with('success', 'Se eliminaron todas las cuentas por pagar de prueba.');
    }

    private function normalizarDocumento($value): string
    {
        $v = strtoupper(trim((string) $value));
        $v = preg_replace('/\s+/', '', $v);
        if (preg_match('/^([A-Z0-9]+)-0*(\d+)$/', $v, $m)) return $m[1] . '-' . str_pad($m[2], 8, '0', STR_PAD_LEFT);
        return $v;
    }
    private function claveEmpresa(string $razon, ?string $ruc): string
    {
        return $ruc ? 'ruc:' . $ruc : 'nombre:' . sha1(mb_strtoupper(trim($razon)));
    }
    private function leerReporte(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true);
        $headers = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $rows[1] ?? []);
        $find = function (string $name) use ($headers) {
            foreach ($headers as $col => $header) if (str_contains($header, $name)) return $col;
            return null;
        };
        return [$rows, [
            'razon' => $find('razón social') ?: $find('razon social'),
            'ruc' => $find('ruc'), 'tipo' => $find('tipo doc'),
            'numero' => $find('comprobante'), 'emision' => $find('emisión') ?: $find('emision'),
            'vencimiento' => $find('vencimiento'), 'moneda' => $find('moneda'),
            'total' => $find('total en soles') ?: $find('total'), 'condicion' => $find('tipo de pago'),
        ]];
    }
    private function numero($value): float
    {
        $v = preg_replace('/[^\d,.-]/', '', (string) $value);
        if (str_contains($v, ',') && str_contains($v, '.')) $v = str_replace(',', '', $v);
        else $v = str_replace(',', '.', $v);
        return (float) $v;
    }
    private function fecha($value): ?string
    {
        if (!$value) return null;
        if (is_numeric($value)) return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        $time = strtotime((string) $value);
        return $time ? date('Y-m-d', $time) : null;
    }
}