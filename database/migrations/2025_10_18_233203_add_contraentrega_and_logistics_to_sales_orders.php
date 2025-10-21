<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // ====== Timestamps logísticos (si no existen) ======
            if (!Schema::hasColumn('sales_orders', 'preparado_at'))     $table->timestamp('preparado_at')->nullable()->after('status');
            if (!Schema::hasColumn('sales_orders', 'despachado_at'))    $table->timestamp('despachado_at')->nullable()->after('preparado_at');
            if (!Schema::hasColumn('sales_orders', 'en_ruta_at'))       $table->timestamp('en_ruta_at')->nullable()->after('despachado_at');
            if (!Schema::hasColumn('sales_orders', 'entregado_at'))     $table->timestamp('entregado_at')->nullable()->after('en_ruta_at');
            if (!Schema::hasColumn('sales_orders', 'no_entregado_at'))  $table->timestamp('no_entregado_at')->nullable()->after('entregado_at');

            // ====== Métricas de entrega ======
            if (!Schema::hasColumn('sales_orders', 'delivery_attempts')) $table->unsignedTinyInteger('delivery_attempts')->default(0)->after('no_entregado_at');
            if (!Schema::hasColumn('sales_orders', 'delivery_notes'))    $table->text('delivery_notes')->nullable()->after('delivery_attempts');

            // ====== Cobranza chofer (contraentrega) ======
            if (!Schema::hasColumn('sales_orders', 'contraentrega_total')) $table->decimal('contraentrega_total', 14, 2)->default(0)->after('total');
            if (!Schema::hasColumn('sales_orders', 'cobrado_efectivo'))    $table->decimal('cobrado_efectivo', 14, 2)->default(0)->after('contraentrega_total');
            if (!Schema::hasColumn('sales_orders', 'cobrado_confirmado_at')) $table->timestamp('cobrado_confirmado_at')->nullable()->after('cobrado_efectivo');
            if (!Schema::hasColumn('sales_orders', 'cobrado_confirmado_por')) {
                $table->foreignId('cobrado_confirmado_por')->nullable()->constrained('users')->nullOnDelete()->after('cobrado_confirmado_at');
            }

            // ====== Liquidación de chofer ======
            if (!Schema::hasColumn('sales_orders', 'driver_settlement_status')) {
                $table->enum('driver_settlement_status', ['PENDIENTE','PARCIAL','LIQUIDADO'])
                      ->default('PENDIENTE')->index()->after('cobrado_confirmado_por');
            }
            if (!Schema::hasColumn('sales_orders', 'driver_settlement_at')) $table->timestamp('driver_settlement_at')->nullable()->after('driver_settlement_status');

            // (Opcional) Vincular a caja/turno POS
            if (!Schema::hasColumn('sales_orders', 'pos_register_id')) {
                $table->foreignId('pos_register_id')->nullable()->constrained('pos_registers')->nullOnDelete()->after('driver_settlement_at');
            }

            // Índice útil si existen las columnas base
            if (Schema::hasColumn('sales_orders', 'driver_id') && Schema::hasColumn('sales_orders', 'programado_para')) {
                $table->index(['driver_id','programado_para','status'], 'so_driver_programado_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // Quitar índices si existen
            try { $table->dropIndex('so_driver_programado_status_idx'); } catch (\Throwable $e) {}

            // Quitar FKs primero
            if (Schema::hasColumn('sales_orders', 'cobrado_confirmado_por')) {
                $table->dropConstrainedForeignId('cobrado_confirmado_por');
            }
            if (Schema::hasColumn('sales_orders', 'pos_register_id')) {
                $table->dropConstrainedForeignId('pos_register_id');
            }

            // Luego columnas
            foreach ([
                'preparado_at','despachado_at','en_ruta_at','entregado_at','no_entregado_at',
                'delivery_attempts','delivery_notes',
                'contraentrega_total','cobrado_efectivo','cobrado_confirmado_at',
                'driver_settlement_status','driver_settlement_at','pos_register_id',
            ] as $col) {
                if (Schema::hasColumn('sales_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
