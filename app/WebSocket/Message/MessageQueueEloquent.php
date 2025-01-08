<?php declare(strict_types=1);

namespace App\WebSocket\Message;

use Illuminate\Database\Eloquent\Model;

class MessageQueueEloquent extends Model
{
    protected $table = 'message_queue';
    protected $guarded = false;
}
