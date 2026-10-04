<?php

namespace App\Support;

/** Catálogos SAT para mostrar descripciones legibles (correos, vistas). */
class SatCatalogs
{
    public const USO_CFDI = [
        'G01' => 'Adquisición de mercancías', 'G02' => 'Devoluciones, descuentos o bonificaciones', 'G03' => 'Gastos en general',
        'I01' => 'Construcciones', 'I02' => 'Mobiliario y equipo de oficina por inversiones', 'I03' => 'Equipo de transporte',
        'I04' => 'Equipo de cómputo y accesorios', 'I05' => 'Dados, troqueles, moldes, matrices y herramental',
        'I06' => 'Comunicaciones telefónicas', 'I07' => 'Comunicaciones satelitales', 'I08' => 'Otra maquinaria y equipo',
        'D01' => 'Honorarios médicos, dentales y gastos hospitalarios', 'D02' => 'Gastos médicos por incapacidad o discapacidad',
        'D03' => 'Gastos funerales', 'D04' => 'Donativos', 'D10' => 'Pagos por servicios educativos (colegiaturas)',
        'S01' => 'Sin efectos fiscales', 'CP01' => 'Pagos', 'CN01' => 'Nómina', 'P01' => 'Por definir',
    ];

    public const FORMA_PAGO = [
        '01' => 'Efectivo', '02' => 'Cheque nominativo', '03' => 'Transferencia electrónica de fondos',
        '04' => 'Tarjeta de crédito', '05' => 'Monedero electrónico', '06' => 'Dinero electrónico',
        '08' => 'Vales de despensa', '12' => 'Dación en pago', '13' => 'Pago por subrogación', '14' => 'Pago por consignación',
        '15' => 'Condonación', '17' => 'Compensación', '23' => 'Novación', '24' => 'Confusión', '25' => 'Remisión de deuda',
        '26' => 'Prescripción o caducidad', '27' => 'A satisfacción del acreedor', '28' => 'Tarjeta de débito',
        '29' => 'Tarjeta de servicios', '30' => 'Aplicación de anticipos', '31' => 'Intermediario pagos', '99' => 'Por definir',
    ];

    public const METODO_PAGO = [
        'PUE' => 'Pago en una sola exhibición',
        'PPD' => 'Pago en parcialidades o diferido',
    ];

    public const TIPO_COMPROBANTE = [
        'I' => 'Ingreso', 'E' => 'Egreso (nota de crédito)', 'P' => 'Pago (complemento)', 'T' => 'Traslado', 'N' => 'Nómina',
    ];

    /** "G01 — Adquisición de mercancías" (o solo la clave si no está en el catálogo). */
    public static function etiqueta(array $catalogo, ?string $clave): string
    {
        if ($clave === null || $clave === '') return '—';
        return isset($catalogo[$clave]) ? "{$clave} — {$catalogo[$clave]}" : $clave;
    }
}
