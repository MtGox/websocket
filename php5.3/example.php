<?php

require('SocketIO.class.php');
require('MtGoxSocket.class.php');

$socketio = new MtGoxSocket('a8f0c118-f963-430a-9150-02fc673afdda', '(secret here)');
$socketio->on('trade', function($msg) { var_dump($msg); });
$socketio->call('private/info');
$socketio->loop();

