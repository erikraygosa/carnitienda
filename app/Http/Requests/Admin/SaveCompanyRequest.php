<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

        return [
            'razon_social'     => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['nullable', 'string', 'max:300'],
            'rfc'              => [
                'required', 'string', 'max:13',
                'regex:/^([A-ZÑ&]{3,4})(\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])([A-Z\d]{2})([A\d])$/i',
                Rule::unique('companies', 'rfc')->ignore($companyId),
            ],
            'tipo_persona'     => ['required', Rule::in(['fisica', 'moral'])],
            'telefono'         => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:150'],
            'sitio_web'        => ['nullable', 'url', 'max:200'],
            'moneda'           => ['nullable', 'string', 'size:3'],
            'timezone'         => ['nullable', 'string', 'max:50'],
            'activo'           => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rfc.regex'  => 'El RFC no tiene formato válido (12 chars moral, 13 chars física).',
            'rfc.unique' => 'Este RFC ya está registrado.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->rfc) {
            $this->merge(['rfc' => strtoupper(trim($this->rfc))]);
        }
    }
}