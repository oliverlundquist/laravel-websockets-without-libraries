<?php

use App\WebSocket\Connection\ConnectionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use function App\WebSocket\Connection\pingConnectionId;

Route::get('/', fn () => view('chat_app'));

Route::get('/ping/{connectionId}', function (string $connectionId) {
    new ConnectionHandler()->pingConnectionId($connectionId);
    return 'pong!';
});
