<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Protocol;

enum PacketType
{
    case Application;
    case Progress; // SOH-framed procedure progress update (0x01 ... 0x04), no LRC
    case Ack;
    case Nak;
    case Unknown;
}
