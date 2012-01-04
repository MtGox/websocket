<?php

// doc at https://github.com/LearnBoost/socket.io-spec

class SocketIO {
	private $fd; // file descriptor of websocket
	private $session; // session data
	private $stamp; // stamp of last ping (or connection)
	private $events = array(); // bound events
	private $buf = ''; // read buffer
	private $url; // parsed url

	public function __construct($url) {
		// get a session (parse url & rebuild url for session getting)
		$this->url = parse_url($url);
		$socketio = $this->url['scheme'].'://'.$this->url['host'];
		if (isset($this->url['port'])) $socketio .= ':'.$this->url['port'];
		$socketio .= '/socket.io/1'; // we implement protocol "1"

		$ch = curl_init($socketio);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		if ($res === false) throw new \Exception(curl_error($ch));

		// parse string in format session:minping:maxping:protos
		$res = explode(':', $res);
		$this->session = $res;
		$protos = array_flip(explode(',', $res[3]));
		if (!isset($protos['websocket'])) throw new \Exception('This socket.io server do not support websocket');

		// compose connection
		$connect = $this->url['host'];
		$default_port = 80;
		if ($this->url['scheme'] == 'https') {
			$connect = 'ssl://'.$connect;
			$default_port = 443;
		}
		if (isset($this->url['port'])) {
			$port = $this->url['port'];
		} else {
			$port = $default_port;
		}

		$this->fd = fsockopen($connect, $port, $errno, $errstr);
		if (!$this->fd) throw new \Exception($errstr);

		// let's use websocket draft-75 because it's simple (framing happens to suck, however)
		// dirty "query in one line"
		fwrite($this->fd, "GET /socket.io/1/websocket/".$this->session[0]." HTTP/1.1\r\nUpgrade: WebSocket\r\nConnection: Upgrade\r\nHost: ".$this->url['host']."\r\nOrigin: *\r\n\r\n");

		$res = fgets($this->fd); // HTTP/1.1 101 Web Socket Protocol Handshake
		if ($res === false) throw new \Exception('socket.io didn\'t like us');
		if (substr($res, 0, 12) != 'HTTP/1.1 101') throw new \Exception('Unexpected answer: '.$res);

		// skip http headers
		while(true) {
			$res = trim(fgets($this->fd));
			if ($res === '') break;
		}

		// socket is now open and working
		if ($this->raw_read() != '1::') throw new \Exception('Server do not report us as connected!');

		$this->stamp = time();
		// connect to the endpoint
		$this->raw_send("1::".$this->url['path']);
	}

	public function send_message($msg) {
		$this->raw_send('3::'.$this->url['path'].':'.$msg);
	}

	public function send_json($obj) {
		$this->raw_send('4::'.$this->url['path'].':'.json_encode($obj));
	}

	public function loop() {
		while(true) {
			if ($this->stamp < (time()-$this->session[1]-5)) {
				// heartbeat time
				$this->raw_send('2');
				$this->stamp = time();
			}

			$r = array($this->fd);
			$w = null; $e = null;
			$n = stream_select($r, $w, $e, 5);
			if ($n == 0) continue;

			$msg = $this->raw_read();
			switch($msg[0]) {
				case '3': // "message"
					if (!isset($this->events['message'])) break; // ignore event if not catched
					$msg = substr($msg, 2); // strip "3:"
					$pos = strpos($msg, ':');
					$msg_id = (string)substr($msg, 0, $pos);
					$msg = substr($msg, 1+$pos);
					$pos = strpos($msg, ':');
					$endpoint = substr($msg, 0, $pos);
					$msg = substr($msg, 1+$pos);
					$this->dispatch('message', array($msg, $endpoint, $msg_id));
					break;
				case '4': // "json message"
					if (!isset($this->events['message'])) break; // ignore event if not catched
					$msg = substr($msg, 2); // strip "4:"
					$pos = strpos($msg, ':');
					$msg_id = (string)substr($msg, 0, $pos);
					$msg = substr($msg, 1+$pos);
					$pos = strpos($msg, ':');
					$endpoint = substr($msg, 0, $pos);
					$msg = json_decode(substr($msg, 1+$pos), true);
					$this->dispatch('message', array($msg, $endpoint, $msg_id));
					break;
			}
		}
	}

	protected function dispatch($callback, $params) {
		if (!isset($this->events[$callback])) return;
		return call_user_func_array($this->events[$callback], $params);
	}

	public function on($event, $callback) {
		$this->events[$event] = $callback;
	}

	protected function raw_read() {
		while(true) {
			if (strlen($this->buf) > 1) {
				if ($this->buf[0] != "\x00") {
					$this->buf = (string)substr($this->buf, 1);
					continue;
				}
				$pos = strpos($this->buf, "\xff");
			} else {
				$pos = false;
			}
			if ($pos === false) {
				$tmp = fread($this->fd, 4096);
				if ($tmp === false) throw new \Exception('Lost connection?');
				$this->buf .= $tmp;
				continue;
			}
			$res = substr($this->buf, 1, $pos-1);
			$this->buf = (string)substr($this->buf, $pos+1);
			return $res;
		}
	}

	protected function raw_send($data) {
		fwrite($this->fd, "\x00".$data."\xff");
	}
}

