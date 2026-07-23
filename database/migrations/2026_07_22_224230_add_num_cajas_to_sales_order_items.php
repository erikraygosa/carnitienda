<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('num_cajas')->nullable()->after('cantidad')
                  ->comment('Número aproximado de cajas (referencia, el peso real se ajusta en despacho)');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('num_cajas');
        });
    }
};
