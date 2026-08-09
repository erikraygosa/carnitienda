<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Para Notas de Crédito (tipo_comprobante E): referencia a la factura de
     * Ingreso original que se está afectando ("CFDI Relacionados", tipo de
     * relación 01 - Nota de crédito de los documentos relacionados).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'related_invoice_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('related_invoice_id')->nullable()->after('sale_id')
                    ->constrained('invoices')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'related_invoice_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('related_invoice_id');
            });
        }
    }
};
