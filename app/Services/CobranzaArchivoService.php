<?php

namespace App\Services;

use App\Models\CobranzaArchivo;
use App\Models\Pago;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CobranzaArchivoService
{
    const MAX_DIMENSION = 1600;
    const WEBP_QUALITY  = 82;

    /**
     * Procesa y guarda una imagen de evidencia para un pago.
     * Convierte a WebP y redimensiona si supera MAX_DIMENSION px.
     */
    public function guardar(UploadedFile $file, Pago $pago, ?int $userId = null): CobranzaArchivo
    {
        $nombreOriginal = $file->getClientOriginalName();
        $extension      = strtolower($file->getClientOriginalExtension());

        // Directorio: cobranzas/YYYY/MM/
        $carpeta = 'cobranzas/' . now()->format('Y/m');
        $nombre  = Str::uuid() . '.webp';
        $ruta    = $carpeta . '/' . $nombre;

        // Convertir a WebP con GD
        $webpContent = $this->convertirAWebp($file->getRealPath(), $extension);

        Storage::disk('public')->put($ruta, $webpContent);

        $tamano = strlen($webpContent);

        return CobranzaArchivo::create([
            'pago_id'        => $pago->id,
            'nombre_original'=> $nombreOriginal,
            'ruta'           => $ruta,
            'extension'      => 'webp',
            'mime_type'      => 'image/webp',
            'tamano'         => $tamano,
            'usuario_id'     => $userId,
        ]);
    }

    /**
     * Convierte una imagen a WebP usando GD.
     * Redimensiona si algún lado supera MAX_DIMENSION px.
     */
    private function convertirAWebp(string $path, string $extension): string
    {
        $gd = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png'         => @imagecreatefrompng($path),
            'webp'        => @imagecreatefromwebp($path),
            default       => false,
        };

        if (!$gd) {
            // Fallback: devolver el archivo original sin conversión
            return file_get_contents($path);
        }

        $w = imagesx($gd);
        $h = imagesy($gd);

        // Redimensionar si es demasiado grande
        if ($w > self::MAX_DIMENSION || $h > self::MAX_DIMENSION) {
            if ($w >= $h) {
                $nw = self::MAX_DIMENSION;
                $nh = (int) round($h * self::MAX_DIMENSION / $w);
            } else {
                $nh = self::MAX_DIMENSION;
                $nw = (int) round($w * self::MAX_DIMENSION / $h);
            }

            $resized = imagecreatetruecolor($nw, $nh);

            // Preservar transparencia para PNG
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparente = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparente);

            imagecopyresampled($resized, $gd, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($gd);
            $gd = $resized;
        }

        ob_start();
        imagewebp($gd, null, self::WEBP_QUALITY);
        $webpContent = ob_get_clean();
        imagedestroy($gd);

        return $webpContent;
    }

    /**
     * Elimina todos los archivos asociados a un pago.
     */
    public function eliminarPorPago(Pago $pago): void
    {
        $pago->archivos->each->delete();
    }
}
