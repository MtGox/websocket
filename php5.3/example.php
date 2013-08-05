<?php

require('SocketIO.class.php');
require('MtGoxSocket.class.php');

$socketio = new MtGoxSocket('dbf1dee9-4f2e-4a08-8cb7-748919a71b21', '');
$socketio->on('trade', function($msg) { var_dump($msg); });
$socketio->loop();

