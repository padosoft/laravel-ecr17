<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Session;

use Padosoft\Ecr17\Protocol\LrcMode;

final class SessionConfig
{
    public function __construct(
        public LrcMode $lrcMode = LrcMode::Std,
        public int $ackTimeoutMs = 2000,      // wait for the physical ACK/NAK
        public int $responseTimeoutMs = 60000, // wait for the application response
        public int $retryCount = 3,           // retransmissions on NAK/timeout (spec: up to 3)
        public int $retryDelayMs = 200,       // delay between retransmissions
        public int $receiptDrainMs = 0,       // after the result, keep forwarding 'S' lines
    ) {}
}
