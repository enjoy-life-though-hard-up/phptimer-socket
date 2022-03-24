<?php
namespace timer\Lib;

class Event implements LibInterface
{
    /**
     * Event base.
     * @var object
     */
    protected $eventBase = null;


    const DEFAULT_BACKLOG = 102400;

    /**
     * All timer event listeners.
     * [func, args, event, flag, time_interval]
     * @var array
     */
    protected $eventTimer = array();


    protected $_allEvents = array();

    /**
     * Timer id.
     * @var int
     */
    protected static $timerId = 1;


    protected $_mainSocket = null;

    /**
     * construct
     * @return void
     */

    public function __construct()
    {

        if (class_exists('\\\\EventBase', false)) {
            $class_name = '\\\\EventBase';
        } else {
            $class_name = '\EventBase';
        }
        $this->eventBase = new $class_name();
    }

    /**
     * @see EventInterface::add()
     */
    public function add($fd, $func, $flag = true, $args = array())
    {
        if (class_exists('\\\\Event', false)) {
            $class_name = '\\\\Event';
        } else {
            $class_name = '\Event';
        }

        switch($flag){
            case self::EV_TIMER_ONCE:
            case self::EV_TIMER:
                $flag = $flag === self::EV_TIMER ? self::EV_TIMER : self::EV_TIMER_ONCE;
                $param = array($func, (array) $args, $flag, $fd, self::$timerId);
                $event = new $class_name($this->eventBase, -1, $class_name::TIMEOUT | $class_name::READ | $class_name::PERSIST, array($this, "timerCallback"), $param);
                if (!$event || !$event->addTimer($fd)) {
                    return false;
                }
                $this->eventTimer[self::$timerId] = $event;
                return self::$timerId++;
            case self::EV_READ:
                $fd_key = (int)$fd;
                $real_flag = $flag === self::EV_READ ? $class_name::READ | $class_name::PERSIST : $class_name::WRITE | $class_name::PERSIST;
            
                $event = new $class_name($this->eventBase, $fd, $real_flag, $func, $fd);

                if (!$event||!$event->add()) {
                    return false;
                }
                $this->_allEvents[$fd_key][$flag] = $event;
                break;
        }
        
    }


    public function addSocket(){
       
    }

    public function cleanFd($socket){
       \set_error_handler(function(){});
        $new_socket = \stream_socket_accept($socket, 0, $remote_address);
        \restore_error_handler();

        // Thundering herd.
        if (!$new_socket) {
            return;
        }
        var_dump($new_socket);
        echo fread($new_socket, 1024);
    }


    public function del($fd)
    {
        if (isset($this->eventTimer[$fd])) {
            $this->eventTimer[$fd]->del();
            unset($this->eventTimer[$fd]);
        }
        return true;
    }

    /**
     * Timer callback.
     * @param null $fd
     * @param int $what
     * @param int $timer_id
     */
    public function timerCallback($fd, $what, $param)
    {
        $timer_id = $param[4];

        if ($param[2] === self::EV_TIMER_ONCE) {
            $this->eventTimer[$timer_id]->del();
            unset($this->eventTimer[$timer_id]);
        }

        try {
            call_user_func_array($param[0], $param[1]);
        } catch (\Exception $e) {
            exit(250);
        } catch (\Error $e) {
            exit(250);
        }
    }

    /**
     * @see Events\EventInterface::clearAllTimer()
     * @return void
     */
    public function clearAllTimer()
    {
        foreach ($this->eventTimer as $event) {
            $event->del();
        }
        $this->eventTimer = array();
    }

    /**
     * @see EventInterface::loop()
     */
    public function loop()
    {
        var_dump($this->eventBase);
        $this->eventBase->loop();
    }

    /**
     * Get timer count.
     *
     * @return integer
     */
    public function getTimerCount()
    {
        return count($this->eventTimer);
    }
}
