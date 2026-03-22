<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\PacConfiguration;
use App\Models\StampCounter;
use App\Services\Pac\FactuapiDriver;
use App\Services\Pac\GenericPacDriver;
use App\Services\Pac\PacDriverInterface;
use Illuminate\Support\Facades\Log;

class PacCfdiService
{
    // ------------------------------------------------------------------
    // Resolver el driver activo
    // ------------------------------------------------------------------
    public function driver(?string $forzarDriver = null): PacDriverInterface
    {
        $config = $forzarDriver
            ? PacConfiguration::where('driver', $forzarDriver)->where('habilitado', true)->firstOrFail()
            : PacConfiguration::activo()->firstOrFail();

        return match($config->driver) {
            'factuapi' => new FactuapiDriver($config),
            'generic'  => new GenericPacDriver($config),
            default    => throw new \RuntimeException("Driver PAC '{$config->driver}' no reconocido."),
        };
    }

    // ------------------------------------------------------------------
    // buildXml — delega al driver activo
    // ------------------------------------------------------------------
    public function buildXml(Invoice $invoice, Company $company, ?string $driver = null): string
    {
        return $this->driver($driver)->buildXml($invoice, $company);
    }

    // ------------------------------------------------------------------
    // Timbrar con fallback automático
    // ------------------------------------------------------------------
    public function stamp(Invoice $invoice, string $xml, Company $company): array
    {
        // 1. Verificar timbres disponibles
        $counter = StampCounter::activoParaEmpresa($company->id);

        if ($counter && ! $counter->tieneTimbres()) {
            return [
                'ok'    => false,
                'error' => 'Sin timbres disponibles. Contacta al administrador del sistema.',
            ];
        }

        // 2. Intentar con el PAC activo
        $driver  = $this->driver();
        $result  = $driver->stamp($invoice, $xml, $company);

        // 3. Si falla, intentar con el PAC alternativo (fallback)
        if (! ($result['ok'] ?? false)) {
            Log::warning('PAC principal falló, intentando PAC alternativo', [
                'invoice_id' => $invoice->id,
                'driver'     => $driver->nombre(),
                'error'      => $result['error'] ?? '',
            ]);

            try {
                $altConfig = PacConfiguration::where('activo', false)
                    ->where('habilitado', true)
                    ->first();

                if ($altConfig) {
                    $altDriver = match($altConfig->driver) {
                        'factuapi' => new FactuapiDriver($altConfig),
                        'generic'  => new GenericPacDriver($altConfig),
                        default    => null,
                    };

                    if ($altDriver) {
                        $result = $altDriver->stamp($invoice, $xml, $company);

                        if ($result['ok'] ?? false) {
                            Log::info('Timbrado exitoso con PAC alternativo', [
                                'invoice_id' => $invoice->id,
                                'driver'     => $altDriver->nombre(),
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('PAC alternativo también falló', ['error' => $e->getMessage()]);
            }
        }

        // 4. Si fue exitoso, actualizar contadores y factura
        if ($result['ok'] ?? false) {
            $invoice->update([
                'uuid'                    => $result['uuid'],
                'xml_timbrado'            => $result['xml_timbrado'],
                'numero_certificado_sat'  => $result['numero_certificado_sat'] ?? null,
                'sello_cfdi'              => $result['sello_cfdi'] ?? null,
                'sello_sat'               => $result['sello_sat'] ?? null,
                'estatus'                 => 'TIMBRADA',
            ]);

            // Incrementar contador de timbres
            $counter?->registrarTimbre();
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Cancelar
    // ------------------------------------------------------------------
    public function cancel(Invoice $invoice, string $motivo, ?string $folioSustitucion = null): array
    {
        $driver = $this->driver();
        $result = $driver->cancel($invoice, $motivo, $folioSustitucion);

        if ($result['ok'] ?? false) {
            $invoice->update(['estatus' => 'CANCELADA']);

            // Registrar cancelación en contador
            $company = $invoice->company
                ?? app(CompanyService::class)->activa();

            if ($company) {
                $counter = StampCounter::activoParaEmpresa($company->id);
                $counter?->registrarCancelacion();
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Estado en SAT
    // ------------------------------------------------------------------
    public function status(Invoice $invoice): array
    {
        return $this->driver()->status($invoice);
    }

    // ------------------------------------------------------------------
    // Info del PAC activo (para el dashboard superadmin)
    // ------------------------------------------------------------------
    public function pacActivo(): ?PacConfiguration
    {
        return PacConfiguration::activo()->first();
    }

    public function pacesDisponibles(): \Illuminate\Database\Eloquent\Collection
    {
        return PacConfiguration::where('habilitado', true)->get();
    }
}