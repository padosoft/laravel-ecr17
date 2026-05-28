<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Response;

final class VasResponse
{
    public function __construct(
        public string $responseId = '',      // RESPID ("0" = OK), "" if absent
        public string $responseMessage = '', // RESPMSG
        public string $orderId = '',         // ORDER_ID
        public bool $moreMessages = false,   // concatenation flag "1"
        public string $idMessage = '',       // 3-digit sequence
        public string $rawXml = '',          // XML body of this message
    ) {}
}
