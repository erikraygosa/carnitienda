<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablas = [
            'quotes',
            'sales_orders',
            'sales',
            'invoices',
        ];

        foreach ($tablas as $tabla) {
            if (Schema::hasTable($tabla) && ! Schema::hasColumn($tabla, 'company_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->foreignId('company_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('companies')
                        ->nullOnDelete();

                    $table->index('company_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['quotes', 'sales_orders', 'sales', 'invoices'] as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'company_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropForeign(['company_id']);
                    $table->dropColumn('company_id');
                });
            }
        }
    }
};