<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Response;

final class PreAuthResponse
{
    public function __construct(
        public Outcome $outcome = Outcome::Unknown,
        public string $resultCode = '',
        public string $pan = '',
        public string $transactionType = '',
        public string $authCode = '',
        public string $preAuthorizedAmount = '', // 8 digits, cents
        public string $preAuthCode = '',         // 9 digits, unique pre-auth id
        public string $actionCode = '',
        public string $hostDateTime = '',
        public string $errorDescription = '',
        public string $cardType = '',
        public string $acquirerId = '',
        public string $stan = '',
        public string $onlineId = '',
    ) {}
}
