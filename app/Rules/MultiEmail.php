<?php

namespace App\Rules;

use App\Support\EmailList;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Uno o varios correos separados por coma, punto y coma o espacio. */
class MultiEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') return;

        $malos = EmailList::invalid((string) $value);
        if ($malos) {
            $fail('Correo(s) no válido(s): ' . implode(', ', $malos) . '.');
            return;
        }
        if (count(EmailList::split((string) $value)) > EmailList::MAX) {
            $fail('Máximo ' . EmailList::MAX . ' correos.');
        }
    }
}
