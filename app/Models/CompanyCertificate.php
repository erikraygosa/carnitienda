<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class CompanyCertificate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'tipo',
        'cer_path',
        'key_path',
        'password_encrypted',
        'numero_certificado',
        'rfc_certificado',
        'vigencia_inicio',
        'vigencia_fin',
        'activo',
        'vigente',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'vigente'         => 'boolean',
        'vigencia_inicio' => 'datetime',
        'vigencia_fin'    => 'datetime',
        'uploaded_at'     => 'datetime',
    ];

    protected $hidden = [
        'password_encrypted',
        'cer_path',
        'key_path',
    ];

    // ------------------------------------------------------------------
    // Boot — elimina archivos físicos al borrar el registro
    // ------------------------------------------------------------------
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (CompanyCertificate $cert) {
            if ($cert->cer_path) {
                Storage::disk('local')->delete($cert->cer_path);
            }
            if ($cert->key_path) {
                Storage::disk('local')->delete($cert->key_path);
            }
        });
    }

    // ------------------------------------------------------------------
    // Relaciones
    // ------------------------------------------------------------------
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ------------------------------------------------------------------
    // Contraseña cifrada
    // ------------------------------------------------------------------
    public function setPassword(string $password): void
    {
        $this->password_encrypted = Crypt::encryptString($password);
        $this->save();
    }

    public function getPasswordDecrypted(): ?string
    {
        if (! $this->password_encrypted) {
            return null;
        }
        try {
            return Crypt::decryptString($this->password_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Lectura de metadatos del .cer
    // ------------------------------------------------------------------
    public function leerMetadatosCer(): ?array
    {
        if (! $this->cer_path) return null;

        $ruta = Storage::disk('local')->path($this->cer_path);

        if (! file_exists($ruta)) return null;

        $der  = file_get_contents($ruta);
        $pem  = "-----BEGIN CERTIFICATE-----\n"
              . chunk_split(base64_encode($der), 64, "\n")
              . "-----END CERTIFICATE-----\n";

        $cert = openssl_x509_read($pem);
        if (! $cert) return null;

        $info = openssl_x509_parse($cert);
        if (! $info) return null;

        $serial = $info['serialNumberHex'] ?? $info['serialNumber'] ?? null;

        $validFrom = isset($info['validFrom_time_t'])
            ? Carbon::createFromTimestamp($info['validFrom_time_t'])
            : null;

        $validTo = isset($info['validTo_time_t'])
            ? Carbon::createFromTimestamp($info['validTo_time_t'])
            : null;

        $rfc = null;
        if (isset($info['subject']['x500UniqueIdentifier'])) {
            $rfc = explode('/', $info['subject']['x500UniqueIdentifier'])[0];
        } elseif (isset($info['subject']['commonName'])) {
            preg_match('/^([A-ZÑ&]{3,4}\d{6}[A-Z\d]{3})/', $info['subject']['commonName'], $m);
            $rfc = $m[1] ?? null;
        }

        return [
            'numero_certificado' => $serial,
            'rfc_certificado'    => $rfc ? strtoupper(trim($rfc)) : null,
            'vigencia_inicio'    => $validFrom,
            'vigencia_fin'       => $validTo,
        ];
    }

    public function sincronizarMetadatos(): bool
    {
        $meta = $this->leerMetadatosCer();
        if (! $meta) return false;

        $this->update([
            'numero_certificado' => $meta['numero_certificado'],
            'rfc_certificado'    => $meta['rfc_certificado'],
            'vigencia_inicio'    => $meta['vigencia_inicio'],
            'vigencia_fin'       => $meta['vigencia_fin'],
            'vigente'            => $meta['vigencia_fin']?->isFuture() ?? false,
        ]);

        return true;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    public function estaVigente(): bool
    {
        return $this->vigencia_fin?->isFuture() ?? false;
    }

    public function diasParaVencer(): ?int
    {
        if (! $this->vigencia_fin) return null;
        return (int) now()->diffInDays($this->vigencia_fin, false);
    }

    public function alertaVencimiento(): ?string
    {
        $dias = $this->diasParaVencer();
        if ($dias === null)  return null;
        if ($dias < 0)       return 'vencido';
        if ($dias <= 30)     return 'critico';
        if ($dias <= 90)     return 'advertencia';
        return null;
    }

    public function getTipoLabelAttribute(): string
    {
        return $this->tipo === 'csd' ? 'Sello Digital (CSD)' : 'Firma Electrónica (FIEL)';
    }
}