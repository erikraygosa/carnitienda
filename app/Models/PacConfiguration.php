<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PacConfiguration extends Model
{
    protected $fillable = [
        'driver',
        'nombre',
        'api_key_encrypted',
        'api_secret_encrypted',
        'config_extra_encrypted',
        'ambiente',
        'activo',
        'habilitado',
        'version_cfdi',
        'notas',
    ];

    protected $casts = [
        'activo'    => 'boolean',
        'habilitado'=> 'boolean',
    ];

    protected $hidden = [
        'api_key_encrypted',
        'api_secret_encrypted',
        'config_extra_encrypted',
    ];

    // ------------------------------------------------------------------
    // Credenciales cifradas
    // ------------------------------------------------------------------
    public function setApiKey(string $value): void
    {
        $this->api_key_encrypted = Crypt::encryptString($value);
        $this->save();
    }

    public function getApiKey(): ?string
    {
        if (! $this->api_key_encrypted) return null;
        try { return Crypt::decryptString($this->api_key_encrypted); }
        catch (\Exception $e) { return null; }
    }

    public function setApiSecret(string $value): void
    {
        $this->api_secret_encrypted = Crypt::encryptString($value);
        $this->save();
    }

    public function getApiSecret(): ?string
    {
        if (! $this->api_secret_encrypted) return null;
        try { return Crypt::decryptString($this->api_secret_encrypted); }
        catch (\Exception $e) { return null; }
    }

    public function setConfigExtra(array $value): void
    {
        $this->config_extra_encrypted = Crypt::encryptString(json_encode($value));
        $this->save();
    }

    public function getConfigExtra(): array
    {
        if (! $this->config_extra_encrypted) return [];
        try { return json_decode(Crypt::decryptString($this->config_extra_encrypted), true) ?? []; }
        catch (\Exception $e) { return []; }
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------
    public function scopeActivo($query)
    {
        return $query->where('activo', true)->where('habilitado', true);
    }

    public function scopeSandbox($query)
    {
        return $query->where('ambiente', 'sandbox');
    }

    public function scopeProduccion($query)
    {
        return $query->where('ambiente', 'produccion');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    public function esSandbox(): bool
    {
        return $this->ambiente === 'sandbox';
    }

    public function getAmbienteLabelAttribute(): string
    {
        return $this->ambiente === 'sandbox' ? 'Sandbox (Pruebas)' : 'Producción';
    }

    /**
     * Activa este PAC y desactiva todos los demás
     */
    public function activar(): void
    {
        static::query()->update(['activo' => false]);
        $this->update(['activo' => true]);
    }
}