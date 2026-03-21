<?php

namespace App\Http\Requests\Admin;

use App\Models\CompanyFiscalData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyFiscalDataRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $tipo = $this->route('company')?->tipo_persona ?? 'moral';

        $regimenValidos = $tipo === 'moral'
            ? CompanyFiscalData::REGIMENES_MORAL
            : CompanyFiscalData::REGIMENES_FISICA;

        $rules = [
            'calle'               => ['required', 'string', 'max:200'],
            'numero_exterior'     => ['required', 'string', 'max:20'],
            'numero_interior'     => ['nullable', 'string', 'max:20'],
            'colonia'             => ['required', 'string', 'max:150'],
            'localidad'           => ['nullable', 'string', 'max:150'],
            'municipio'           => ['required', 'string', 'max:150'],
            'estado'              => ['required', 'string', 'max:100'],
            'codigo_postal'       => ['required', 'digits:5'],
            'pais'                => ['nullable', 'string', 'size:3'],
            'regimen_fiscal'      => ['required', Rule::in($regimenValidos)],
            'actividad_economica' => ['nullable', 'string', 'max:300'],
        ];

        if ($tipo === 'moral') {
            $rules['acta_constitutiva']  = ['nullable', 'string', 'max:100'];
            $rules['fecha_constitucion'] = ['nullable', 'date', 'before:today'];
            $rules['notario']            = ['nullable', 'string', 'max:100'];
        }

        if ($tipo === 'fisica') {
            $rules['curp']             = ['nullable', 'string', 'size:18'];
            $rules['fecha_nacimiento'] = ['nullable', 'date', 'before:today'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'codigo_postal.digits' => 'El código postal debe tener 5 dígitos.',
            'regimen_fiscal.in'    => 'El régimen fiscal no es válido para el tipo de persona.',
        ];
    }
}