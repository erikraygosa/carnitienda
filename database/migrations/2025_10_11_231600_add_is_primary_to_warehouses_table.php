<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('activo');
            $table->index('is_primary');
        });

        // Marcar uno como principal si no hay ninguno
        if (!DB::table('warehouses')->where('is_primary', 1)->exists()) {
            // Preferir uno ACTIVO; si no hay, el primero que exista
            $id = DB::table('warehouses')->where('activo', 1)->orderBy('id')->value('id')
               ?: DB::table('warehouses')->orderBy('id')->value('id');

            if ($id) {
                DB::table('warehouses')->where('id', $id)->update(['is_primary' => 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndex(['is_primary']);
            $table->dropColumn('is_primary');
        });
    }
};
