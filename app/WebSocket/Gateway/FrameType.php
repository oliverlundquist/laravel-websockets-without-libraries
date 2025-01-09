<?php declare(strict_types=1);

namespace App\WebSocket\Gateway;

enum FrameType: int
{
    // For the sake of brevity,
    // Message Fragmentation and Binary Payloads
    // are not implemented in this example app.

    // 0000 0000 continuation (0x0)
    // case CONTINUATION = 0b00000000;

    // 1000 0000 final, continuation (0x0)
    // case CONTINUATION_END = 0b10000000;

    // 1000 0001 final, text (UTF-8) (0x1)
    case TEXT = 0b10000001;

    // 1000 0010 final, binary (0x2)
    // case BINARY = 0b10000010;

    // 1000 1000 control: close (0x8)
    case CONTROL_CLOSE = 0b10001000;

    // 1000 1001 control: ping (0x9)
    case CONTROL_PING = 0b10001001;

    // 1000 1010 control: pong (0xA)
    case CONTROL_PONG = 0b10001010;
}
