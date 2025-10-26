<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable();           // opcional
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipping_route_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle')->nullable();         // placa/alias si aún no tienes tabla vehicles
            $table->dateTime('fecha')->nullable();
            $table->string('status', 20)->default('PLANEADO'); // PLANEADO, PREPARANDO, CARGADO, EN_RUTA, ENTREGADO, CERRADO, CANCELADO
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('dispatches');
    }
};
