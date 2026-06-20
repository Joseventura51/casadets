<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteCaja extends Model
{
    protected $fillable = ['caja_sesion_id', 'caja_id', 'fecha', 'archivo', 'generado_at'];

    protected $casts = [
        'fecha'        => 'date',
        'generado_at'  => 'datetime',
    ];

    public function sesion()
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class);
    }

    public function existeArchivo(): bool
    {
        return $this->archivo && \Storage::disk('local')->exists($this->archivo);
    }
}
