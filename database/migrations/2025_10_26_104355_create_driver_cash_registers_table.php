<?php

// database/migrations/2025_10_26_000000_create_driver_cash_registers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('driver_cash_registers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('driver_id')->constrained('drivers');
            $t->date('fecha');                       // día del corte
            $t->decimal('saldo_inicial', 12, 2)->default(0);
            $t->decimal('saldo_cargos', 12, 2)->default(0);
            $t->decimal('saldo_abonos', 12, 2)->default(0);
            $t->decimal('saldo_final', 12, 2)->default(0);
            $t->enum('estatus', ['ABIERTO','CERRADO'])->default('ABIERTO');
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('opened_by')->nullable()->constrained('users');
            $t->foreignId('closed_by')->nullable()->constrained('users');
            $t->text('notas')->nullable();
            $t->timestamps();

            $t->unique(['driver_id','fecha']); // un corte por día/chofer
        });
    }
    public function down(): void {
        Schema::dropIfExists('driver_cash_registers');
    }
};
