<?php

namespace App\Http\Requests;

use App\Models\CompanyFiscalData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyFiscalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipoPer = $this->route('company')?->tipo_persona ?? 'moral';

        $regimenValidos = $tipoPer === 'moral'
            ? CompanyFiscalData::REGIMENES_MORAL
            : CompanyFiscalData::REGIMENES_FISICA;

        $rules = [
            // Dirección fiscal
            'calle'            => ['required', 'string', 'max:200'],
            'numero_exterior'  => ['required', 'string', 'max:20'],
            'numero_interior'  => ['nullable', 'string', 'max:20'],
            'colonia'          => ['required', 'string', 'max:150'],
            'localidad'        => ['nullable', 'string', 'max:150'],
            'municipio'        => ['required', 'string', 'max:150'],
            'estado'           => ['required', 'string', 'max:100'],
            'codigo_postal'    => ['required', 'digits:5'],
            'pais'             => ['nullable', 'string', 'size:3'],

            // Régimen
            'regimen_fiscal'   => ['required', Rule::in($regimenValidos)],

            // Opcional
            'actividad_economica' => ['nullable', 'string', 'max:300'],
        ];

        // Campos persona moral
        if ($tipoPer === 'moral') {
            $rules['acta_constitutiva']  = ['nullable', 'string', 'max:100'];
            $rules['fecha_constitucion'] = ['nullable', 'date', 'before:today'];
            $rules['notario']            = ['nullable', 'string', 'max:100'];
        }

        // Campos persona física
        if ($tipoPer === 'fisica') {
            $rules['curp']             = ['nullable', 'string', 'size:18', 'regex:/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z\d]\d$/'];
            $rules['fecha_nacimiento'] = ['nullable', 'date', 'before:today'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'codigo_postal.digits'   => 'El código postal debe tener exactamente 5 dígitos.',
            'regimen_fiscal.in'      => 'El régimen fiscal seleccionado no es válido para el tipo de persona.',
            'curp.regex'             => 'El formato del CURP no es válido.',
            'curp.size'              => 'El CURP debe tener exactamente 18 caracteres.',
            'fecha_constitucion.before' => 'La fecha de constitución no puede ser futura.',
            'fecha_nacimiento.before'   => 'La fecha de nacimiento no puede ser futura.',
        ];
    }
}