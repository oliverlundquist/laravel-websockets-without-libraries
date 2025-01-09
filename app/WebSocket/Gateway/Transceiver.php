<?php declare(strict_types=1);

namespace App\WebSocket\Gateway;

use ErrorException;
use Socket;

class Transceiver
{
    public function transmit(string $message, Socket $client, FrameType $frameType = FrameType::TEXT): int
    {
        $webSocketFrame = new FrameFactory()->createDataFrame($message, $frameType);

        $result = @socket_write($client, $webSocketFrame->frame);
        usleep(300000); // 300ms

        if ($result === false) {
            return 1;
        }
        return 0;
    }

    public function receive(Socket $client): WebSocketFrame|int
    {
        $result = socket_read($client, 10000000); // 10MB

        if ($result === false) {
            if (socket_last_error() === 11) {
                return 0;
            }
            return 1;
        }
        if (strlen($result) === 0) {
            return 0;
        }
        return new FrameFactory()->readDataFrame($result);
    }
}
