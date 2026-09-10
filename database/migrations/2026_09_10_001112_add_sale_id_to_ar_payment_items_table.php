<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite que un cobro (ar_payments) reparta FIFO también contra notas
     * de venta a crédito (sales), igual que ya hace con sales_order_id e
     * invoice_id.
     */
    public function up(): void
    {
        Schema::table('ar_payment_items', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('sales_order_id')->constrained('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ar_payment_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
        });
    }
};
