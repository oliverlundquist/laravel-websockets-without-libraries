<?php declare(strict_types=1);

namespace App\WebSocket\Message;

class MessageQueueHandler
{
    /**
     * @return array<int, \App\WebSocket\Message\MessageQueueEloquent>
     */
    public function getMessagesForInstance(string $instance): array
    {
        $messages = MessageQueueEloquent::where('instance_name', $instance)->get();
        $messages->map(fn ($message) => $message->delete());
        return $messages->all();
    }

    /**
     * @param array<int, string> $instances
     */
    public function queueForBroadcast(array $instances, string $message): void
    {
        foreach ($instances as $instance) {
            MessageQueueEloquent::create([
                'instance_name' => $instance,
                'type'         => 'broadcast',
                'payload'      => $message,
                'target_id'    => null
            ]);
        }
    }

    /**
     * @param UUIDv4String $connectionId
     */
    public function queueForConnectionId(string $instance, string $connectionId, string $message): void
    {
        MessageQueueEloquent::create([
            'instance_name' => $instance,
            'type'         => 'direct',
            'payload'      => $message,
            'target_id'    => $connectionId
        ]);
    }
}
