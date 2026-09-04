<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('payment_types')->where('clave', 'TARJETA')->exists()) {
            DB::table('payment_types')->insert([
                'clave'       => 'TARJETA',
                'descripcion' => 'Tarjeta de crédito',
                'activo'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('payment_types')->where('clave', 'TARJETA')->delete();
    }
};
