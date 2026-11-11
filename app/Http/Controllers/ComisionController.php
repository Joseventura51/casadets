<?php

namespace App\Http\Controllers;

use App\Models\ComisionMovimiento;
use App\Models\Vendedor;
use App\Services\VendedorScope;
use App\Support\DecimalMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComisionController extends Controller
{
    public function index(Request $request)
    {
        $query = ComisionMovimiento::query()->with(['vendedor', 'venta', 'user']);
        $allowedVendedores = VendedorScope::ids();
        if ($allowedVendedores !== null) {
            $query->whereIn('vendedor_id', $allowedVendedores);
        }
        if (VendedorScope::cajaIds() !== null) {
            $query->whereHas('venta', fn ($q) => VendedorScope::aplicar($q));
        }
        if ($request->filled('vendedor')) {
            $query->where('vendedor_id', $request->integer('vendedor'));
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_efectiva', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_efectiva', '<=', $request->input('hasta'));
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        $movimientos = $query->orderByDesc('fecha_efectiva')->orderByDesc('id')
            ->paginate(50)->withQueryString();
        $totalesQuery = ComisionMovimiento::query()
            ->when($allowedVendedores !== null, fn ($q) => $q->whereIn('vendedor_id', $allowedVendedores))
            ->when(VendedorScope::cajaIds() !== null, fn ($q) => $q->whereHas('venta', fn ($venta) => VendedorScope::aplicar($venta)))
            ->when($request->filled('vendedor'), fn ($q) => $q->where('vendedor_id', $request->integer('vendedor')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_efectiva', '>=', $request->input('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_efectiva', '<=', $request->input('hasta')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')));
        $totales = $totalesQuery
            ->selectRaw("COALESCE(SUM(CASE WHEN importe > 0 THEN importe ELSE 0 END), 0) as positivos,
                COALESCE(SUM(CASE WHEN importe < 0 THEN importe ELSE 0 END), 0) as negativos,
                COALESCE(SUM(importe), 0) as saldo")
            ->first();

        $vendedores = Vendedor::query()
            ->when($allowedVendedores !== null, fn ($q) => $q->whereIn('id', $allowedVendedores))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        $totales = [
            'positivos' => DecimalMoney::formatCents(DecimalMoney::cents($totales->positivos ?? 0)),
            'negativos' => DecimalMoney::formatCents(DecimalMoney::cents($totales->negativos ?? 0)),
            'saldo' => DecimalMoney::formatCents(DecimalMoney::cents($totales->saldo ?? 0)),
        ];

        return view('casadets.comisiones.index', compact('movimientos', 'totales', 'vendedores'));
    }
}
