<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Protocol;

use InvalidArgumentException;

/**
 * Builds ECR17 application-message payloads (the bytes between STX and ETX).
 * All fields are fixed-width and validated; an overflowing value throws so a
 * malformed frame is never produced.
 *
 * `paymentType` is the single request digit: '0' auto, '1' debit, '2' credit,
 * '3' other.
 */
final class Ecr17Protocol
{
    private const RESERVED = '0';      // '0' (0x30) filler for reserved numeric fields

    private const FIELD_SEP = "\x1B";  // end-of-field for the privative TAG content

    // --- Payment family (167 bytes): 'P' payment, 'X' extended, 'p' pre-auth ---

    public static function buildPaymentMessage(string $terminalId, string $cashRegisterId, int $amountCents, string $paymentType = '0', bool $cardAlreadyPresent = false, bool $withAdditionalData = false, string $receiptText = ''): string
    {
        return self::buildPaymentLike('P', $terminalId, $cashRegisterId, $amountCents, $paymentType, $cardAlreadyPresent, $withAdditionalData, $receiptText);
    }

    public static function buildExtendedPaymentMessage(string $terminalId, string $cashRegisterId, int $amountCents, string $paymentType = '0', bool $cardAlreadyPresent = false, bool $withAdditionalData = false, string $receiptText = ''): string
    {
        return self::buildPaymentLike('X', $terminalId, $cashRegisterId, $amountCents, $paymentType, $cardAlreadyPresent, $withAdditionalData, $receiptText);
    }

    public static function buildPreAuthMessage(string $terminalId, string $cashRegisterId, int $amountCents, string $paymentType = '0', bool $cardAlreadyPresent = false, bool $withAdditionalData = false, string $receiptText = ''): string
    {
        return self::buildPaymentLike('p', $terminalId, $cashRegisterId, $amountCents, $paymentType, $cardAlreadyPresent, $withAdditionalData, $receiptText);
    }

    // --- Pre-auth integration/closure (176 bytes): 'i' incremental, 'c' closure ---

    public static function buildIncrementalMessage(string $terminalId, string $cashRegisterId, int $amountCents, string $originalPreAuthCode, bool $withAdditionalData = false, string $receiptText = ''): string
    {
        return self::buildPreAuthFollowUp('i', $terminalId, $cashRegisterId, $amountCents, $originalPreAuthCode, $withAdditionalData, $receiptText);
    }

    public static function buildPreAuthClosureMessage(string $terminalId, string $cashRegisterId, int $amountCents, string $originalPreAuthCode, bool $withAdditionalData = false, string $receiptText = ''): string
    {
        return self::buildPreAuthFollowUp('c', $terminalId, $cashRegisterId, $amountCents, $originalPreAuthCode, $withAdditionalData, $receiptText);
    }

    // --- Card verification 'H' (39 bytes) ---

    public static function buildCardVerificationMessage(string $terminalId, string $cashRegisterId, string $paymentType = '0', bool $withAdditionalData = false): string
    {
        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .'H'                                     // 10 : message code
            .self::leftPad($cashRegisterId, 8)       // 11 : cash register id
            .self::flag($withAdditionalData)         // 19 : presence of additional GT data
            .'00'                                    // 20 : reserved (2)
            .'0'                                     // 22 : standard card verification
            .$paymentType                            // 23 : payment type
            .'0000000000000000';                     // 24 : reserved (16) -> 39
    }

    // --- Session commands (26 bytes): 'C' close, 'T' totals ---

    public static function buildCloseSessionMessage(string $terminalId, string $cashRegisterId, bool $withAdditionalData = false): string
    {
        return self::buildSessionCommand('C', $terminalId, $cashRegisterId, $withAdditionalData);
    }

    public static function buildTotalsMessage(string $terminalId, string $cashRegisterId, bool $withAdditionalData = false): string
    {
        return self::buildSessionCommand('T', $terminalId, $cashRegisterId, $withAdditionalData);
    }

    // --- Send last result 'G' (22 bytes) ---

