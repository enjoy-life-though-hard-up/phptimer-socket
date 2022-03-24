<?php
require __DIR__ . '/vendor/autoload.php';
use timer\Daemon;

class work{
    public static $timer = null;

    public $_mainSocket = null;

    const DEFAULT_BACKLOG = 102400;


    public $context_option = null;


    protected $transport = 'tcp';


    public function __construct(){
        static::$timer = Daemon::runAll();
        if (!isset($context_option['socket']['backlog'])) {
            $context_option['socket']['backlog'] = static::DEFAULT_BACKLOG;
        }
        $this->_context = \stream_context_create($context_option);
    }


    public function addWork($address){
        $errno = 0;
        $errmsg = '';

        \stream_context_set_option($this->_context, 'socket', 'so_reuseport', 1);


        $flags = $this->transport === 'udp' ? \STREAM_SERVER_BIND : \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN;


        $this->_mainSocket = \stream_socket_server($address, $errno, $errmsg, $flags, $this->_context);
        if (!$this->_mainSocket) {
           throw new Exception($errmsg);
        }
        \set_error_handler(function(){});
        $socket = \socket_import_stream($this->_mainSocket);
        \socket_set_option($socket, \SOL_SOCKET, \SO_KEEPALIVE, 1);
        \socket_set_option($socket, \SOL_TCP, \TCP_NODELAY, 1);
        \restore_error_handler();


        \stream_set_blocking($this->_mainSocket, false);

        $fd = $this->_mainSocket;

        static::$timer->add($fd,array($this,"cleanTcp"),3);
    }

    public function addTimer($time,$func,$flag){
        static::$timer->add($time,$func,$flag);
    }


    public function cleanTcp($socket){

         \set_error_handler(function(){});
        $new_socket = \stream_socket_accept($socket, 0, $remote_address);
        \restore_error_handler();

        // Thundering herd.
        if (!$new_socket) {
            return;
        }
        echo "recv message".fread($new_socket,1024);
    }

    public function loop(){
        static::$timer->loop();
    }
}


//测试执行 timer类
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return bcadd($usec, $sec, 3);
}

$work = new work();
$work->addTimer(0.5, function () {
 
    if (Daemon::getOS() === OS_TYPE_WIN) {
        echo microtime_float() . "\n";
    } else {
        file_put_contents("/tmp/test.txt", microtime_float() . "\n", FILE_APPEND);
    }
},1);

$work->addTimer(1, function () {
 
    if (Daemon::getOS() === OS_TYPE_WIN) {
        echo microtime_float() . "once \n";
    } else {
        file_put_contents("/tmp/test.txt", microtime_float() . "once \n", FILE_APPEND);
    }
}, 2);

$work->addWork("tcp://127.0.0.1:9098");

$work->loop();

