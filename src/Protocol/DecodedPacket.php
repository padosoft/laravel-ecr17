<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Protocol;

final readonly class DecodedPacket
{
    public function __construct(
        public PacketType $type,
        public string $payload,
        public bool $validLrc,
    ) {}
}
