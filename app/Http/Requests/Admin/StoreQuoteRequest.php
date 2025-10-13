<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'fecha'          => ['required','date'],
            'client_id'      => ['nullable','exists:clients,id'],
            'price_list_id'  => ['nullable','exists:price_lists,id'],
            'moneda'         => ['required','string','max:10'],
            'vigencia_hasta' => ['nullable','date'],
            'items'          => ['required','array','min:1'],

            'items.*.product_id'  => ['nullable','exists:products,id'],
            'items.*.descripcion' => ['required','string','max:255'],
            'items.*.cantidad'    => ['required','numeric','min:0.001'],
            'items.*.precio'      => ['required','numeric','min:0'],
            'items.*.descuento'   => ['nullable','numeric','min:0'],
            // impuesto es importe (no %)
            'items.*.impuesto'    => ['nullable','numeric','min:0'],
        ];
    }
}
