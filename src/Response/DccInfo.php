<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Response;

/**
 * Optional DCC / currency-exchange block (from a 'V' response). Named DccInfo to
 * avoid clashing with a CurrencyExchange result type.
 */
final class DccInfo
{
    public function __construct(
        public bool $applied = false,
        public string $rate = '',         // 8 digits, 4 decimals
        public string $currencyCode = '', // alpha-3
        public string $amount = '',       // 12 digits, in transaction currency
        public string $precision = '',    // decimals
    ) {}
}
