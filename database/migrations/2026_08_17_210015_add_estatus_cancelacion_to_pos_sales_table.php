<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->string('estatus', 20)->default('COMPLETADA')->after('total');
            $table->timestamp('cancelado_at')->nullable()->after('estatus');
            $table->foreignId('cancelado_por')->nullable()->after('cancelado_at')
                ->constrained('users')->nullOnDelete();
            $table->string('motivo_cancelacion', 255)->nullable()->after('cancelado_por');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelado_por');
            $table->dropColumn(['estatus', 'cancelado_at', 'motivo_cancelacion']);
        });
    }
};
