<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
        $table->id();

        // Relaciones
        $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
        $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
        $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();

        // Folio y fechas
        $table->string('folio')->unique();
        $table->dateTime('fecha')->index();
        $table->date('programado_para')->nullable()->index();

        // Entrega
        $table->enum('delivery_type', ['RECOGER','ENVIO'])->default('ENVIO')->index();
        $table->string('entrega_nombre')->nullable();
        $table->string('entrega_telefono')->nullable();
        $table->string('entrega_calle')->nullable();
        $table->string('entrega_numero')->nullable();
        $table->string('entrega_colonia')->nullable();
        $table->string('entrega_ciudad')->nullable();
        $table->string('entrega_estado')->nullable();
        $table->string('entrega_cp', 10)->nullable();

        // Logística
        $table->foreignId('shipping_route_id')->nullable()->constrained('shipping_routes')->nullOnDelete();
        $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

        // Pago
        $table->enum('payment_method', ['CREDITO','TRANSFERENCIA','CONTRAENTREGA','EFECTIVO'])->default('EFECTIVO')->index();
        $table->unsignedInteger('credit_days')->nullable(); // válido sólo para CREDITO

        // Totales
        $table->string('moneda', 10)->default('MXN');
        $table->decimal('subtotal', 14, 2)->default(0);
        $table->decimal('impuestos', 14, 2)->default(0);
        $table->decimal('descuento', 14, 2)->default(0);
        $table->decimal('total', 14, 2)->default(0);

        // Estado
        $table->enum('status', [
            'BORRADOR','APROBADO','PROCESADO','DESPACHADO','ENTREGADO','CANCELADO'
        ])->default('BORRADOR')->index();

        // Auditoría
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
