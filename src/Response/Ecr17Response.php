<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Response;

/**
 * Parsers for ECR17 terminal *response* application payloads (the bytes between
 * STX and ETX). Field offsets follow the spec response tables. Parsing is
 * defensive: fields beyond the payload length come back empty rather than
 * throwing, so a short/truncated response degrades gracefully.
 */
final class Ecr17Response
{
    public static function parsePayment(string $p): PaymentResponse
    {
        $r = new PaymentResponse();
        $code = self::at($p, 10, 1); // 'E' plain or 'V' DCC
        $dcc = $code === 'V';

        $r->resultCode = self::at($p, 11, 2);
        $r->outcome = Outcome::fromCode($r->resultCode);

        if ($r->outcome === Outcome::Ko) {
            $r->errorDescription = self::trimRight(self::at($p, 13, 24));
        } else {
            $r->pan = self::at($p, 13, 19);
            $r->transactionType = self::trimRight(self::at($p, 32, 3));
            $r->authCode = self::trimRight(self::at($p, 35, 6));
            $r->hostDateTime = self::at($p, 41, 7);
        }

        // Common to any response.
        $r->cardType = self::at($p, 48, 1);
        $r->acquirerId = self::trimRight(self::at($p, 49, 11));
        $r->stan = self::at($p, 60, 6);
        $r->onlineId = self::at($p, 66, 6);

        if ($dcc) {
            $r->currency = new DccInfo(
                applied: self::at($p, 83, 1) === '1',
                rate: self::at($p, 84, 8),
                currencyCode: self::trimRight(self::at($p, 92, 3)),
                amount: self::at($p, 95, 12),
                precision: self::at($p, 107, 1),
            );
        }

        return $r;
    }

    public static function parseStatus(string $p): StatusResponse
    {
        $r = new StatusResponse();
        $r->terminalId = self::at($p, 1, 8);
        $r->dateTimeRaw = self::at($p, 21, 10);
        $s = self::at($p, 31, 1);
        $r->status = ($s !== '' && ctype_digit($s)) ? (int) $s : -1;
        $r->softwareRelease = self::trimRight(self::at($p, 32, strlen($p)));

        return $r;
    }

    public static function parseTotals(string $p): TotalsResponse
    {
        $r = new TotalsResponse();
        $r->resultCode = self::at($p, 11, 2);
        $r->outcome = Outcome::fromCode($r->resultCode);
        $r->posTotal = self::at($p, 13, 16);

        return $r;
    }

    public static function parseClose(string $p): CloseResponse
    {
        $r = new CloseResponse();
        $r->resultCode = self::at($p, 11, 2);
        $r->outcome = Outcome::fromCode($r->resultCode);

        if ($r->outcome === Outcome::Ok) {
            $r->posTotal = self::at($p, 13, 16);
            $r->hostTotal = self::at($p, 29, 16);
        } else {
            $r->errorDescription = self::trimRight(self::at($p, 13, 19));
            $r->actionCode = self::at($p, 32, 3);
        }

        return $r;
    }

    public static function parsePreAuth(string $p): PreAuthResponse
    {
        $r = new PreAuthResponse();
        $r->resultCode = self::at($p, 11, 2);
        $r->outcome = Outcome::fromCode($r->resultCode);

        if ($r->outcome === Outcome::Ko) {
            $r->errorDescription = self::trimRight(self::at($p, 13, 24));
            $r->actionCode = self::at($p, 37, 3);
        } else {
            $r->pan = self::at($p, 13, 19);
            $r->transactionType = self::trimRight(self::at($p, 32, 3));
            $r->authCode = self::trimRight(self::at($p, 35, 6));
            $r->preAuthorizedAmount = self::at($p, 41, 8);
            $r->preAuthCode = self::at($p, 49, 9);
            $r->actionCode = self::at($p, 58, 3);
            $r->hostDateTime = self::at($p, 61, 7);
        }

        // In the OK layout preAuthorizedAmount occupies positions 41-48, so pos 48
        // is the amount's last digit, NOT a card type. Only read cardType for KO.
        if ($r->outcome === Outcome::Ko) {
            $r->cardType = self::at($p, 48, 1);
        }
        $r->acquirerId = self::trimRight(self::at($p, 72, 11));
        $r->stan = self::at($p, 83, 6);
        $r->onlineId = self::at($p, 89, 6);

        return $r;
    }

    public static function parseVas(string $p): VasResponse
    {
        $r = new VasResponse();
        $r->moreMessages = self::at($p, 15, 1) === '1';
        $r->idMessage = self::at($p, 16, 3);
        $r->rawXml = self::at($p, 27, strlen($p));
        $r->responseId = self::xmlValue($r->rawXml, 'RESPID');
        $r->responseMessage = self::xmlValue($r->rawXml, 'RESPMSG');
        $r->orderId = self::xmlValue($r->rawXml, 'ORDER_ID');

        return $r;
    }

    // --- Helpers --------------------------------------------------------------

    /** 1-based field extractor. Returns '' if the field starts beyond the payload. */
    private static function at(string $p, int $pos1, int $len): string
    {
        if ($pos1 === 0 || $pos1 > strlen($p)) {
            return '';
        }

        return substr($p, $pos1 - 1, $len);
    }

    private static function trimRight(string $s): string
    {
        return rtrim($s, ' ');
    }

    /** Extracts the value of a Nexi VAS XML param: <p k="KEY">value</p>. */
    private static function xmlValue(string $xml, string $key): string
    {
        $needle = '"'.$key.'">';
        $start = strpos($xml, $needle);
        if ($start === false) {
            return '';
        }

        $from = $start + strlen($needle);
        $end = strpos($xml, '<', $from);
        $value = $end === false ? substr($xml, $from) : substr($xml, $from, $end - $from);

        return trim($value);
    }
}
