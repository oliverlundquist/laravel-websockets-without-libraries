<?php declare(strict_types=1);

namespace App\WebSocket\Connection;

use Illuminate\Database\Eloquent\Model;

class ConnectionEloquent extends Model
{
    protected $table = 'connections';
    protected $guarded = false;
}
