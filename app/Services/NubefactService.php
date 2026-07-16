<?php

namespace App\Services;

use App\Models\NubefactComprobante;
use App\Models\Venta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NubefactService
{
    private string $token;
    private string $url;
    private string $ruc;
    private string $razonSocial;
    private string $nombreComercial;
    private string $direccion;

    public function __construct()
    {
        $this->token           = config('services.nubefact.token', '');
        $this->url             = config('services.nubefact.url', '');
        $this->ruc             = config('services.nubefact.ruc', '');
        $this->razonSocial     = config('services.nubefact.razon_social', '');
        $this->nombreComercial = config('services.nubefact.nombre_comercial', '');
        $this->direccion       = config('services.nubefact.direccion', '');
    }

    public function emitir(Venta $venta, ?int $ventaReferenciaId = null): NubefactComprobante
    {
        $venta->load(['detalles', 'cliente']);

        $tipoComprobante = $this->tipoComprobante($venta->documento_tipo);

        [$serie, $numero] = $this->parsearDocumento($venta->documento_numero);

        $payload = $this->buildPayload($venta, $tipoComprobante, $serie, $numero, $ventaReferenciaId);

        $comprobante = NubefactComprobante::create([
            'venta_id'           => $venta->id,
            'tipo_comprobante'   => $tipoComprobante,
            'serie'              => $serie,
            'numero'             => $numero,
            'estado'             => 'pendiente',
            'venta_referencia_id'=> $ventaReferenciaId,
        ]);

        try {
            $response = Http::withToken($this->token)
                ->timeout(30)
                ->post($this->url, $payload);

            $body = $response->json() ?? [];

            if ($response->successful() && ($body['aceptada_por_sunat'] ?? false)) {
                $comprobante->update([
                    'estado'              => 'aceptado',
                    'hash'                => $body['hash'] ?? null,
                    'xml'                 => $body['xml'] ?? null,
                    'cdr'                 => $body['cdr'] ?? null,
                    'pdf_url'             => $body['pdf'] ?? null,
                    'enlace_pdf'          => $body['enlace_del_pdf'] ?? null,
                    'nubefact_id'         => $body['id'] ?? null,
                    'respuesta_completa'  => $body,
                    'error_mensaje'       => null,
                ]);
            } else {
                $errorMsg = $body['errors'] ?? $body['sunat_description'] ?? $body['message'] ?? 'Error desconocido';
                if (is_array($errorMsg)) $errorMsg = implode(' | ', $errorMsg);

                $comprobante->update([
                    'estado'             => 'rechazado',
                    'respuesta_completa' => $body,
                    'error_mensaje'      => $errorMsg,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Nubefact error', ['venta_id' => $venta->id, 'error' => $e->getMessage()]);
            $comprobante->update([
                'estado'        => 'rechazado',
                'error_mensaje' => $e->getMessage(),
            ]);
        }

        return $comprobante->fresh();
    }

    private function buildPayload(Venta $venta, int $tipoComprobante, string $serie, int $numero, ?int $ventaReferenciaId): array
    {
        $igvPorcentaje = (float) ($venta->igv_porcentaje ?? 18.00);
        $igvIncluido   = (bool) ($venta->igv_incluido ?? true);
        $factor        = 1 + ($igvPorcentaje / 100);

        $cliente = $venta->cliente;

        $items        = [];
        $totalGravada = 0.0;
        $totalIgv     = 0.0;
        $totalTotal   = 0.0;

        foreach ($venta->detalles as $detalle) {
            $precioIngresado = (float) $detalle->precio_unitario;
            $cantidad        = (float) $detalle->cantidad;

            if ($igvIncluido) {
                $valorUnitario   = round($precioIngresado / $factor, 6);
                $precioUnitario  = $precioIngresado;
            } else {
                $valorUnitario   = $precioIngresado;
                $precioUnitario  = round($precioIngresado * $factor, 2);
            }

            $subtotal      = round($valorUnitario * $cantidad, 2);
            $igvItem       = round($precioUnitario * $cantidad - $subtotal, 2);
            $totalItem     = round($precioUnitario * $cantidad, 2);
            $igvUnitario   = round($precioUnitario - $valorUnitario, 2);

            $totalGravada += $subtotal;
            $totalIgv     += $igvItem;
            $totalTotal   += $totalItem;

            $items[] = [
                'unidad_de_medida'          => $detalle->unidad_medida ?? 'NIU',
                'codigo'                    => $detalle->codigo ?? '',
                'descripcion'               => $detalle->producto,
                'cantidad'                  => $cantidad,
                'valor_unitario'            => round($valorUnitario, 6),
                'precio_unitario'           => round($precioUnitario, 2),
                'subtotal'                  => $subtotal,
                'tipo_de_igv'               => 1,
                'igv'                       => $igvItem,
                'total'                     => $totalItem,
                'anticipo_regularizacion'   => false,
                'anticipo_documento_serie'  => '',
                'anticipo_documento_numero' => '',
            ];
        }

        $totalGravada = round($totalGravada, 2);
        $totalIgv     = round($totalIgv, 2);
        $totalTotal   = round($totalTotal, 2);

        $payload = [
            'operacion'                          => 'generar_comprobante',
            'tipo_de_comprobante'                => $tipoComprobante,
            'serie'                              => $serie,
            'numero'                             => $numero,
            'sunat_transaction'                  => 1,
            'cliente_tipo_de_documento'          => $cliente?->tipo_documento ?? ($tipoComprobante === 1 ? '6' : '1'),
            'cliente_numero_de_documento'        => $cliente?->documento ?? '',
            'cliente_denominacion'               => $cliente?->nombre ?? 'CLIENTE VARIOS',
            'cliente_direccion'                  => $cliente?->direccion ?? '',
            'cliente_email'                      => '',
            'cliente_email_1'                    => '',
            'cliente_email_2'                    => '',
            'fecha_de_emision'                   => $venta->fecha->format('d-m-Y'),
            'fecha_de_vencimiento'               => '',
            'moneda'                             => 1,
            'tipo_de_cambio'                     => '',
            'porcentaje_de_igv'                  => $igvPorcentaje,
            'descuento_global'                   => '',
            'total_descuento'                    => '',
            'total_anticipo'                     => '',
            'total_gravada'                      => $totalGravada,
            'total_inafecta'                     => '',
            'total_exonerada'                    => '',
            'total_igv'                          => $totalIgv,
            'total_gratuita'                     => '',
            'total_otros_cargos'                 => '',
            'total'                              => $totalTotal,
            'percepcion_tipo'                    => '',
            'percepcion_base_imponible'          => '',
            'total_percepcion'                   => '',
            'total_incluido_percepcion'          => '',
            'detraccion'                         => false,
            'observaciones'                      => $venta->observaciones ?? '',
            'documento_que_se_modifica_tipo'     => '',
            'documento_que_se_modifica_serie'    => '',
            'documento_que_se_modifica_numero'   => '',
            'tipo_de_nota_de_credito_o_debito'   => '',
            'enviar_automaticamente_a_la_sunat'  => true,
            'enviar_automaticamente_al_cliente'  => false,
            'codigo_unico'                       => '',
            'condiciones_de_pago'                => '',
            'medio_de_pago'                      => '',
            'placa_vehiculo'                     => '',
            'orden_compra_servicio'              => '',
            'tabla_personalizada_codigo'         => '',
            'formato_de_pdf'                     => '',
            'items'                              => $items,
        ];

        if ($tipoComprobante === 3 && $ventaReferenciaId) {
            $ventaRef = Venta::find($ventaReferenciaId);
            if ($ventaRef && $ventaRef->documento_numero) {
                [$serieRef, $numeroRef] = $this->parsearDocumento($ventaRef->documento_numero);
                $tipoRef = $this->tipoComprobante($ventaRef->documento_tipo);
                $payload['documento_que_se_modifica_tipo']   = $tipoRef;
                $payload['documento_que_se_modifica_serie']  = $serieRef;
                $payload['documento_que_se_modifica_numero'] = $numeroRef;
                $payload['tipo_de_nota_de_credito_o_debito'] = 1;
            }
        }

        return $payload;
    }

    private function tipoComprobante(string $tipo): int
    {
        return match (strtolower($tipo)) {
            'factura'       => 1,
            'boleta'        => 2,
            'nota_credito'  => 3,
            default         => 2,
        };
    }

    private function parsearDocumento(string $documentoNumero): array
    {
        $parts  = explode('-', $documentoNumero, 2);
        $serie  = $parts[0] ?? 'BBB1';
        $numero = (int) ($parts[1] ?? 1);
        return [$serie, $numero];
    }
}
