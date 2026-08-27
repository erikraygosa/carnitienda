<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('origen', 30)->nullable()->after('status'); // app | chat_asistente
            $table->foreignId('assistant_conversation_id')->nullable()
                ->after('origen')
                ->constrained('assistant_conversations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assistant_conversation_id');
            $table->dropColumn('origen');
        });
    }
};
