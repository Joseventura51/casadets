<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Services\NubefactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConfiguracionNubefactController extends Controller
{
    private array $campos = [
        'nubefact_token'           => 'Token API Nubefact',
        'nubefact_url'             => 'URL API',
        'nubefact_ruc'             => 'RUC',
        'nubefact_razon_social'    => 'Razón Social',
        'nubefact_nombre_comercial'=> 'Nombre Comercial',
        'nubefact_direccion'       => 'Dirección',
        'nubefact_serie_factura'   => 'Serie Factura',
        'nubefact_serie_boleta'    => 'Serie Boleta',
        'nubefact_igv_porcentaje'  => 'IGV (%)',
    ];

    public function index()
    {
        $cfg = Configuracion::grupo('nubefact');

        $valores = [
            'token'            => $cfg['nubefact_token']?->valor ?? config('services.nubefact.token', ''),
            'url'              => $cfg['nubefact_url']?->valor ?? config('services.nubefact.url', 'https://api.nubefact.com/api/v1'),
            'ruc'              => $cfg['nubefact_ruc']?->valor ?? config('services.nubefact.ruc', ''),
            'razon_social'     => $cfg['nubefact_razon_social']?->valor ?? config('services.nubefact.razon_social', ''),
            'nombre_comercial' => $cfg['nubefact_nombre_comercial']?->valor ?? config('services.nubefact.nombre_comercial', ''),
            'direccion'        => $cfg['nubefact_direccion']?->valor ?? config('services.nubefact.direccion', ''),
            'serie_factura'    => $cfg['nubefact_serie_factura']?->valor ?? config('services.nubefact.serie_factura', 'FFF1'),
            'serie_boleta'     => $cfg['nubefact_serie_boleta']?->valor ?? config('services.nubefact.serie_boleta', 'BBB1'),
            'igv_porcentaje'   => $cfg['nubefact_igv_porcentaje']?->valor ?? '18',
        ];

        $tokenConfigurado = !empty($valores['token']);

        return view('admin.nubefact', compact('valores', 'tokenConfigurado'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'ruc'              => 'required|digits:11',
            'razon_social'     => 'required|string|max:200',
            'nombre_comercial' => 'nullable|string|max:200',
            'direccion'        => 'nullable|string|max:300',
            'serie_factura'    => 'required|string|max:10',
            'serie_boleta'     => 'required|string|max:10',
            'igv_porcentaje'   => 'required|numeric|min:0|max:100',
            'url'              => 'required|url',
        ]);

        $map = [
            'nubefact_url'              => $request->url,
            'nubefact_ruc'              => $request->ruc,
            'nubefact_razon_social'     => $request->razon_social,
            'nubefact_nombre_comercial' => $request->nombre_comercial ?? '',
            'nubefact_direccion'        => $request->direccion ?? '',
            'nubefact_serie_factura'    => strtoupper($request->serie_factura),
            'nubefact_serie_boleta'     => strtoupper($request->serie_boleta),
            'nubefact_igv_porcentaje'   => $request->igv_porcentaje,
        ];

        if ($request->filled('token')) {
            $map['nubefact_token'] = $request->token;
        }

        foreach ($map as $clave => $valor) {
            Configuracion::set($clave, $valor, 'nubefact');
        }

        return back()->with('success', 'Configuración Nubefact guardada correctamente.');
    }

    public function testConexion()
    {
        $token = Configuracion::get('nubefact_token') ?? config('services.nubefact.token', '');
        $url   = Configuracion::get('nubefact_url')   ?? config('services.nubefact.url', 'https://api.nubefact.com/api/v1');

        if (empty($token)) {
            return response()->json(['ok' => false, 'mensaje' => 'Token no configurado. Ingresa tu Token API de Nubefact y guarda primero.']);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get(rtrim($url, '/') . '/');

            if ($response->status() === 401) {
                return response()->json(['ok' => false, 'mensaje' => 'Token inválido — Nubefact rechazó la autenticación (401).']);
            }

            if ($response->serverError()) {
                return response()->json(['ok' => false, 'mensaje' => 'Error en servidor Nubefact (' . $response->status() . ').']);
            }

            return response()->json(['ok' => true, 'mensaje' => 'Conexión con Nubefact exitosa (' . $response->status() . ').']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => 'Error de red: ' . $e->getMessage()]);
        }
    }
}
