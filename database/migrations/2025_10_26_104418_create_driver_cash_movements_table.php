<?php

// database/migrations/2025_10_26_000001_create_driver_cash_movements_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('driver_cash_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('register_id')->constrained('driver_cash_registers')->cascadeOnDelete();
            $t->foreignId('driver_id')->constrained('drivers');
            $t->enum('tipo', ['CARGO','ABONO','AJUSTE']);
            $t->decimal('monto', 12, 2);
            $t->string('descripcion')->nullable();

            // referencia (ej. orden/despacho/pago)
            $t->nullableMorphs('source'); // source_type, source_id

            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();

            $t->index(['driver_id','tipo']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('driver_cash_movements');
    }
};
