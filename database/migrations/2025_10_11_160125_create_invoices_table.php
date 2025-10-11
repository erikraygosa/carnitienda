<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('serie', 10)->nullable();
            $table->unsignedBigInteger('folio')->nullable();
            $table->dateTime('fecha');
            $table->string('forma_pago', 5)->nullable();
            $table->string('metodo_pago', 5)->nullable();  // PUE | PPD
            $table->string('uso_cfdi', 5)->nullable();
            $table->string('moneda', 10)->default('MXN');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('impuestos', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('uuid', 40)->nullable()->unique();
            $table->enum('estatus', ['EMITIDA','CANCELADA','BORRADOR'])->default('BORRADOR');
            $table->string('version_cfdi', 10)->default('4.0');
            $table->json('xml_timbrado')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['serie','folio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
