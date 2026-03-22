<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();   // Ej: app.nombre, app.logo_path
            $table->text('valor')->nullable();
            $table->string('tipo', 20)->default('string'); // string, boolean, integer, json, file
            $table->string('grupo', 50)->default('general'); // general, facturacion, correo, etc.
            $table->string('descripcion', 300)->nullable();
            $table->boolean('es_publica')->default(false);  // Si puede leerse sin autenticación
            $table->timestamps();

            $table->index('grupo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};