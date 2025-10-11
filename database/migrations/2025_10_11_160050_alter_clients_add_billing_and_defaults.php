<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('tipo_persona', 2)->default('PF'); // PF | PM
            $table->string('rfc', 13)->nullable()->index();
            $table->string('razon_social', 180)->nullable();
            $table->string('nombre_comercial', 180)->nullable();
            $table->string('regimen_fiscal', 5)->nullable();
            $table->string('uso_cfdi_default', 5)->nullable();
            $table->foreignId('shipping_route_id')->nullable()->constrained('shipping_routes')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('payment_type_id')->nullable()->constrained('payment_types')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('credito_limite', 14, 2)->default(0);
            $table->unsignedSmallInteger('credito_dias')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['tipo_persona','rfc','razon_social','nombre_comercial','regimen_fiscal','uso_cfdi_default','credito_limite','credito_dias']);
            $table->dropConstrainedForeignId('shipping_route_id');
            $table->dropConstrainedForeignId('payment_type_id');
            $table->dropConstrainedForeignId('price_list_id');
        });
    }
};
