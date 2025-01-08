<?php declare(strict_types=1);

namespace App\WebSocket\Server;

use Exception;
use Socket;

class WebSocketServer
{
    public function startServer(string $address = '0.0.0.0', int $port = 10000): Socket
    {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($server === false) {
            throw new Exception('Failed to start server on ' . $address . ':' . $port);
        }
        socket_set_nonblock($server);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($server, SOL_SOCKET, SO_SNDBUF, 1000000); // 1MB
        socket_set_option($server, SOL_SOCKET, SO_RCVBUF, 1000000); // 1MB
        @socket_bind($server, $address, $port);
        socket_listen($server);
        return $server;
    }

    public function sendHandshakeResponse(Socket $client): int
    {
        $request = socket_read($client, 10000000); // 10MB
        if ($request === false) {
            socket_close($client);
            return 1;
        }
        if (preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $request, $matches) === 0) {
            socket_close($client);
            return 2;
        }
        if (! array_key_exists(1, $matches)) {
            socket_close($client);
            return 3;
        }
        $key      = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $headers  = "HTTP/1.1 101 Switching Protocols\r\n";
        $headers .= "Upgrade: websocket\r\n";
        $headers .= "Connection: Upgrade\r\n";
        $headers .= "Sec-WebSocket-Version: 13\r\n";
        $headers .= "Sec-WebSocket-Accept: $key\r\n";
        $headers .= "\r\n";
        $result   = socket_write($client, $headers, strlen($headers));

        return $result === false ? 4 : 0;
    }
}
