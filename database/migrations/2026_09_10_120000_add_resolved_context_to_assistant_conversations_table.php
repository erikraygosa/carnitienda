<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table) {
            // Guarda los ids de cliente/producto que REALMENTE salieron de
            // una búsqueda real (buscar_cliente/buscar_producto) en esta
            // conversación — found o como candidato de un "ambiguous". Se
            // usa para rechazar un client_id/product_id que el modelo de IA
            // mande sin haberlo resuelto de verdad (alucinado), en vez de
            // confiar ciegamente en lo que diga.
            $table->json('resolved_context')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->dropColumn('resolved_context');
        });
    }
};
