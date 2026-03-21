<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Primero verificamos si la tabla existe, si no la creamos completa
        if (!Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('folio', 30)->unique();
                $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
                $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
                $table->date('fecha');
                $table->string('status', 20)->default('PENDIENTE');
                // PENDIENTE → ASIGNADO → EN_RUTA → COMPLETADO | CANCELADO
                $table->foreignId('dispatch_id')->nullable()->constrained('dispatches')->nullOnDelete();
                $table->text('notas')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completado_at')->nullable();
                $table->timestamps();
            });

            Schema::create('stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->decimal('qty', 12, 3);
                $table->timestamps();
            });
        } else {
            // La tabla ya existe — solo agregamos las columnas que faltan
            Schema::table('stock_transfers', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_transfers', 'folio')) {
                    $table->string('folio', 30)->nullable()->after('id');
                }
                if (!Schema::hasColumn('stock_transfers', 'status')) {
                    $table->string('status', 20)->default('PENDIENTE')->after('fecha');
                }
                if (!Schema::hasColumn('stock_transfers', 'dispatch_id')) {
                    $table->foreignId('dispatch_id')->nullable()->constrained('dispatches')->nullOnDelete()->after('status');
                }
                if (!Schema::hasColumn('stock_transfers', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('dispatch_id');
                }
                if (!Schema::hasColumn('stock_transfers', 'completado_at')) {
                    $table->timestamp('completado_at')->nullable()->after('created_by');
                }
            });
        }

        // Tabla de asignación de traspasos a despachos (si no existe)
        if (!Schema::hasTable('dispatch_transfer_assignments')) {
            Schema::create('dispatch_transfer_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
                $table->string('status', 20)->default('PENDIENTE'); // PENDIENTE | COMPLETADO | NO_COMPLETADO
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_transfer_assignments');
        // No dropeamos stock_transfers porque puede tener datos
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn(['folio','status','dispatch_id','created_by','completado_at']);
        });
    }
};