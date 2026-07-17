<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SunatController extends Controller
{
    public function consultar(Request $request)
    {
        $numero = preg_replace('/\D/', '', trim($request->input('numero', '')));
        $tipo   = strlen($numero) === 11 ? 'ruc' : (strlen($numero) === 8 ? 'dni' : null);

        if (!$tipo) {
            return response()->json(['error' => 'Ingresa un RUC (11 dígitos) o DNI (8 dígitos).'], 422);
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Referer' => 'https://casadets.pe', 'User-Agent' => 'casadets/1.0'])
                ->get("https://api.apis.net.pe/v2/{$tipo}/{$numero}");

            if (!$response->successful()) {
                return response()->json(['error' => 'No se encontró el número en SUNAT/RENIEC.'], 404);
            }

            $data = $response->json();

            if ($tipo === 'ruc') {
                $nombre    = $data['razonSocial']    ?? ($data['nombre'] ?? '');
                $direccion = $data['direccion']       ?? '';
                $docTipo   = 'RUC';
            } else {
                $ap = ($data['apellidoPaterno'] ?? '') . ' ' . ($data['apellidoMaterno'] ?? '');
                $nombre    = trim(($data['nombres'] ?? '') . ' ' . trim($ap));
                $direccion = '';
                $docTipo   = 'DNI';
            }

            if (!$nombre) {
                return response()->json(['error' => 'No se encontraron datos para ese número.'], 404);
            }

            // Buscar si ya existe como cliente en el sistema
            $clienteExistente = Cliente::where('documento', $numero)->first();

            return response()->json([
                'numero'    => $numero,
                'tipo'      => $docTipo,
                'nombre'    => $nombre,
                'direccion' => $direccion,
                'cliente_id'=> $clienteExistente?->id,
            ]);

        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error de conexión con la API: ' . $e->getMessage()], 500);
        }
    }

    public function guardarCliente(Request $request)
    {
        $data = $request->validate([
            'nombre'    => 'required|string|max:255',
            'documento' => 'required|string|max:20',
            'tipo_doc'  => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:500',
        ]);

        $cliente = Cliente::updateOrCreate(
            ['documento' => $data['documento']],
            [
                'nombre'    => $data['nombre'],
                'tipo_documento' => $data['tipo_doc'] ?? 'RUC',
                'direccion' => $data['direccion'] ?? '',
                'activo'    => true,
            ]
        );

        return response()->json(['id' => $cliente->id, 'nombre' => $cliente->nombre]);
    }
}
