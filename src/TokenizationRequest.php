<?php

declare(strict_types=1);

namespace Padosoft\Ecr17;

enum TokenizationService
{
    case Recurring;
    case UnscheduledOrOneClick;
}

/** Tokenization additional-data ('U') attached to a payment/preauth/verify. */
final readonly class TokenizationRequest
{
    public function __construct(
        public TokenizationService $service,
        /** Unique contract code at merchant level, alphanumeric, up to 18 chars. */
        public string $contractCode,
    ) {}
}