    public static function buildSendLastResultMessage(string $terminalId, string $cashRegisterId, bool $withAdditionalData = false): string
    {
        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .'G'                                     // 10 : message code
            .self::leftPad($cashRegisterId, 8)       // 11 : cash register id
            .self::flag($withAdditionalData)         // 19 : presence of additional GT data
            .'000';                                  // 20 : reserved (3) -> 22
    }

    // --- Enable/disable ECR printing 'E' (11 bytes) ---

    public static function buildEnableEcrPrintMessage(string $terminalId, bool $enabled): string
    {
        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .'E'                                     // 10 : message code
            .self::flag($enabled);                   // 11 : enable(1)/disable(0) -> 11
    }

    // --- Reprint ticket 'R' (22 bytes) ---

    public static function buildReprintMessage(string $terminalId, bool $toEcr, string $ticketType = '0'): string
    {
        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .'R'                                     // 10 : message code
            .self::flag($toEcr)                      // 11 : 1 = receipt to ECR, 0 = print on terminal
            .$ticketType                             // 12 : ticket type flag
            .'0000000000';                           // 13 : reserved (10) -> 22
    }

    // --- Status 's' (10 bytes) ---

    public static function buildStatusMessage(string $terminalId): string
    {
        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .'s';                                    // 10 : message code (lowercase per spec) -> 10
    }

    // --- Reversal 'S' (26 bytes); stan "000000" = reverse last, no STAN check ---

    public static function buildReversalMessage(string $terminalId, string $cashRegisterId, string $stan = '000000'): string
    {
        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .'S'                                     // 10 : message code
            .self::leftPad($cashRegisterId, 8)       // 11 : cash register id
            .self::leftPad($stan, 6)                 // 19 : STAN ("000000" = no check)
            .self::RESERVED                          // 25 : presence of additional GT data
            .self::RESERVED;                         // 26 : reserved -> 26
    }

    // --- VAS 'K' (variable, length-prefixed XML, max 1024) ---

    public static function buildVasMessage(string $terminalId, string $ecrId, string $xmlRequest): string
    {
        if (strlen($xmlRequest) > 1024) {
            throw new InvalidArgumentException('ECR17: VAS request exceeds 1024 bytes');
        }

        return self::leftPad($terminalId, 8)                       // 1  : terminal id
            .self::RESERVED                                         // 9  : reserved
            .'K'                                                    // 10 : message code
            .self::leftPad($ecrId, 8)                               // 11 : ECR identifier
            .'000'                                                  // 19 : reserved (3)
            .self::RESERVED                                         // 22 : reserved (1)
            .self::leftPad((string) strlen($xmlRequest), 4)         // 23 : VAS request length (4)
            .$xmlRequest;                                           // 27 : VAS request (XML)
    }

    // --- Additional data for GT / tokenization 'U' (variable) ---

    public static function buildAdditionalTagsMessage(string $terminalId, string $tagContent, string $isoField = '62', string $tagNumber = 'DF8D01'): string
    {
        $len = strlen($tagContent);
        if ($len < 1 || $len > 100) {
            throw new InvalidArgumentException('ECR17: additional TAG content must be 1..100 chars');
        }

        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .'U'                                     // 10 : message code
            .'000000'                                // 11 : payment type (6) -> standard payment
            .self::leftPad($isoField, 2)             // 17 : ISO field number (e.g. "62")
            .self::rightPad($tagNumber, 8)           // 19 : TAG number, left-justified, blank-filled
            .self::RESERVED                          // 27 : reserved (1)
            .'0000'                                  // 28 : exclusive TAG index bytemap
            .'00000'                                 // 32 : reserved (5)
            .$tagContent                             // 37 : privative TAG content (1..100)
            .self::FIELD_SEP;                        //      end-of-field (0x1B)
    }

