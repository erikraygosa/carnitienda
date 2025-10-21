<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'cp')) {
                $table->string('cp', 10)->nullable()->after('rfc');
            }
            if (!Schema::hasColumn('clients', 'regimen_fiscal')) {
                // Catálogo SAT: 3 dígitos (ej. 601, 603...)
                $table->string('regimen_fiscal', 5)->nullable()->after('cp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'regimen_fiscal')) {
                $table->dropColumn('regimen_fiscal');
            }
            if (Schema::hasColumn('clients', 'cp')) {
                $table->dropColumn('cp');
            }
        });
    }
};
