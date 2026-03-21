<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyCertificateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tipo'     => ['required', Rule::in(['csd', 'fiel'])],
            'password' => ['required', 'string', 'min:4', 'max:100'],
            'archivo_cer' => [
                'required', 'file', 'max:1024',
                function ($attribute, $value, $fail) {
                    if (strtolower($value->getClientOriginalExtension()) !== 'cer') {
                        $fail('El certificado debe ser un archivo .cer');
                    }
                },
            ],
            'archivo_key' => [
                'required', 'file', 'max:1024',
                function ($attribute, $value, $fail) {
                    if (strtolower($value->getClientOriginalExtension()) !== 'key') {
                        $fail('La llave privada debe ser un archivo .key');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo_cer.required' => 'Debes subir el archivo .cer',
            'archivo_key.required' => 'Debes subir el archivo .key',
            'password.required'    => 'La contraseña del certificado es obligatoria.',
        ];
    }
}