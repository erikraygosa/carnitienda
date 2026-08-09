<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permite que un ArPaymentItem cubra una factura libre (sin pedido)
     * directamente, en vez de forzosamente pasar por sales_order_id.
     * Un cobro cubre O una nota/pedido O una factura libre — nunca ambas.
     */
    public function up(): void
    {
        // sales_order_id era NOT NULL; ahora es opcional porque un item
        // puede cubrir una factura libre en su lugar.
        DB::statement('ALTER TABLE `ar_payment_items` MODIFY `sales_order_id` BIGINT UNSIGNED NULL');

        if (! Schema::hasColumn('ar_payment_items', 'invoice_id')) {
            Schema::table('ar_payment_items', function (Blueprint $table) {
                $table->foreignId('invoice_id')->nullable()->after('sales_order_id')
                    ->constrained('invoices')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ar_payment_items', 'invoice_id')) {
            Schema::table('ar_payment_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('invoice_id');
            });
        }
        DB::statement('ALTER TABLE `ar_payment_items` MODIFY `sales_order_id` BIGINT UNSIGNED NOT NULL');
    }
};
