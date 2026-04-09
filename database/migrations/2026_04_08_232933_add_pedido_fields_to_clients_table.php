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
    Schema::table('clients', function (Blueprint $table) {
        $table->json('dias_pedido')->nullable()->after('credito_dias');
        $table->unsignedTinyInteger('pedido_periodicidad')->default(7)->after('dias_pedido');
        // 7 = semanal, puede ser 14 quincenal, etc.
    });
}

public function down(): void
{
    Schema::table('clients', function (Blueprint $table) {
        $table->dropColumn(['dias_pedido', 'pedido_periodicidad']);
    });
}
};
