<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Mapper;

/**
 * Magento payment method code -> Quaderno payment method.
 */
class PaymentMethodMapper
{
    private const MAP = [
        'checkmo' => 'check',
        'banktransfer' => 'wire_transfer',
        'cashondelivery' => 'cash',
        'purchaseorder' => 'other',
        'free' => 'other',
    ];

    /**
     * Quaderno method for a Magento payment method code.
     *
     * @param string $code
     * @return string
     */
    public function map(string $code): string
    {
        if (isset(self::MAP[$code])) {
            return self::MAP[$code];
        }
        if (str_contains($code, 'paypal')) {
            return 'paypal';
        }
        if (str_contains($code, 'bizum') || str_contains($code, 'sepa') || str_contains($code, 'debit')) {
            return str_contains($code, 'bizum') ? 'wire_transfer' : 'direct_debit';
        }
        foreach (['card', 'cc', 'stripe', 'braintree', 'redsys', 'adyen', 'checkout'] as $needle) {
            if (str_contains($code, $needle)) {
                return 'credit_card';
            }
        }
        return 'other';
    }
}
