<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Dirección fiscal (para facturación)
            $table->string('fiscal_calle')->nullable()->after('cp');
            $table->string('fiscal_numero')->nullable()->after('fiscal_calle');
            $table->string('fiscal_colonia')->nullable()->after('fiscal_numero');
            $table->string('fiscal_ciudad')->nullable()->after('fiscal_colonia');
            $table->string('fiscal_estado')->nullable()->after('fiscal_ciudad');
            $table->string('fiscal_cp')->nullable()->after('fiscal_estado');

            // Dirección de entrega
            $table->string('entrega_calle')->nullable()->after('fiscal_cp');
            $table->string('entrega_numero')->nullable()->after('entrega_calle');
            $table->string('entrega_colonia')->nullable()->after('entrega_numero');
            $table->string('entrega_ciudad')->nullable()->after('entrega_colonia');
            $table->string('entrega_estado')->nullable()->after('entrega_ciudad');
            $table->string('entrega_cp')->nullable()->after('entrega_estado');
            $table->boolean('entrega_igual_fiscal')->default(false)->after('entrega_cp');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_calle','fiscal_numero','fiscal_colonia',
                'fiscal_ciudad','fiscal_estado','fiscal_cp',
                'entrega_calle','entrega_numero','entrega_colonia',
                'entrega_ciudad','entrega_estado','entrega_cp',
                'entrega_igual_fiscal',
            ]);
        });
    }
};