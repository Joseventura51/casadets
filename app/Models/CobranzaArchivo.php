<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CobranzaArchivo extends Model
{
    protected $fillable = [
        'pago_id',
        'nombre_original',
        'ruta',
        'extension',
        'mime_type',
        'tamano',
        'usuario_id',
    ];

    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function existeArchivo(): bool
    {
        return Storage::disk('public')->exists($this->ruta);
    }

    public function urlPublica(): string
    {
        return Storage::disk('public')->url($this->ruta);
    }

    protected static function booted(): void
    {
        static::deleting(function (CobranzaArchivo $archivo) {
            if (Storage::disk('public')->exists($archivo->ruta)) {
                Storage::disk('public')->delete($archivo->ruta);
            }
        });
    }
}
