<?php
namespace timer;

require __DIR__ . '/init.php';

use timer\Timer;

class Daemon
{
    public static $stdoutFile = '/tmp/null';
    public static $daemonName = 'daemon php';
    protected static $OS      = OS_TYPE_LIN;

    protected static $_outputStream = null;

    public static function runAll()
    {
        self::checkEnvCli(); //检查环境

        //如果是win
        if (static::$OS !== OS_TYPE_LIN) {
            return Timer::factory();
        }
        //self::daemonize(); //守护进程化
        //self::chdir(); //改变工作目录
        //self::closeSTD(); //关闭标准输出、标准错误
        //self::setProcessTitle(self::$daemonName); //设置守护进程的名字
        return Timer::factory();
    }

    /**
     * 检测执行环境，必须是linux系统和cli方式执行
     * @return [type] [description]
     */
    protected static function checkEnvCli()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::$OS = OS_TYPE_WIN;
        }

        if (php_sapi_name() != "cli") {
            exit("only run in command line mode \n");
        }
    }

    /**
     * 设置掩码、fork两次、设置会话组长
     * @return [type] [description]
     */
    protected static function daemonize()
    {
        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (-1 === posix_setsid()) {
            throw new Exception("setsid fail");
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new Exception("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }
    }

    /**
     * 改变工作目录
     * @return [type] [description]
     */
    protected static function chdir()
    {
        if (!chdir('/')) {
            throw new Exception("change dir fail", 1);
        }
    }

    /**
     * 关闭标准输出、标准错误
     * @return [type] [description]
     */
    protected static function closeSTD()
    {
        global $STDOUT, $STDERR;
        $handle = \fopen(static::$stdoutFile, "a");
        if ($handle) {
            unset($handle);
            \set_error_handler(function(){});
        /*    if ($STDOUT) {
                \fclose($STDOUT);
            }
            if ($STDERR) {
                \fclose($STDERR);
            }*/
/*            \fclose(\STDOUT);
            \fclose(\STDERR);*/
            $STDOUT = \fopen(static::$stdoutFile, "a");
            $STDERR = \fopen(static::$stdoutFile, "a");
            // change output stream
            static::$_outputStream = null;
            //static::outputStream($STDOUT);
            \restore_error_handler();
            return;
        }

        throw new Exception('Can not open stdoutFile ' . static::$stdoutFile);
    }


    private static function outputStream($stream = null)
    {
        if (!$stream) {
            $stream = static::$_outputStream ? static::$_outputStream : \STDOUT;
        }
        if (!$stream || !\is_resource($stream) || 'stream' !== \get_resource_type($stream)) {
            return false;
        }
        $stat = \fstat($stream);
        if (!$stat) {
            return false;
        }
        if (($stat['mode'] & 0170000) === 0100000) {
            // file
            static::$_outputDecorated = false;
        } else {
            static::$_outputDecorated =
                static::$_OS === \OS_TYPE_LINUX &&
                \function_exists('posix_isatty') &&
                \posix_isatty($stream);
        }
        return static::$_outputStream = $stream;
    }


    /**
     * 设置定时器名字
     *
     * @param string $title
     * @return void
     */
    protected static function setProcessTitle($title)
    {
        set_error_handler(function () {});
        // >=php 5.5
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        } // Need proctitle when php<=5.5 .
        elseif (extension_loaded('proctitle') && function_exists('setproctitle')) {
            setproctitle($title);
        }
        restore_error_handler();
    }

    /**
     * 返回当前执行环境
     * @return [type] [description]
     */
    public static function getOS()
    {
        return self::$OS;
    }
}
