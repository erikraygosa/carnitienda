<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ============================================================
// Request para crear/editar empresa
// ============================================================
class SaveCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ajusta según tu política de autorización
    }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

        return [
            // Identificación
            'razon_social'     => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['nullable', 'string', 'max:300'],
            'rfc'              => [
                'required',
                'string',
                'max:13',
                'regex:/^([A-ZÑ&]{3,4})(\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])([A-Z\d]{2})([A\d])$/i',
                Rule::unique('companies', 'rfc')->ignore($companyId),
            ],
            'tipo_persona'     => ['required', Rule::in(['fisica', 'moral'])],

            // Contacto
            'telefono'   => ['nullable', 'string', 'max:20'],
            'email'      => ['nullable', 'email', 'max:150'],
            'sitio_web'  => ['nullable', 'url', 'max:200'],

            // Config
            'moneda'   => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.regex'    => 'El RFC no tiene un formato válido. Verifica que corresponda a persona física (13 caracteres) o moral (12 caracteres).',
            'rfc.unique'   => 'Este RFC ya está registrado en el sistema.',
            'tipo_persona.required' => 'Debes indicar si es persona física o moral.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->rfc) {
            $this->merge(['rfc' => strtoupper(trim($this->rfc))]);
        }
    }
}