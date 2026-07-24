<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Primero cerrar cualquier sesión duplicada abierta para la misma caja,
        // conservando solo la más reciente para no violar el índice que vamos a crear.
        DB::statement("
            UPDATE caja_sesiones
            SET estado = 'cerrada'
            WHERE estado = 'abierta'
              AND caja_id IS NOT NULL
              AND id NOT IN (
                  SELECT MAX(id)
                  FROM caja_sesiones
                  WHERE estado = 'abierta'
                    AND caja_id IS NOT NULL
                  GROUP BY caja_id
              )
        ");

        // Índice único parcial: solo puede existir una fila con estado='abierta' por caja.
        // SQLite y PostgreSQL soportan índices parciales/filtrados.
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS unique_open_session_per_caja
            ON caja_sesiones (caja_id)
            WHERE estado = 'abierta' AND caja_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS unique_open_session_per_caja");
    }
};
