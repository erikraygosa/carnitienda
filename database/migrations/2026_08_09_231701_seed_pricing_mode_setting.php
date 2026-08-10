<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 'global' conserva el comportamiento actual (un solo precio por producto,
        // el mismo en todos los almacenes) — no rompe nada en instalaciones existentes.
        DB::table('system_settings')->updateOrInsert(
            ['clave' => 'precios.modo'],
            [
                'valor'       => 'global',
                'tipo'        => 'string',
                'grupo'       => 'precios',
                'descripcion' => "Cómo se resuelve el precio de venta: 'global' (un precio por producto) o 'almacen' (precio propio por almacén, con el de Matriz como base)",
                'es_publica'  => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('system_settings')->where('clave', 'precios.modo')->delete();
    }
};
