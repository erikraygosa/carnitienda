<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_ar_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->decimal('saldo_asignado', 12, 2)->default(0); // saldo al momento de asignar
            $table->decimal('monto_cobrado', 12, 2)->default(0);  // lo que realmente cobró el chofer
            $table->string('status', 20)->default('PENDIENTE');   // PENDIENTE | COBRADO | NO_COBRADO
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_ar_assignments');
    }
};