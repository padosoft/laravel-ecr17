<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Response;

final class CloseResponse
{
    public function __construct(
        public Outcome $outcome = Outcome::Unknown,
        public string $resultCode = '',
        public string $posTotal = '',         // positive, 16 digits
        public string $hostTotal = '',        // positive, 16 digits
        public string $errorDescription = '', // negative
        public string $actionCode = '',       // negative
    ) {}
}
