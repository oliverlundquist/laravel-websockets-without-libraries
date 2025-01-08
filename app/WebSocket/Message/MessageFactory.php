<?php declare(strict_types=1);

namespace App\WebSocket\Message;

use Exception;

class MessageFactory
{
    /**
     * @param UUIDv4String $connectionId
     * @param AppString $appName
     */
    public function welcomeMessage(string $connectionId, string $instanceName, string $appName = 'chat_app'): string
    {
        return $this->jsonEncodeMessage([
            'event'         => 'welcome_message',
            'connection_id' => $connectionId,
            'instance_name' => $instanceName,
            'app_name'      => $appName,
        ]);
    }

    /**
     * @param array{id: int, connection_id: UUIDv4String, instance_name: string, last_activity_at: UnixTimestamp} $userList
     */
    public function userList(array $userList): string
    {
        return $this->jsonEncodeMessage([
            'event' => 'user_list',
            'users' => $userList
        ]);
    }

    /**
     * @param AppString $appName
     */
    public function chatMessage(string $payload, string $appName = 'chat_app'): string
    {
        return $this->jsonEncodeMessage([
            'event'    => 'public_chat',
            'app_name' => $appName,
            'payload'  => $payload,
        ]);
    }

    /**
     * @param UUIDv4String $connectionId
     * @param AppString $appName
     */
    public function dmMessage(string $connectionId, string $instanceName, string $appName = 'chat_app'): string
    {
        return $this->jsonEncodeMessage([
            'event'       => 'private_chat',
            'app_name'    => $appName,
            'destination' => [
                'connection_id' => $connectionId,
                'instance_name' => $instanceName
            ]
        ]);
    }

    /**
     * @param array<string, string|array<string, string|int>> $message
     */
    private function jsonEncodeMessage(array $message): string
    {
        $message = json_encode($message);
        if ($message === false) {
            throw new Exception('Failed to json_encode message');
        }
        return $message;
    }
}
