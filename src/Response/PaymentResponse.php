<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Response;

/**
 * Payment-family response ('E' plain, 'V' with DCC). Reused for reversal / card
 * verification / pre-auth closure which share the layout.
 */
final class PaymentResponse
{
    public function __construct(
        public Outcome $outcome = Outcome::Unknown,
        public string $resultCode = '',       // raw "00"/"01"/"05"/"09"
        public string $pan = '',              // positive
        public string $transactionType = '',  // positive, raw "ICC"/"MAG"/...
        public string $authCode = '',         // positive
        public string $hostDateTime = '',     // positive, raw DDDHHMM
        public string $errorDescription = '', // negative
        public string $cardType = '',         // common, raw "1"/"2"/"3"
        public string $acquirerId = '',       // common
        public string $stan = '',             // common
        public string $onlineId = '',         // common
        public DccInfo $currency = new DccInfo,
    ) {}
}
