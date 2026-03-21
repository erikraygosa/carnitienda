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
    Schema::table('products', function (Blueprint $table) {
        $table->string('sat_clave_prod_serv', 20)->nullable()->after('barcode');
        $table->string('sat_clave_unidad', 10)->nullable()->after('sat_clave_prod_serv');
        $table->string('sat_objeto_imp', 2)->default('02')->after('sat_clave_unidad');
        $table->string('sat_tipo_factor', 10)->default('Tasa')->after('sat_objeto_imp');
        $table->decimal('sat_tasa_iva', 8, 6)->nullable()->after('sat_tipo_factor');
        $table->decimal('sat_tasa_ieps', 8, 6)->nullable()->after('sat_tasa_iva');
        $table->string('sat_no_identificacion', 100)->nullable()->after('sat_tasa_ieps');
    });
}

public function down(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropColumn([
            'sat_clave_prod_serv','sat_clave_unidad','sat_objeto_imp',
            'sat_tipo_factor','sat_tasa_iva','sat_tasa_ieps','sat_no_identificacion',
        ]);
    });
}
};
