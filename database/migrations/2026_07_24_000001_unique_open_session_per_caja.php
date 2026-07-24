<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cerrar sesiones duplicadas abiertas para la misma caja,
        // conservando solo la más reciente.
        // MySQL no permite referenciar la tabla actualizada en un subquery directo;
        // se envuelve en una tabla derivada intermedia para evitarlo.
        DB::statement("
            UPDATE caja_sesiones
            SET estado = 'cerrada'
            WHERE estado = 'abierta'
              AND caja_id IS NOT NULL
              AND id NOT IN (
                  SELECT max_id FROM (
                      SELECT MAX(id) AS max_id
                      FROM caja_sesiones
                      WHERE estado = 'abierta'
                        AND caja_id IS NOT NULL
                      GROUP BY caja_id
                  ) AS t
              )
        ");

        // Índice único parcial (solo SQLite y PostgreSQL lo soportan).
        // En MySQL lo omitimos: el booleano esta_abierta en cajas ya garantiza
        // una sola caja abierta a la vez; el historial de sesiones no necesita
        // la restricción a nivel de índice.
        $driver = DB::getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'])) {
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS unique_open_session_per_caja
                ON caja_sesiones (caja_id)
                WHERE estado = 'abierta' AND caja_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'])) {
            DB::statement("DROP INDEX IF EXISTS unique_open_session_per_caja");
        }
    }
};
