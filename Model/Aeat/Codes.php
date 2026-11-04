<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Aeat;

/**
 * AEAT Verifactu code lists L2 and L3, labels verbatim from DsRegistroVeriFactu v1.0.
 * The provider builds the records; the module never sends these codes itself.
 */
class Codes
{
    /** TipoFactura (list L2) */
    public const TIPO_FACTURA_F1 = 'F1';
    public const TIPO_FACTURA_F2 = 'F2';
    public const TIPO_FACTURA_F3 = 'F3';
    public const TIPO_FACTURA_R1 = 'R1';
    public const TIPO_FACTURA_R2 = 'R2';
    public const TIPO_FACTURA_R3 = 'R3';
    public const TIPO_FACTURA_R4 = 'R4';
    public const TIPO_FACTURA_R5 = 'R5';

    /** TipoRectificativa (list L3) */
    public const TIPO_RECTIFICATIVA_SUSTITUCION = 'S';
    public const TIPO_RECTIFICATIVA_DIFERENCIAS = 'I';

    /** EstadoRegistro values seen in provider responses */
    public const ESTADO_CORRECTO = 'Correcto';
    public const ESTADO_ACEPTADO_CON_ERRORES = 'AceptadoConErrores';
    public const ESTADO_INCORRECTO = 'Incorrecto';

    /** TipoFactura labels (list L2) */
    public const TIPO_FACTURA_LABELS = [
        self::TIPO_FACTURA_F1 => 'Factura (art. 6, 7.2 y 7.3 del RD 1619/2012)',
        self::TIPO_FACTURA_F2 => 'Factura Simplificada y Facturas sin identificación del destinatario'
            . ' art. 6.1.d) RD 1619/2012',
        self::TIPO_FACTURA_F3 => 'Factura emitida en sustitución de facturas simplificadas facturadas'
            . ' y declaradas',
        self::TIPO_FACTURA_R1 => 'Factura Rectificativa (Error fundado en derecho y Art. 80 Uno Dos y Seis LIVA)',
        self::TIPO_FACTURA_R2 => 'Factura Rectificativa (Art. 80.3)',
        self::TIPO_FACTURA_R3 => 'Factura Rectificativa (Art. 80.4)',
        self::TIPO_FACTURA_R4 => 'Factura Rectificativa (Resto)',
        self::TIPO_FACTURA_R5 => 'Factura Rectificativa en facturas simplificadas',
    ];

    /** TipoRectificativa labels (list L3) */
    public const TIPO_RECTIFICATIVA_LABELS = [
        self::TIPO_RECTIFICATIVA_SUSTITUCION => 'Por sustitución',
        self::TIPO_RECTIFICATIVA_DIFERENCIAS => 'Por diferencias',
    ];
}
