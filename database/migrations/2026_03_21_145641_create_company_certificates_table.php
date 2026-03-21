<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // Tipo de certificado
            $table->enum('tipo', ['csd', 'fiel']); // CSD = Sello Digital, FIEL = Firma Electrónica

            // Archivos (paths en storage/app/private/certs/{company_id}/)
            $table->string('cer_path', 500)->nullable();   // Certificado .cer
            $table->string('key_path', 500)->nullable();   // Llave privada .key

            // Contraseña cifrada con Laravel Crypt (NUNCA texto plano)
            $table->text('password_encrypted')->nullable();

            // Metadatos del certificado (extraídos del .cer)
            $table->string('numero_certificado', 40)->nullable();
            $table->string('rfc_certificado', 13)->nullable();
            $table->datetime('vigencia_inicio')->nullable();
            $table->datetime('vigencia_fin')->nullable();

            // Estado
            $table->boolean('activo')->default(true);
            $table->boolean('vigente')->default(true); // Se actualiza por job programado

            // Quién subió el certificado
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Solo un CSD activo y un FIEL activo por empresa
            $table->unique(['company_id', 'tipo', 'activo']);
            $table->index(['company_id', 'tipo']);
            $table->index('vigencia_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_certificates');
    }
};