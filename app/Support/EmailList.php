<?php

namespace App\Support;

/** Lista de correos escrita en un solo campo: "ventas@x.com, cobranza@x.com; conta@x.com". */
class EmailList
{
    public const MAX = 10;

    /** Todas las entradas (válidas o no), sin repetir. */
    public static function split(?string $texto): array
    {
        $partes = preg_split('/[\s,;]+/', trim((string) $texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $vistos = [];
        $out = [];
        foreach ($partes as $p) {
            $k = mb_strtolower($p);
            if (isset($vistos[$k])) continue;
            $vistos[$k] = true;
            $out[] = $p;
        }
        return $out;
    }

    /** Solo los correos válidos. */
    public static function parse(?string $texto): array
    {
        return array_values(array_filter(self::split($texto), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    public static function invalid(?string $texto): array
    {
        return array_values(array_filter(self::split($texto), fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    /** Formato canónico para guardar: "a@x.com, b@x.com" (null si queda vacío). */
    public static function normalize(?string $texto): ?string
    {
        $l = self::split($texto);
        return $l ? implode(', ', $l) : null;
    }
}
