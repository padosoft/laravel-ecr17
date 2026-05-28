<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Response;

final class StatusResponse
{
    public function __construct(
        public string $terminalId = '',
        public string $dateTimeRaw = '', // "DDMMYYhhmm"
        public int $status = -1,         // 0..6, -1 = unknown/missing
        public string $softwareRelease = '',
    ) {}
}
