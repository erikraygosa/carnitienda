<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Un usuario puede ser admin de esa empresa o solo miembro
            $table->boolean('es_admin')->default(false);

            // Empresa activa actualmente para este usuario (sesión)
            $table->boolean('es_empresa_activa')->default(false);

            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};