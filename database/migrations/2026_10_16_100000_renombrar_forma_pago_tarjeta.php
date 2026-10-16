<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Solo existe una forma de pago con tarjeta (TARJETA); se muestra como
    // "Pago con tarjeta" (sin distinguir crédito/débito).
    public function up(): void
    {
        DB::table('payment_types')->where('clave', 'TARJETA')->update(['descripcion' => 'Pago con tarjeta']);
    }

    public function down(): void
    {
        DB::table('payment_types')->where('clave', 'TARJETA')->update(['descripcion' => 'Tarjeta de crédito']);
    }
};
