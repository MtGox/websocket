<?php

class MtGoxSocket extends SocketIO {
	private $api = null; // api access

	public function __construct($key, $secret) {
		$this->api = array($key, $secret);
		$this->on('message', array($this, 'handleMessage'));
		parent::__construct('https://socketio.mtgox.com/mtgox');
	}

	public function handleMessage($msg) {
		if (is_string($msg)) $msg = json_decode($msg, true);
		if ($msg['op'] == 'private') {
			// not a stream command
			$type = $msg['private'];
			$this->dispatch($type, array($msg[$type], $type, $msg['channel']));
			return;
		}
		var_dump($msg);
	}

	public function call($call, $params = array(), $item = null, $currency = null) {
		$nonce = explode(' ', microtime(false));
		$nonce = $nonce[1].substr($nonce[0], 2, 6);
		$id = md5($nonce); // id can be anything to recognize this call
		$query = array('call' => $call, 'params' => $params, 'item' => $item, 'currency' => $currency, 'id' => $id, 'nonce' => $nonce);
		$query = json_encode($query);
		// generate signature
		$sign = hash_hmac('sha512', $query, base64_decode($this->api[1]), true);
		// prefix signature to query
		$query = pack('H*', str_replace('-','',$this->api[0])).$sign.$query;
		// send query
		$call = array('op' => 'call', 'call' => base64_encode($query), 'id' => $id, 'context' => 'mtgox.com');
		$this->send_json($call);
		return $id;
	}
}

