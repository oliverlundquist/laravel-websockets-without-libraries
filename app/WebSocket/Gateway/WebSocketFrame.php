<?php declare(strict_types=1);

namespace App\WebSocket\Gateway;

class WebSocketFrame
{
    public function __construct(
        public FrameType $frameType,
        public string $frame,
        public PayloadType $payloadType,
        public int $payloadLength,
        public string $payload,
        public ?object $jsonDecodedPayload = null,
        public bool $isMasked = false,
        public string $maskBytes = ''
    ) {}
}
