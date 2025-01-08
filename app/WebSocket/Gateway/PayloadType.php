<?php declare(strict_types=1);

namespace App\WebSocket\Gateway;

enum PayloadType
{
    case SHORT;  // up to 125
    case MEDIUM; // up to 65536 (16-bytes)
    case LONG;   // up to 18446744073709551615 (64-bytes)
}
