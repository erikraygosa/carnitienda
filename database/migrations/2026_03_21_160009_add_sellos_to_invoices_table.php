<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'sello_cfdi')) {
                $table->text('sello_cfdi')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'sello_sat')) {
                $table->text('sello_sat')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'sello_cfdi')) {
                $table->dropColumn('sello_cfdi');
            }
            if (Schema::hasColumn('invoices', 'sello_sat')) {
                $table->dropColumn('sello_sat');
            }
        });
    }
};