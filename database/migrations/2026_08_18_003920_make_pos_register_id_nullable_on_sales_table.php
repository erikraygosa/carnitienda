<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las notas de venta directas ya no usan pos_register_id (se amarran a
     * cash_register_id) — era NOT NULL sin default, lo que tronaba el
     * insert en cuanto dejamos de mandarlo.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('pos_register_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('pos_register_id')->nullable(false)->change();
        });
    }
};
