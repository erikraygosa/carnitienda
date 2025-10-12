<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Código de barras (opcional)
            $table->string('barcode', 100)->nullable()->unique()->after('sku');

            // IVA / impuesto directo (elegimos tasa_iva para evitar FK)
            $table->decimal('tasa_iva', 5, 2)->default(0.00)->after('unidad');

            // Valuación de inventario
            $table->decimal('costo_promedio', 14, 4)->default(0.0000)->after('precio_base');

            // Bandera para servicios o ítems sin control de stock
            $table->boolean('maneja_inventario')->default(true)->after('stock_min');

            // Notas internas
            $table->text('notas')->nullable()->after('activo');

            // Si prefieres manejar un catálogo de impuestos, descomenta:
            // $table->unsignedBigInteger('impuesto_id')->nullable()->after('unidad');
            // $table->foreign('impuesto_id')->references('id')->on('impuestos')
            //       ->nullOnDelete(); // o ->cascadeOnDelete() según tu regla
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Importante: para revertir unique en barcode, solo basta con dropear la columna
            // Laravel dropea índice implícito junto con la columna.
            if (Schema::hasColumn('products', 'barcode')) {
                $table->dropColumn('barcode');
            }
            if (Schema::hasColumn('products', 'tasa_iva')) {
                $table->dropColumn('tasa_iva');
            }
            if (Schema::hasColumn('products', 'costo_promedio')) {
                $table->dropColumn('costo_promedio');
            }
            if (Schema::hasColumn('products', 'maneja_inventario')) {
                $table->dropColumn('maneja_inventario');
            }
            if (Schema::hasColumn('products', 'notas')) {
                $table->dropColumn('notas');
            }

            // Reverso si usaste impuesto_id:
            // if (Schema::hasColumn('products', 'impuesto_id')) {
            //     $table->dropForeign(['impuesto_id']);
            //     $table->dropColumn('impuesto_id');
            // }
        });
    }
};
