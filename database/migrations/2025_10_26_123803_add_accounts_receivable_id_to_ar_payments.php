<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('ar_payments', 'accounts_receivable_id')) {
            Schema::table('ar_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('accounts_receivable_id')->nullable()->after('id');
                // Si quieres FK y aún no existe, puedes activar esto:
                // $table->foreign('accounts_receivable_id')
                //       ->references('id')->on('accounts_receivable')
                //       ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ar_payments', 'accounts_receivable_id')) {
            Schema::table('ar_payments', function (Blueprint $table) {
                // Si creaste FK arriba, primero dropea la llave:
                // $table->dropForeign(['accounts_receivable_id']);
                $table->dropColumn('accounts_receivable_id');
            });
        }
    }
};
