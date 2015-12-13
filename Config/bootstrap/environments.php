<?php
use Josegonzalez\Environments\Environment;

include dirname(__FILE__) . DS . 'environments' . DS . 'production.php';
include dirname(__FILE__) . DS . 'environments' . DS . 'staging.php';
include dirname(__FILE__) . DS . 'environments' . DS . 'development.php';

// run

Environment::start();
