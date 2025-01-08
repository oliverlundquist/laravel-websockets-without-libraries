<?php declare(strict_types=1);

namespace App\WebSocket\Connection;

use Illuminate\Support\Str;

class ConnectionHandler
{
    /**
     * @param AppString $app
     * @return UUIDv4String
     */
    public function accept(string $app = 'chat_app'): string
    {
        $nowTimestamp = intval(now()->format('U'));
        $connectionId = (string) Str::uuid();

        ConnectionEloquent::create([
            'instance_name'    => config('instance.name'),
            'connection_id'    => $connectionId,
            'app_name'         => $app,
            'last_activity_at' => $nowTimestamp,
        ]);

        return $connectionId;
    }

    /**
     * @param UUIDv4String $connectionId
     */
    public function pingConnectionId(string $connectionId): void
    {
        $nowTimestamp = intval(now()->format('U'));
        ConnectionEloquent::where('connection_id', $connectionId)->update(['last_activity_at' => $nowTimestamp]);
    }

    /**
     * @return array<int, UUIDv4String>
     */
    public function purgeInactiveConnections(): array
    {
        $tenSecondsAgo        = intval(now()->subSeconds(10)->format('U'));
        /** @var array<int, UUIDv4String> $deletedConnectionIds */
        $deletedConnectionIds = ConnectionEloquent::where('last_activity_at', '<', $tenSecondsAgo)->pluck('connection_id')->all();
        ConnectionEloquent::where('last_activity_at', '<', $tenSecondsAgo)->delete();

        return $deletedConnectionIds;
    }

    /**
     * @return array<int, string>
     */
    public function getInstancesList(): array
    {
        /** @var array<int, string> $instanceList */
        $instanceList = ConnectionEloquent::select('instance_name')->distinct()->pluck('instance_name')->all();
        return $instanceList;
    }

    /**
     * @param AppString $app
     * @return array{
     *      id: int,
     *      connection_id: UUIDv4String,
     *      instance_name: string,
     *      last_activity_at: UnixTimestamp
     * }
     */
    public function getConnectionsList(string $app = 'chat_app'): array
    {
        /** @var array{id: int, connection_id: UUIDv4String, instance_name: string, last_activity_at: UnixTimestamp} $connectionsList */
        $connectionsList = ConnectionEloquent::where('app_name', $app)->select(['id', 'connection_id', 'instance_name', 'last_activity_at'])->get()->toArray();
        return $connectionsList;
    }

    /**
     * @param UUIDv4String $connectionId
     */
    public function delete(string $connectionId): void
    {
        ConnectionEloquent::where('connection_id', $connectionId)->delete();
    }
}
