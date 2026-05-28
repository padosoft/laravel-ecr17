<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Protocol;

/**
 * Frames/decodes a single ECR17 packet. Byte buffers are PHP (binary) strings.
 *
 * `decode()` treats the buffer as exactly ONE frame (LRC = the final byte for
 * application frames). Splitting a byte stream into individual frames is the
 * transport layer's responsibility.
 */
final class PacketCodec
{
    public const STX = 0x02;

    public const ETX = 0x03;

    public const SOH = 0x01;

    public const EOT = 0x04;

    public const ACK = 0x06;

    public const NAK = 0x15;

    public function __construct(private readonly LrcMode $lrcMode) {}

    /** STX + payload + ETX + LRC. */
    public function encodeApplication(string $payload): string
    {
        return chr(self::STX)
            .$payload
            .chr(self::ETX)
            .chr(Lrc::compute($payload, $this->lrcMode));
    }

    /** ctrl + ETX + LRC (e.g. ACK/NAK). */
    public function encodeControl(int $ctrl): string
    {
        return chr($ctrl)
            .chr(self::ETX)
            .chr(Lrc::compute(chr($ctrl), $this->lrcMode));
    }

    public function decode(string $data): DecodedPacket
    {
        if ($data === '') {
            return new DecodedPacket(PacketType::Unknown, '', false);
        }

        $first = ord($data[0]);

        if ($first === self::ACK) {
            return new DecodedPacket(PacketType::Ack, '', true);
        }

        if ($first === self::NAK) {
            return new DecodedPacket(PacketType::Nak, '', true);
        }

        if ($first === self::SOH) {
            // Progress = SOH + 20-char message + EOT (no LRC). Need at least SOH +
            // EOT, and the final byte MUST be EOT (reject garbage/truncated reads).
            if (strlen($data) < 2 || ord($data[strlen($data) - 1]) !== self::EOT) {
                return new DecodedPacket(PacketType::Unknown, '', false);
            }

            return new DecodedPacket(PacketType::Progress, substr($data, 1, -1), true);
        }

        if ($first === self::STX) {
            $etxIndex = strpos($data, chr(self::ETX));
            if ($etxIndex === false) {
                return new DecodedPacket(PacketType::Unknown, '', false);
            }

            // A well-formed frame is exactly STX + payload + ETX + LRC, so the LRC
            // must be the final byte. Reject a truncated frame (no LRC) and a
            // buffer with trailing bytes (coalesced reads / garbage).
            if ($etxIndex + 2 !== strlen($data)) {
                return new DecodedPacket(PacketType::Unknown, '', false);
            }

            $payload = substr($data, 1, $etxIndex - 1);
            $rxLrc = ord($data[$etxIndex + 1]);
            $calcLrc = Lrc::compute($payload, $this->lrcMode);

            return new DecodedPacket(PacketType::Application, $payload, $rxLrc === $calcLrc);
        }

        return new DecodedPacket(PacketType::Unknown, '', false);
    }
}
