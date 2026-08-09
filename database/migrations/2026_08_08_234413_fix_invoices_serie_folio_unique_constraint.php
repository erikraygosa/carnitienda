<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La restricción original era UNIQUE(serie, folio) sin importar el tipo
     * de comprobante. Pero invoice_series lleva un folio_actual INDEPENDIENTE
     * por tipo_comprobante (I, E, P, N), así que una factura (I) serie A
     * folio 1 y un complemento de pago (P) serie A folio 1 son documentos
     * distintos y válidos, pero la restricción actual los rechaza como
     * duplicados ("Duplicate entry 'A-1'"). Se corrige a
     * UNIQUE(serie, folio, tipo_comprobante).
     */
    protected function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();
        $res = DB::select("
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
            LIMIT 1
        ", [$db, $table, $index]);
        return !empty($res);
    }

    public function up(): void
    {
        if ($this->indexExists('invoices', 'invoices_serie_folio_unique')) {
            DB::statement('ALTER TABLE `invoices` DROP INDEX `invoices_serie_folio_unique`');
        }

        if (! $this->indexExists('invoices', 'uniq_invoices_serie_folio_tipo')) {
            DB::statement('
                ALTER TABLE `invoices`
                ADD UNIQUE KEY `uniq_invoices_serie_folio_tipo` (`serie`, `folio`, `tipo_comprobante`)
            ');
        }
    }

    public function down(): void
    {
        if ($this->indexExists('invoices', 'uniq_invoices_serie_folio_tipo')) {
            DB::statement('ALTER TABLE `invoices` DROP INDEX `uniq_invoices_serie_folio_tipo`');
        }
        if (! $this->indexExists('invoices', 'invoices_serie_folio_unique')) {
            DB::statement('ALTER TABLE `invoices` ADD UNIQUE KEY `invoices_serie_folio_unique` (`serie`, `folio`)');
        }
    }
};
