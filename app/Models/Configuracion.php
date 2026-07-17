<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor', 'grupo'];

    public static function get(string $clave, mixed $default = null): mixed
    {
        $row = static::where('clave', $clave)->first();
        return $row ? $row->valor : $default;
    }

    public static function set(string $clave, mixed $valor, string $grupo = 'general'): void
    {
        static::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor, 'grupo' => $grupo]
        );
    }

    public static function grupo(string $grupo): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('grupo', $grupo)->get()->keyBy('clave');
    }
}
