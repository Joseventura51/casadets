<?php

namespace App\Http\Controllers;

use App\Models\CobranzaArchivo;
use App\Models\Pago;
use App\Services\CobranzaArchivoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CobranzaArchivoController extends Controller
{
    /**
     * Sube uno o varios archivos de evidencia para un pago.
     * Espera: pago_id + archivos[] (multipart)
     */
    public function upload(Request $request)
    {
        $request->validate([
            'pago_id'    => 'required|integer|exists:pagos,id',
            'archivos'   => 'required|array|min:1|max:10',
            'archivos.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:5120', // 5 MB
            ],
        ], [
            'archivos.*.mimes' => 'Solo se permiten imágenes JPG, PNG o WEBP.',
            'archivos.*.max'   => 'Cada imagen debe pesar menos de 5 MB.',
        ]);

        $pago    = Pago::findOrFail($request->pago_id);
        $service = app(CobranzaArchivoService::class);
        $guardados = [];

        foreach ($request->file('archivos') as $file) {
            $archivo   = $service->guardar($file, $pago, auth()->id());
            $guardados[] = [
                'id'              => $archivo->id,
                'nombre_original' => $archivo->nombre_original,
                'url'             => $archivo->urlPublica(),
                'tamano'          => $archivo->tamano,
            ];
        }

        return response()->json([
            'success'   => true,
            'archivos'  => $guardados,
            'message'   => count($guardados) . ' imagen(es) guardada(s).',
        ]);
    }

    /**
     * Elimina un archivo de evidencia.
     */
    public function destroy(CobranzaArchivo $archivo)
    {
        $archivo->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Imagen eliminada.');
    }

    /**
     * Descarga / muestra un archivo de evidencia.
     */
    public function ver(CobranzaArchivo $archivo)
    {
        if (!$archivo->existeArchivo()) {
            abort(404, 'Archivo no encontrado.');
        }

        $nombreDescarga = pathinfo($archivo->nombre_original, PATHINFO_FILENAME) . '.webp';

        return Storage::disk('public')->download($archivo->ruta, $nombreDescarga, [
            'Content-Type' => 'image/webp',
        ]);
    }
}
