<?php
/**
 * Singleton class to handle environment specific configurations.
 *
 * Auto-detect environment based on specific configured params and
 * allow per environment configuration and environment emulation.
 *
 * Environment. Smart Environment Handling.
 * Copyright 2008 Rafael Bandeira - rafaelbandeira3
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 */
class Environment {

	public $environments = array();

	protected static $_instance;

	protected $_configMap = array(
		'security' => 'Security.level'
	);

	public static function &getInstance() {
		if (! self::$_instance) {
			$Environment = 'Environment';
			if (config('app_environment')) {
				$Environment = 'App' . $Environment;
			}

			self::$_instance = new $Environment();
			Configure::write('Environment.initialized', true);
		}

		return self::$_instance;
	}

	public static function configure($name, $params, $config = null, $callable = null) {
		$_this = Environment::getInstance();
		$_this->environments[$name] = compact('name', 'params', 'config', 'callable');
	}

	public static function start($environment = null, $default = 'development') {
		$_this =& Environment::getInstance();
		return $_this->setup($environment, $default);
	}

	public static function is($environment = null) {
		$current = Configure::read('Environment.name');

		if (! $environment) {
			return $current;
		}

		return $current === $environment;
	}

	protected function __construct() {}
	protected function __clone() { }

	public function setup($environment = null, $default = 'development') {
		if (Configure::read('Environment.setup')) {
			return false;
		}

		$current = ($environment === null) ? $default : $environment;
		if (empty($environment)) {
			foreach ($this->environments as $name => $config) {
				if ($this->_match($name, $config['params'])) {
					$current = $name;
					break;
				}
			}
		}

		if (!isset($this->environments[$current])) {
			throw new CakeException('Environment ' . $current . ' does not exist.');
		}

		$config = array_merge(
			$this->environments[$current]['config'],
			array('Environment.name' => $current)
		);
		foreach ($config as $param => $value) {
			if (isset($this->_configMap[$param])) {
				$param = $this->_configMap[$param];
			}
			Configure::write($param, $value);
		}

		if (is_callable($this->environments[$current]['callable'])) {
			$this->environments[$current]['callable']();
		}

		if (Configure::read('debug') > 0) {
			App::uses('CakeLog', 'Log');
			if (class_exists('CakeLog')) {
				CakeLog::write(LOG_INFO, $current);
				Configure::write('Environment.setup', true);
			}
		}

		return true;
	}

	protected function _match($environment, $params) {
		$_cake_env = env('CAKE_ENV');
		if (!empty($_cake_env)) {
			return env('CAKE_ENV') == $environment;
		}

		if (is_bool($params)) {
			return $params;
		}

		if (is_callable($params) || (is_string($params) && function_exists($params))) {
			return $params();
		}

		foreach ($params as $param => $value) {
			if (function_exists($param)) {
				$match = call_user_func($param, $value);
			} elseif (is_array($value)) {
				$match = in_array(env($param), $value);
			} else {
				$match = (env($param) === $value);
			}

			if (!$match) {
				return false;
			}
		}
		return true;
	}

}
