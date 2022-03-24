<?php
namespace timer;
require __DIR__ . '/init.php';

class Server{
    public $_context = null;

    public function __construct(){
        $context_option = new array()
        $this->_context = \stream_context_create($context_option);
    }
}

