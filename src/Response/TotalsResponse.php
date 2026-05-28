<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Response;

final class TotalsResponse
{
    public function __construct(
        public Outcome $outcome = Outcome::Unknown,
        public string $resultCode = '',
        public string $posTotal = '', // 16 digits, cents
    ) {}
}
