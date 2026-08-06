<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En algunos entornos, stock_transfers.status se quedó como el ENUM viejo
 * ('draft','completed','cancelled') de la migración original — la migración
 * 2026_03_21_025736_add_transfer_dispatch_fields solo agrega la columna
 * 'status' cuando NO existe, así que si ya existía (como ENUM), nunca se
 * convirtió al flujo real usado por la app (PENDIENTE/ASIGNADO/EN_RUTA/
 * COMPLETADO/CANCELADO/NO_COMPLETADO), y cualquier insert con esos valores
 * truena con "Data truncated for column 'status'".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_transfers')) return;

        $col = collect(DB::select("SHOW COLUMNS FROM stock_transfers WHERE Field = 'status'"))->first();
        if ($col && str_starts_with(strtolower($col->Type), 'enum')) {
            DB::statement("ALTER TABLE stock_transfers MODIFY status VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'");
            DB::table('stock_transfers')->where('status', 'completed')->update(['status' => 'COMPLETADO']);
            DB::table('stock_transfers')->where('status', 'cancelled')->update(['status' => 'CANCELADO']);
            DB::table('stock_transfers')->where('status', 'draft')->update(['status' => 'PENDIENTE']);
        }
    }

    public function down(): void
    {
        // No revertimos a ENUM: perdería los valores del flujo nuevo.
    }
};
