<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `sales` MODIFY COLUMN `status` ENUM('ABIERTA','CERRADA','ENTREGADA','FACTURADA','CANCELADA') NOT NULL DEFAULT 'ABIERTA'");

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales','created_by')) {
                $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('sales','owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `sales` MODIFY COLUMN `status` ENUM('ABIERTA','CERRADA','CANCELADA') NOT NULL DEFAULT 'ABIERTA'");

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales','owner_id')) $table->dropConstrainedForeignId('owner_id');
            if (Schema::hasColumn('sales','created_by')) $table->dropConstrainedForeignId('created_by');
        });
    }
};
