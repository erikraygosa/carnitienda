<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFiscalData extends Model
{
    protected $table = 'company_fiscal_data';

    protected $fillable = [
        'company_id',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'localidad',
        'municipio',
        'estado',
        'codigo_postal',
        'pais',
        'regimen_fiscal',
        'regimen_fiscal_descripcion',
        'actividad_economica',
        'acta_constitutiva',
        'fecha_constitucion',
        'notario',
        'curp',
        'fecha_nacimiento',
    ];

    protected $casts = [
        'fecha_constitucion' => 'date',
        'fecha_nacimiento'   => 'date',
    ];

    // ------------------------------------------------------------------
    // Catálogo SAT c_RegimenFiscal vigente
    // ------------------------------------------------------------------
    public const REGIMENES_FISCALES = [
        '601' => 'General de Ley Personas Morales',
        '603' => 'Personas Morales con Fines no Lucrativos',
        '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
        '606' => 'Arrendamiento',
        '607' => 'Régimen de Enajenación o Adquisición de Bienes',
        '608' => 'Demás ingresos',
        '610' => 'Residentes en el Extranjero sin Establecimiento Permanente en México',
        '611' => 'Ingresos por Dividendos (socios y accionistas)',
        '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
        '614' => 'Ingresos por intereses',
        '615' => 'Régimen de los ingresos por obtención de premios',
        '616' => 'Sin obligaciones fiscales',
        '620' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
        '621' => 'Incorporación Fiscal',
        '622' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
        '623' => 'Opcional para Grupos de Sociedades',
        '624' => 'Coordinados',
        '625' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
        '626' => 'Régimen Simplificado de Confianza',
    ];

    public const REGIMENES_MORAL  = ['601', '603', '620', '622', '623', '624', '625', '626'];
    public const REGIMENES_FISICA = ['605', '606', '607', '608', '610', '611', '612', '614', '615', '616', '621', '622', '625', '626'];

    // ------------------------------------------------------------------
    // Relaciones
    // ------------------------------------------------------------------
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    public function getDireccionCompletaAttribute(): string
    {
        $partes = array_filter([
            $this->calle . ($this->numero_exterior ? ' ' . $this->numero_exterior : ''),
            $this->numero_interior ? 'Int. ' . $this->numero_interior : null,
            $this->colonia,
            'C.P. ' . $this->codigo_postal,
            $this->municipio,
            $this->estado,
        ]);

        return implode(', ', $partes);
    }

    public function getDescripcionRegimenAttribute(): string
    {
        return self::REGIMENES_FISCALES[$this->regimen_fiscal]
            ?? $this->regimen_fiscal_descripcion
            ?? $this->regimen_fiscal;
    }

    public static function regimenesParaTipo(string $tipo_persona): array
    {
        $claves = $tipo_persona === 'moral'
            ? self::REGIMENES_MORAL
            : self::REGIMENES_FISICA;

        return collect(self::REGIMENES_FISCALES)
            ->only($claves)
            ->toArray();
    }
}