<?php
error_reporting(E_ALL ^ E_DEPRECATED);

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Sockets\ChatSocket;

require dirname(__DIR__) . '/vendor/autoload.php';

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatSocket()
        )
    ),
    8080
);

echo "WebSocket server bezi\n";
$server->run();