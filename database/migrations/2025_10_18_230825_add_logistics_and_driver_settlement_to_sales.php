<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $hasStatus   = Schema::hasColumn('sales', 'status');
        $hasDriverId = Schema::hasColumn('sales', 'driver_id');

        Schema::table('sales', function (Blueprint $table) use ($hasStatus, $hasDriverId) {
            // Si tu tabla sales NO tiene status, créalo (ajusta según tus necesidades)
            if (!$hasStatus) {
                $table->enum('status', [
                    'BORRADOR','APROBADO','PREPARANDO','PROCESADO',
                    'EN_RUTA','ENTREGADO','NO_ENTREGADO','CANCELADO'
                ])->default('BORRADOR')->index()->after('tipo_venta');
            }

            // Timestamps logísticos
            if (!Schema::hasColumn('sales', 'preparado_at'))     $table->timestamp('preparado_at')->nullable()->after('status');
            if (!Schema::hasColumn('sales', 'despachado_at'))    $table->timestamp('despachado_at')->nullable()->after('preparado_at');
            if (!Schema::hasColumn('sales', 'en_ruta_at'))       $table->timestamp('en_ruta_at')->nullable()->after('despachado_at');
            if (!Schema::hasColumn('sales', 'entregado_at'))     $table->timestamp('entregado_at')->nullable()->after('en_ruta_at');
            if (!Schema::hasColumn('sales', 'no_entregado_at'))  $table->timestamp('no_entregado_at')->nullable()->after('entregado_at');

            // Métricas de entrega
            if (!Schema::hasColumn('sales', 'delivery_attempts')) $table->unsignedTinyInteger('delivery_attempts')->default(0)->after('no_entregado_at');
            if (!Schema::hasColumn('sales', 'delivery_notes'))    $table->text('delivery_notes')->nullable()->after('delivery_attempts');

            // Cobranza chofer / contraentrega
            if (!Schema::hasColumn('sales', 'contraentrega_total'))  $table->decimal('contraentrega_total', 14, 2)->default(0)->after('total');
            if (!Schema::hasColumn('sales', 'cobrado_efectivo'))     $table->decimal('cobrado_efectivo', 14, 2)->default(0)->after('contraentrega_total');
            if (!Schema::hasColumn('sales', 'cobrado_confirmado_at'))$table->timestamp('cobrado_confirmado_at')->nullable()->after('cobrado_efectivo');
            if (!Schema::hasColumn('sales', 'cobrado_confirmado_por')) {
                $table->foreignId('cobrado_confirmado_por')->nullable()->constrained('users')->nullOnDelete()->after('cobrado_confirmado_at');
            }

            // Liquidación de chofer
            if (!Schema::hasColumn('sales', 'driver_settlement_status')) {
                $table->enum('driver_settlement_status', ['PENDIENTE','PARCIAL','LIQUIDADO'])->default('PENDIENTE')->index()->after('cobrado_confirmado_por');
            }
            if (!Schema::hasColumn('sales', 'driver_settlement_at')) $table->timestamp('driver_settlement_at')->nullable()->after('driver_settlement_status');

            // POS register (opcional)
            if (!Schema::hasColumn('sales', 'pos_register_id')) {
                $table->foreignId('pos_register_id')->nullable()->constrained('pos_registers')->nullOnDelete()->after('driver_settlement_at');
            }

            // Índices útiles
            if ($hasDriverId) {
                $table->index(['driver_id','status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // FKs primero
            if (Schema::hasColumn('sales','cobrado_confirmado_por')) $table->dropConstrainedForeignId('cobrado_confirmado_por');
            if (Schema::hasColumn('sales','pos_register_id'))        $table->dropConstrainedForeignId('pos_register_id');

            foreach ([
                'preparado_at','despachado_at','en_ruta_at','entregado_at','no_entregado_at',
                'delivery_attempts','delivery_notes',
                'contraentrega_total','cobrado_efectivo','cobrado_confirmado_at',
                'driver_settlement_status','driver_settlement_at','pos_register_id',
            ] as $col) {
                if (Schema::hasColumn('sales', $col)) $table->dropColumn($col);
            }

            // OJO: sólo elimina status si lo agregamos en esta migración (dejarlo si ya existía antes)
            // $table->dropColumn('status'); // <- comenta o elimina si tu tabla ya tenía status
        });
    }
};
