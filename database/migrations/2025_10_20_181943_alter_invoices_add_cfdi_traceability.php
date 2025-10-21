<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // --- Helpers para revisar índices sin Doctrine/DBAL ---
    protected function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();
        $res = DB::select("
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
            LIMIT 1
        ", [$db, $table, $index]);
        return !empty($res);
    }

    public function up(): void
    {
        // 1) Columnas y FKs (solo si faltan)
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'sales_order_id')) {
                $table->unsignedBigInteger('sales_order_id')->nullable()->after('client_id');
                $table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete();
            }

            if (!Schema::hasColumn('invoices', 'sale_id')) {
                $table->unsignedBigInteger('sale_id')->nullable()->after('sales_order_id');
                $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            }

            if (!Schema::hasColumn('invoices', 'tipo_comprobante')) {
                $table->enum('tipo_comprobante', ['I','E','P','N'])->default('I')->after('sale_id');
            }
            if (!Schema::hasColumn('invoices', 'lugar_expedicion')) {
                $table->string('lugar_expedicion', 10)->nullable()->after('tipo_comprobante');
            }

            // exportacion: si existe mal (varchar(1)), lo corregimos abajo con ALTER
            if (!Schema::hasColumn('invoices', 'exportacion')) {
                $table->char('exportacion', 2)->default('01')->after('lugar_expedicion');
            }

            if (!Schema::hasColumn('invoices', 'regimen_fiscal_emisor')) {
                $table->string('regimen_fiscal_emisor', 3)->nullable()->after('exportacion');
            }
            if (!Schema::hasColumn('invoices', 'regimen_fiscal_receptor')) {
                $table->string('regimen_fiscal_receptor', 3)->nullable()->after('regimen_fiscal_emisor');
            }
            if (!Schema::hasColumn('invoices', 'receptor_rfc')) {
                $table->string('receptor_rfc', 13)->nullable()->after('regimen_fiscal_receptor');
            }
            if (!Schema::hasColumn('invoices', 'receptor_razon_social')) {
                $table->string('receptor_razon_social')->nullable()->after('receptor_rfc');
            }
            if (!Schema::hasColumn('invoices', 'receptor_cp')) {
                $table->string('receptor_cp', 10)->nullable()->after('receptor_razon_social');
            }

            if (!Schema::hasColumn('invoices', 'condiciones_pago')) {
                $table->string('condiciones_pago')->nullable()->after('uso_cfdi');
            }
            if (!Schema::hasColumn('invoices', 'cuenta')) {
                $table->string('cuenta')->nullable()->after('condiciones_pago');
            }

            // Catálogos SAT quizá ya existan en tu tabla original; si no, los agregamos
            if (!Schema::hasColumn('invoices', 'forma_pago')) {
                $table->string('forma_pago', 3)->nullable()->after('fecha');
            }
            if (!Schema::hasColumn('invoices', 'metodo_pago')) {
                $table->string('metodo_pago', 3)->nullable()->after('forma_pago');
            }
            if (!Schema::hasColumn('invoices', 'uso_cfdi')) {
                $table->string('uso_cfdi', 5)->nullable()->after('metodo_pago');
            }
        });

        // 2) Normaliza exportacion si quedó como VARCHAR(1)
        //    (esto evita el error "Invalid default value for 'exportacion'")
        DB::statement("
            ALTER TABLE `invoices`
            MODIFY `exportacion` CHAR(2) NOT NULL DEFAULT '01'
        ");

        // 3) Normaliza estatus a ENUM deseado (no usa Doctrine)
        if (Schema::hasColumn('invoices', 'estatus')) {
            DB::statement("
                ALTER TABLE `invoices`
                MODIFY `estatus` ENUM('BORRADOR','TIMBRADA','ENVIADA','CANCELADA')
                NOT NULL DEFAULT 'BORRADOR'
            ");
        }

        // 4) Índices (solo si faltan)
        if (!$this->indexExists('invoices', 'uniq_invoice_sales_order')) {
            DB::statement("ALTER TABLE `invoices` ADD UNIQUE KEY `uniq_invoice_sales_order` (`sales_order_id`)");
        }
        if (!$this->indexExists('invoices', 'uniq_invoice_sale')) {
            DB::statement("ALTER TABLE `invoices` ADD UNIQUE KEY `uniq_invoice_sale` (`sale_id`)");
        }
        if (!$this->indexExists('invoices', 'idx_invoices_client')) {
            DB::statement("ALTER TABLE `invoices` ADD INDEX `idx_invoices_client` (`client_id`)");
        }
        if (!$this->indexExists('invoices', 'idx_invoices_uuid')) {
            DB::statement("ALTER TABLE `invoices` ADD INDEX `idx_invoices_uuid` (`uuid`)");
        }
        if (!$this->indexExists('invoices', 'idx_invoices_serie_folio')) {
            DB::statement("ALTER TABLE `invoices` ADD INDEX `idx_invoices_serie_folio` (`serie`,`folio`)");
        }
    }

    public function down(): void
    {
        // Quita índices si existen
        foreach (['uniq_invoice_sales_order','uniq_invoice_sale','idx_invoices_client','idx_invoices_uuid','idx_invoices_serie_folio'] as $idx) {
            if ($this->indexExists('invoices', $idx)) {
                DB::statement("ALTER TABLE `invoices` DROP INDEX `$idx`");
            }
        }

        Schema::table('invoices', function (Blueprint $table) {
            // FKs + columnas (solo si existen)
            if (Schema::hasColumn('invoices', 'sales_order_id')) {
                try { $table->dropForeign(['sales_order_id']); } catch (\Throwable $e) {}
                $table->dropColumn('sales_order_id');
            }
            if (Schema::hasColumn('invoices', 'sale_id')) {
                try { $table->dropForeign(['sale_id']); } catch (\Throwable $e) {}
                $table->dropColumn('sale_id');
            }

            foreach ([
                'tipo_comprobante','lugar_expedicion','exportacion',
                'regimen_fiscal_emisor','regimen_fiscal_receptor',
                'receptor_rfc','receptor_razon_social','receptor_cp',
                'condiciones_pago','cuenta'
            ] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
            // Ojo: forma_pago, metodo_pago, uso_cfdi quizá ya existían en tu diseño; si no quieres borrarlos, comenta estas líneas:
            // foreach (['forma_pago','metodo_pago','uso_cfdi'] as $col) {
            //     if (Schema::hasColumn('invoices', $col)) $table->dropColumn($col);
            // }
        });

        // Revertir ENUM si quieres (opcional)
        // DB::statement("ALTER TABLE `invoices` MODIFY `estatus` VARCHAR(50) NULL");
    }
};
