<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('alias', 255);
            $table->timestamps();

            $table->unique(['client_id', 'alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_aliases');
    }
};
