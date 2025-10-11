<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts_receivable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('tipo_doc', 5)->default('FA'); // FA factura, NC nota crédito
            $table->string('folio_documento', 30)->index();
            $table->date('fecha');
            $table->date('vencimiento')->nullable();
            $table->string('moneda', 10)->default('MXN');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('impuestos', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2)->default(0)->index();
            $table->enum('status', ['ABIERTA','PARCIAL','PAGADA','VENCIDA'])->default('ABIERTA')->index();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tipo_doc','folio_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_receivable');
    }
};
