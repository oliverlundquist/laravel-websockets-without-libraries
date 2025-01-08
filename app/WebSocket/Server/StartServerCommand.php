<?php declare(strict_types=1);

namespace App\WebSocket\Server;

use App\WebSocket\Connection\ConnectionHandler;
use App\WebSocket\Gateway\DataTransformer;
use App\WebSocket\Gateway\FrameFactory;
use App\WebSocket\Gateway\FrameType;
use App\WebSocket\Gateway\Transceiver;
use App\WebSocket\Gateway\WebSocketFrame;
use App\WebSocket\Message\MessageFactory;
use App\WebSocket\Message\MessageQueueHandler;
use ErrorException;
use Illuminate\Console\Command;
use Socket;

class StartServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:start-server';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts a WebSocket server';

    /**
     * Variable that holds all active connections.
     *
     * @var array<UUIDv4String, \Socket>
     */
    protected $clients = [];

    /**
     * Variable that holds a web socket server instance.
     *
     * @var WebSocketServer
     */
    protected $webSocketServer;

    /**
     * Variable that holds a connection handler instance.
     *
     * @var ConnectionHandler
     */
    protected $connectionHandler;

    /**
     * Variable that holds a message queue handler instance.
     *
     * @var MessageQueueHandler
     */
    protected $messageQueueHandler;

    /**
     * Variable that holds a transceiver instance.
     *
     * @var Transceiver
     */
    protected $transceiver;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->webSocketServer     = new WebSocketServer;
        $this->connectionHandler   = new ConnectionHandler;
        $this->messageQueueHandler = new MessageQueueHandler;
        $this->transceiver         = new Transceiver;

        $server = $this->webSocketServer->startServer();

        while (true) {

            // 1. accept new connections
            $this->acceptNewConnections($server);
            usleep(300000); // 300ms

            // 2. filter out inactive connections
            $this->purgeInactiveConnections();
            usleep(300000); // 300ms

            // 3. process queued messages
            $this->processQueuedMessages();
            usleep(300000); // 300ms

            // 4. read sockets looking for new queued message
            $this->readDataFromAllConnections();
            usleep(300000); // 300ms
        }
    }

    private function acceptNewConnections(Socket $server): void
    {
        $client = $this->newClientAttemptingToConnect($server);
        if ($client instanceof Socket) {
            $successfulHandshake = $this->webSocketServer->sendHandshakeResponse($client) === 0;
            if ($successfulHandshake === true) {
                $this->addConnection($client);
            }
        }
    }

    private function purgeInactiveConnections(): void
    {
        $deletedConnectionIds = $this->connectionHandler->purgeInactiveConnections();
        foreach ($deletedConnectionIds as $deletedConnectionId) {
            unset($this->clients[$deletedConnectionId]);
        }
    }

    private function processQueuedMessages(): void
    {
        /** @var string $instanceName */
        $instanceName = config('instance.name') ?? '';
        $messages     = $this->messageQueueHandler->getMessagesForInstance($instanceName);

        foreach ($messages as $message) {
            if ($message->type === 'broadcast') {
                $this->transmitToAllConnections($message->payload);
            }
            if (is_null($message->target_id)) {
                continue;
            }
            if ($message->type === 'direct') {
                $this->transmitToConnection($message->target_id, $message->payload);
            }
        }
    }

    private function readDataFromAllConnections(): void
    {
        foreach ($this->clients as $connectionId => $client) {
            $webSocketFrame = $this->receive($connectionId);
            if (is_null($webSocketFrame)) {
                continue;
            }
            $this->respondToControlFrames($webSocketFrame, $connectionId);
            $this->queueTextMessage($webSocketFrame, $connectionId);
        }
    }

    /**
     * @param UUIDv4String $connectionId
     */
    private function respondToControlFrames(WebSocketFrame $webSocketFrame, string $connectionId): void
    {
        if ($webSocketFrame->frameType === FrameType::CONTROL_PING) {
            $this->transmit($connectionId, 'pong!', FrameType::CONTROL_PONG);
        }
        if ($webSocketFrame->frameType === FrameType::CONTROL_PONG) {
            // ignore silently
        }
        if ($webSocketFrame->frameType === FrameType::CONTROL_CLOSE) {
            $this->transmit($connectionId, 'byebye!', FrameType::CONTROL_CLOSE);
        }
    }

    /**
     * @param ?UUIDv4String $connectionId
     */
    private function queueTextMessage(WebSocketFrame $webSocketFrame, ?string $connectionId = null): void
    {
        if ($webSocketFrame->frameType !== FrameType::TEXT) {
            return;
        }
        if (is_null($webSocketFrame->jsonDecodedPayload)) {
            return;
        }
        /** @var object{event: string, destination: object{instance_name: string, connection_id: UUIDv4String}} $jsonDecodedPayload */
        $jsonDecodedPayload = $webSocketFrame->jsonDecodedPayload;
        //
        // Broadcast Events
        //
        if (in_array($jsonDecodedPayload->event, ['public_chat', 'user_list'])) {
            $instances = $this->connectionHandler->getInstancesList();
            $this->messageQueueHandler->queueForBroadcast($instances, $webSocketFrame->payload);
        }
        //
        // Direct Events
        //
        if (is_null($connectionId)) {
            return;
        }
        if ($jsonDecodedPayload->event === 'private_chat') {
            $instanceName = $jsonDecodedPayload->destination->instance_name;
            $connectionId = $jsonDecodedPayload->destination->connection_id;
            $this->messageQueueHandler->queueForConnectionId($instanceName, $connectionId, $webSocketFrame->payload);
        }
    }

    private function transmitToAllConnections(string $message): void
    {
        foreach ($this->clients as $connectionId => $client) {
            $this->transmit($connectionId, $message);
        }
    }

    private function transmitToConnection(string $connectionId, string $message): void
    {
        $this->transmit($connectionId, $message);
    }

    /**
     * @param UUIDv4String $connectionId
     */
    private function transmit(string $connectionId, string $message, FrameType $frameType = FrameType::TEXT): void
    {
        if (! array_key_exists($connectionId, $this->clients)) {
            return;
        }
        $result = $this->transceiver->transmit($message, $this->clients[$connectionId], $frameType);
        if ($result === 1) { // broken pipe
            $this->removeConnection($connectionId);
        }
    }

    /**
     * @param UUIDv4String $connectionId
     */
    private function receive(string $connectionId): ?WebSocketFrame
    {
        if (! array_key_exists($connectionId, $this->clients)) {
            return null;
        }
        $result = $this->transceiver->receive($this->clients[$connectionId]);
        if ($result === 1) { // broken pipe
            $this->removeConnection($connectionId);
        }
        return $result instanceof WebSocketFrame ? $result : null;
    }

    private function newClientAttemptingToConnect(Socket $server): ?Socket
    {
        $client = socket_accept($server);

        return $client === false ? null : $client;
    }

    private function broadcastUsersList(): void
    {
        $userList       = $this->connectionHandler->getConnectionsList();
        $message        = new MessageFactory()->userList($userList);
        $webSocketFrame = new FrameFactory()->createDataFrame($message);
        $this->queueTextMessage($webSocketFrame);
    }

    private function addConnection(Socket $client): void
    {
        /** @var string $instanceName */
        $instanceName   = config('instance.name') ?? '';
        $connectionId   = $this->connectionHandler->accept();
        $welcomeMessage = (new MessageFactory)->welcomeMessage($connectionId, $instanceName);
        $this->transceiver->transmit($welcomeMessage, $client);
        socket_set_nonblock($client);
        socket_set_option($client, SOL_SOCKET, SO_SNDBUF, 1000000); // 1MB
        socket_set_option($client, SOL_SOCKET, SO_RCVBUF, 1000000); // 1MB
        $this->clients[$connectionId] = $client;
        $this->broadcastUsersList();
    }

    /**
     * @param UUIDv4String $connectionId
     */
    private function removeConnection(string $connectionId): void
    {
        unset($this->clients[$connectionId]);
        $this->connectionHandler->delete($connectionId);
        $this->broadcastUsersList();
    }
}