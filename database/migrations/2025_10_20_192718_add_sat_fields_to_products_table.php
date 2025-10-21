<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products','clave_prod_serv')) {
                // Catálogo SAT (8 dígitos típico, ej. 01010101)
                $table->string('clave_prod_serv', 10)->nullable()->after('precio_base');
            }
            if (!Schema::hasColumn('products','clave_unidad')) {
                // Catálogo SAT de unidades (ej. H87)
                $table->string('clave_unidad', 10)->nullable()->after('clave_prod_serv');
            }
            if (!Schema::hasColumn('products','unidad')) {
                // Texto visible (ej. PZA, KG, LT)
                $table->string('unidad', 20)->nullable()->after('clave_unidad');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products','unidad')) $table->dropColumn('unidad');
            if (Schema::hasColumn('products','clave_unidad')) $table->dropColumn('clave_unidad');
            if (Schema::hasColumn('products','clave_prod_serv')) $table->dropColumn('clave_prod_serv');
        });
    }
};
