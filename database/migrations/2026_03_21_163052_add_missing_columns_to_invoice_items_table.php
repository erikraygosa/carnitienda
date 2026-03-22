<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('invoice_items', function (Blueprint $table) {
        $table->decimal('valor_unitario', 14, 6)->default(0)->after('cantidad');
        $table->decimal('base', 14, 6)->default(0)->after('descuento');
        $table->string('objeto_imp', 2)->default('02')->after('base');
        $table->decimal('iva_pct', 8, 4)->default(0)->after('objeto_imp');
        $table->decimal('iva_importe', 14, 6)->default(0)->after('iva_pct');
        $table->decimal('ieps_pct', 8, 4)->default(0)->after('iva_importe');
        $table->decimal('ieps_importe', 14, 6)->default(0)->after('ieps_pct');
        $table->decimal('importe', 14, 6)->default(0)->after('ieps_importe');
    });
}

public function down(): void
{
    Schema::table('invoice_items', function (Blueprint $table) {
        $table->dropColumn([
            'valor_unitario', 'base', 'objeto_imp',
            'iva_pct', 'iva_importe',
            'ieps_pct', 'ieps_importe', 'importe',
        ]);
    });
}
};
