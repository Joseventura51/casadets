<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\ReporteCaja;
use App\Services\CajaService;
use App\Services\ReporteCajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReporteCajaController extends Controller
{
    public function index(Request $request)
    {
        $cajasDisponibles = CajaService::cajasUsuario()->values();

        $cajaId = $request->input('caja_id', session('caja_id'));
        if ($request->has('caja_id') && $cajaId) {
            session(['caja_id' => $cajaId]);
        }
        if (!$cajaId && $cajasDisponibles->count() === 1) {
            $cajaId = $cajasDisponibles->first()->id;
        }

        $cajaSeleccionada = $cajaId ? Caja::find($cajaId) : null;

        $query = ReporteCaja::with(['sesion', 'caja'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($cajaSeleccionada) {
            $query->where('caja_id', $cajaSeleccionada->id);
        }

        if ($request->filled('desde')) {
            $query->where('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->where('fecha', '<=', $request->hasta);
        }

        $reportes = $query->paginate(25)->withQueryString();

        return view('casadets.reportes_caja.index', compact(
            'reportes', 'cajasDisponibles', 'cajaSeleccionada'
        ));
    }

    public function descargar(ReporteCaja $reporte)
    {
        if (!$reporte->existeArchivo()) {
            return back()->with('error', 'El archivo de este reporte ya no está disponible.');
        }

        $nombre = "reporte_caja_{$reporte->caja?->codigo}_{$reporte->fecha->format('Y-m-d')}.xlsx";

        return Storage::disk('local')->download($reporte->archivo, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function regenerar(ReporteCaja $reporte)
    {
        try {
            $sesion = $reporte->sesion;
            ReporteCajaService::generar($sesion);
            return back()->with('success', 'Reporte regenerado correctamente.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al regenerar: ' . $e->getMessage());
        }
    }
}
