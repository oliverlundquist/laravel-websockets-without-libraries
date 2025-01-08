<?php declare(strict_types=1);

namespace App\WebSocket\Gateway;

use InvalidArgumentException;
use UnhandledMatchError;


class FrameFactory
{
    public function readDataFrame(string $dataFrame): WebSocketFrame
    {
        $frameType     = $this->getFrameType(ord($dataFrame[0]));
        $payloadType   = $this->getPayloadType($dataFrame);
        $isMasked      = (ord($dataFrame[1]) & 0b10000000) === 128;
        $payloadLength = $this->calculatePayloadLength($dataFrame, $payloadType);
        $maskBytes     = $this->extractMaskBytes($dataFrame, $payloadType);
        $payload       = $this->extractPayload($dataFrame, $isMasked, $payloadType);

        if ($isMasked) {
            $payload = $this->unmaskPayload($payload, $maskBytes);
        }
        return new WebSocketFrame($frameType, $dataFrame, $payloadType, $payloadLength, $payload, $this->jsonDecodedPayload($payload), $isMasked, $maskBytes);
    }

    public function createDataFrame(string $payload, FrameType $frameType = FrameType::TEXT): WebSocketFrame
    {
        $header      = $this->buildFrameHeader($payload, $frameType);
        $frame       = $header . $payload;
        $payloadType = match (strlen($header)) {
            2  => PayloadType::SHORT,
            4  => PayloadType::MEDIUM,
            10 => PayloadType::LONG,
            default => null
        };
        if (is_null($payloadType)) {
            throw new UnhandledMatchError('Invalid Header Length');
        }
        return new WebSocketFrame($frameType, $frame, $payloadType, strlen($payload), $payload, $this->jsonDecodedPayload($payload));
    }

    private function getFrameType(int $firstByte): FrameType
    {
        $frameType = FrameType::tryFrom($firstByte);
        if (is_null($frameType)) {
            throw new InvalidArgumentException('Invalid Frame Type');
        }
        return $frameType;
    }

    private function getPayloadType(string $dataFrame): PayloadType
    {
        $secondByteLength = ord($dataFrame[1]) & 0b1111111; // 8bit -> 7bit

        $payloadType = match (true) {
            $secondByteLength  <= 125 => PayloadType::SHORT,
            $secondByteLength === 126 => PayloadType::MEDIUM,
            $secondByteLength === 127 => PayloadType::LONG,
            default => null
        };
        if (is_null($payloadType)) {
            throw new InvalidArgumentException('Invalid Payload Type');
        }
        return $payloadType;
    }

    private function calculatePayloadLength(string $dataFrame, PayloadType $payloadType): int
    {
        $secondByteLength = ord($dataFrame[1]) & 0b1111111; // 8bit -> 7bit

        $lengthInDecimal = match (true) {
            $payloadType === PayloadType::SHORT  => $secondByteLength,
            $payloadType === PayloadType::MEDIUM => unpack('n', substr($dataFrame, 2, 2)),
            $payloadType === PayloadType::LONG   => unpack('J', substr($dataFrame, 2, 8)),
            default => null
        };
        /** @var array<int, int>|false|null $lengthInDecimal */
        if ($lengthInDecimal === false || is_null($lengthInDecimal)) {
            throw new InvalidArgumentException('Invalid Payload Length');
        }
        return is_int($lengthInDecimal) ? $lengthInDecimal : $lengthInDecimal[1];
    }

    private function extractPayload(string $dataFrame, bool $isMasked, PayloadType $payloadType): string
    {
        $payload = match (true) {
            $payloadType === PayloadType::SHORT  => substr($dataFrame, $isMasked ?  6  : 2),
            $payloadType === PayloadType::MEDIUM => substr($dataFrame, $isMasked ?  8  : 4),
            $payloadType === PayloadType::LONG   => substr($dataFrame, $isMasked ? 14 : 10),
            default => null
        };
        if (is_null($payload)) {
            throw new InvalidArgumentException('Failed to Extract Payload');
        }
        return $payload;
    }

    private function extractMaskBytes(string $dataFrame, PayloadType $payloadType): string
    {
        $maskBytes = match (true) {
            $payloadType === PayloadType::SHORT  => substr($dataFrame,  2, 4),
            $payloadType === PayloadType::MEDIUM => substr($dataFrame,  4, 4),
            $payloadType === PayloadType::LONG   => substr($dataFrame, 10, 4),
            default => null
        };
        if (is_null($maskBytes)) {
            throw new InvalidArgumentException('Failed to Extract Mask Bytes');
        }
        return $maskBytes;
    }

    private function unmaskPayload(string $payload, string $maskBytes): string
    {
        $unmaskedText = '';

        for ($i = 0; $i < strlen($payload); $i++) {
            $unmaskedText .= $payload[$i] ^ $maskBytes[$i % 4];
        }
        return $unmaskedText;
    }

    private function jsonDecodedPayload(string $payload): ?object
    {
        /** @var object|null $jsonDecodedPayload */
        $jsonDecodedPayload = json_validate($payload) ? json_decode($payload) : null;
        return $jsonDecodedPayload;
    }

    private function buildFrameHeader(string $payload, FrameType $frameType): string
    {
        $payloadLength = strlen($payload);

        return match (true) {
            $payloadLength <= 125                           => pack('CC',  $frameType->value, $payloadLength),
            $payloadLength  > 125 && $payloadLength < 65536 => pack('CCn', $frameType->value, 126, $payloadLength),
            $payloadLength >= 65536                         => pack('CCJ', $frameType->value, 127, $payloadLength)
        };
    }
}
