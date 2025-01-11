<?php declare(strict_types=1);

namespace App\WebSocket\Connection;

use Illuminate\Support\Str;

class ConnectionHandler
{
    /**
     * @param ?UUIDv4String $connectionId
     * @param AppString $app
     * @return UUIDv4String
     */
    public function accept(?string $connectionId = null, string $app = 'chat_app'): string
    {
        $nowTimestamp = intval(now()->format('U'));
        $connectionId = $connectionId ?? (string) Str::uuid();

        ConnectionEloquent::where('connection_id', $connectionId)->delete();
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
    public function getInactiveConnections(): array
    {
        $fourMinutes = intval(now()->subMinutes(4)->format('U')); // 4 missed pings (Chrome limits setInterval scripts to 1 execution per minute when tab is in background.)
        /** @var array<int, UUIDv4String> $inactiveConnectionIds */
        $inactiveConnectionIds = ConnectionEloquent::where('last_activity_at', '<', $fourMinutes)->pluck('connection_id')->all();

        return $inactiveConnectionIds;
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