    /**
     * Formats the TAG 5 content for tokenization (Intesa-style mapping):
     *   "0COF0TRK<contract>|0FNZ03" (unscheduled/one-click)
     *   "0REC0TRK<contract>|0FNZ03" (recurring)
     */
    public static function formatTokenizationTag(bool $recurring, string $contractCode): string
    {
        $len = strlen($contractCode);
        if ($len < 1 || $len > 18) {
            throw new InvalidArgumentException('ECR17: tokenization contract code must be 1..18 chars');
        }

        $service = $recurring ? '0REC' : '0COF';

        return $service.'0TRK'.$contractCode.'|0FNZ03';
    }

    // --- Shared layouts -------------------------------------------------------

    /** Shared 167-byte payment-family layout (codes 'P', 'X', 'p'). */
    private static function buildPaymentLike(string $code, string $terminalId, string $cashRegisterId, int $amountCents, string $paymentType, bool $cardAlreadyPresent, bool $withAdditionalData, string $receiptText): string
    {
        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .$code                                   // 10 : message code
            .self::leftPad($cashRegisterId, 8)       // 11 : cash register id
            .self::flag($withAdditionalData)         // 19 : presence of additional GT data
            .'00'                                    // 20 : reserved
            .self::flag($cardAlreadyPresent)         // 22 : start-with-card-present
            .$paymentType                            // 23 : payment type
            .self::amountField($amountCents)         // 24 : amount (8)
            .self::leftPad($receiptText, 128, ' ')   // 32 : receipt text (128)
            .'00000000';                             // 160: reserved (8) -> 167
    }

    /** Shared 176-byte pre-auth integration/closure layout (codes 'i', 'c'). */
    private static function buildPreAuthFollowUp(string $code, string $terminalId, string $cashRegisterId, int $amountCents, string $originalPreAuthCode, bool $withAdditionalData, string $receiptText): string
    {
        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .$code                                   // 10 : message code
            .self::leftPad($cashRegisterId, 8)       // 11 : cash register id
            .self::flag($withAdditionalData)         // 19 : presence of additional GT data
            .'0000'                                  // 20 : reserved (4)
            .self::amountField($amountCents)         // 24 : amount (8)
            .self::leftPad($receiptText, 128, ' ')   // 32 : receipt text (128)
            .self::leftPad($originalPreAuthCode, 9)  // 160: original pre-auth code (9)
            .'00000000';                             // 169: reserved (8) -> 176
    }

    /** Shared 26-byte session command layout (codes 'C', 'T'). */
    private static function buildSessionCommand(string $code, string $terminalId, string $cashRegisterId, bool $withAdditionalData): string
    {
        return self::leftPad($terminalId, 8)        // 1  : terminal id
            .self::RESERVED                          // 9  : reserved
            .$code                                   // 10 : message code
            .self::leftPad($cashRegisterId, 8)       // 11 : cash register id
            .self::flag($withAdditionalData)         // 19 : presence of additional GT data
            .'0000000';                              // 20 : reserved (7) -> 26
    }

    // --- Field helpers --------------------------------------------------------

    /** Right-aligns into a fixed-width field, left-padding with $ch. Rejects overflow. */
    private static function leftPad(string $value, int $size, string $ch = self::RESERVED): string
    {
        if (strlen($value) > $size) {
            throw new InvalidArgumentException(
                "ECR17: value '{$value}' exceeds fixed field width of {$size} bytes"
            );
        }

        return str_pad($value, $size, $ch, STR_PAD_LEFT);
    }

    /** Left-aligns into a fixed-width field, right-padding with $ch. Rejects overflow. */
    private static function rightPad(string $value, int $size, string $ch = ' '): string
    {
        if (strlen($value) > $size) {
            throw new InvalidArgumentException(
                "ECR17: value '{$value}' exceeds fixed field width of {$size} bytes"
            );
        }

        return str_pad($value, $size, $ch, STR_PAD_RIGHT);
    }

    private static function amountField(int $amountCents): string
    {
        if ($amountCents < 0) {
            throw new InvalidArgumentException('ECR17: amount must be non-negative');
        }

        return self::leftPad((string) $amountCents, 8);
    }

    private static function flag(bool $on): string
    {
        return $on ? '1' : '0';
    }
}
