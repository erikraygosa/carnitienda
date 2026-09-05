<?php

namespace App\Services;

/**
 * Convierte un monto en pesos a su representación en letras, como se usa
 * en tickets/pagarés: "CINCO MIL SEISCIENTOS VEINTIDÓS PESOS 31/100 M.N."
 * Sin dependencias externas — solo texto plano, para tickets térmicos.
 */
class NumeroALetrasService
{
    private const PALABRAS = [
        0 => 'CERO', 1 => 'UNO', 2 => 'DOS', 3 => 'TRES', 4 => 'CUATRO',
        5 => 'CINCO', 6 => 'SEIS', 7 => 'SIETE', 8 => 'OCHO', 9 => 'NUEVE',
        10 => 'DIEZ', 11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE',
        15 => 'QUINCE', 16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE',
        20 => 'VEINTE', 21 => 'VEINTIUNO', 22 => 'VEINTIDÓS', 23 => 'VEINTITRÉS', 24 => 'VEINTICUATRO',
        25 => 'VEINTICINCO', 26 => 'VEINTISÉIS', 27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE',
    ];

    private const DECENAS = [
        3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA',
        6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
        5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS',
    ];

    public static function convertir(float $numero, string $moneda = 'PESOS', string $etiquetaCentavos = 'M.N.'): string
    {
        $numero   = round(abs($numero), 2);
        $entero   = (int) floor($numero);
        $centavos = (int) round(($numero - $entero) * 100);

        $letrasEntero = $entero === 0 ? 'CERO' : self::apocoparUno(self::convertirEntero($entero));
        $sufijo       = $entero === 1 ? 'PESO' : $moneda;

        return sprintf('%s %s %02d/100 %s', $letrasEntero, $sufijo, $centavos, $etiquetaCentavos);
    }

    /** "UNO"/"VEINTIUNO"/... -> "UN"/"VEINTIÚN"/... justo antes de "PESOS". */
    private static function apocoparUno(string $texto): string
    {
        if ($texto === 'VEINTIUNO') return 'VEINTIÚN';
        if (str_ends_with($texto, ' UNO')) return substr($texto, 0, -1);
        if ($texto === 'UNO') return 'UN';
        return $texto;
    }

    private static function convertirEntero(int $n): string
    {
        if ($n < 30) return self::PALABRAS[$n];
        if ($n < 100) return self::convertirDecenas($n);
        if ($n < 1000) return self::convertirCentenas($n);
        if ($n < 1000000) return self::convertirMiles($n);
        return self::convertirMillones($n);
    }

    private static function convertirDecenas(int $n): string
    {
        $decena = intdiv($n, 10);
        $unidad = $n % 10;
        if ($unidad === 0) return self::DECENAS[$decena];
        return self::DECENAS[$decena] . ' Y ' . self::PALABRAS[$unidad];
    }

    private static function convertirCentenas(int $n): string
    {
        if ($n === 100) return 'CIEN';
        $centena = intdiv($n, 100);
        $resto   = $n % 100;
        $texto   = $centena === 1 ? 'CIENTO' : self::CENTENAS[$centena];
        return $resto === 0 ? $texto : $texto . ' ' . self::convertirEntero($resto);
    }

    private static function convertirMiles(int $n): string
    {
        $miles = intdiv($n, 1000);
        $resto = $n % 1000;
        $texto = $miles === 1 ? 'MIL' : self::convertirEntero($miles) . ' MIL';
        return $resto === 0 ? $texto : $texto . ' ' . self::convertirEntero($resto);
    }

    private static function convertirMillones(int $n): string
    {
        $millones = intdiv($n, 1000000);
        $resto    = $n % 1000000;
        $texto    = $millones === 1 ? 'UN MILLÓN' : self::convertirEntero($millones) . ' MILLONES';
        return $resto === 0 ? $texto : $texto . ' ' . self::convertirEntero($resto);
    }
}
