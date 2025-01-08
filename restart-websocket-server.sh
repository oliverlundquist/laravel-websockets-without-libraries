#!/bin/bash

docker exec -it laravel-websockets-without-libraries-php-websocket-server-1-1 bash -c 'kill $(pidof php artisan websocket:start-server)'
docker exec -it laravel-websockets-without-libraries-php-websocket-server-2-1 bash -c 'kill $(pidof php artisan websocket:start-server)'
docker exec -it laravel-websockets-without-libraries-php-websocket-server-3-1 bash -c 'kill $(pidof php artisan websocket:start-server)'
sleep 1
docker exec -it laravel-websockets-without-libraries-php-websocket-server-1-1 kill 1
docker exec -it laravel-websockets-without-libraries-php-websocket-server-2-1 kill 1
docker exec -it laravel-websockets-without-libraries-php-websocket-server-3-1 kill 1
