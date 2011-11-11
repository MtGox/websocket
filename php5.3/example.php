<?php

require('SocketIO.class.php');

$socketio = new SocketIO('https://socketio.mtgox.com/mtgox');
$socketio->on('message', function($msg) { var_dump($msg); });
$socketio->loop();

