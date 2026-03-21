<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo'     => ['required', Rule::in(['csd', 'fiel'])],
            'password' => ['required', 'string', 'min:4', 'max:100'],

            // Archivos: máximo 1 MB cada uno, extensiones estrictas
            'archivo_cer' => [
                'required',
                'file',
                'mimes:cer',
                'max:1024', // 1 MB
                function ($attribute, $value, $fail) {
                    // Verificación adicional: el mime puede no detectar .cer correctamente
                    // Verificamos la extensión manualmente
                    if (strtolower($value->getClientOriginalExtension()) !== 'cer') {
                        $fail('El certificado debe ser un archivo .cer');
                    }
                },
            ],
            'archivo_key' => [
                'required',
                'file',
                'max:1024',
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
            'tipo.required'        => 'Debes indicar si es CSD o FIEL.',
            'password.required'    => 'La contraseña del certificado es obligatoria.',
            'archivo_cer.required' => 'Debes subir el archivo .cer del certificado.',
            'archivo_key.required' => 'Debes subir el archivo .key de la llave privada.',
            'archivo_cer.max'      => 'El archivo .cer no puede superar 1 MB.',
            'archivo_key.max'      => 'El archivo .key no puede superar 1 MB.',
        ];
    }
}