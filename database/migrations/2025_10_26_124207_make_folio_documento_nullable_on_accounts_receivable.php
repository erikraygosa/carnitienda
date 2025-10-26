<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            // agrega columnas polimórficas nullable: source_type (string), source_id (bigint)
            $table->nullableMorphs('source'); // crea source_type y source_id
        });
    }

    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->dropMorphs('source');
        });
    }
};
