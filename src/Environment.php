<?php
namespace Josegonzalez\Environments;

use Cake\Core\Configure;
use Cake\Core\Exception\Exception;

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
class Environment
{

    public $environments = [];

    protected static $_instance;

    /**
     * Disables directly constructing an Environment instance
     *
     * @return void
     **/
    protected function __construct()
    {
    }

    /**
     * Disables directly cloning an Environment instance
     *
     * @return void
     **/
    protected function __clone()
    {
    }

    /**
     * Retrieves the current instance of an environment
     *
     * @return Josegonzalez\Environments\Environment
     **/
    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new Environment();
            Configure::write('Environment.initialized', true);
        }

        return self::$_instance;
    }

    /**
     * Configures a given environment with certain instructions
     *
     * @param string $name Name of the environment we are checking for
     * @param array $params Array of keys to check
     * @param array $config Array of configure keys
     * @param callable $callable A callable to invoke when an environment matches
     * @return bool
     **/
    public static function configure($name, $params, $config = null, $callable = null)
    {
        $_this = Environment::getInstance();
        $_this->environments[$name] = compact('name', 'params', 'config', 'callable');
    }

    /**
     * Kicks off the environment setup
     *
     * @param string $environment Name of the environment we want to force setup for
     * @param string $default Default environment name
     * @return bool
     **/
    public static function start($environment = null, $default = 'development')
    {
        $_this = Environment::getInstance();
        return $_this->setup($environment, $default);
    }

    /**
     * Checks if the current environment matches the passed environment
     *
     * @param string $environment Name of the environment we are checking for
     * @return bool
     **/
    public static function is($environment = null)
    {
        $current = Configure::read('Environment.name');

        if (! $environment) {
            return $current;
        }

        return $current === $environment;
    }

    /**
     * Configures the current environment
     *
     * @param string $environment Name of the environment we want to force setup for
     * @param string $default Default environment name
     * @return bool
     * @throws Cake\Core\Exception\Exception
     **/
    public function setup($environment = null, $default = 'development')
    {
        if (Configure::read('Environment.setup')) {
            return false;
        }

        $current = $this->currentEnvironment($environment, $default);

        if (!isset($this->environments[$current])) {
            throw new Exception(sprintf('Environment %s does not exist.', $current));
        }

        $config = array_merge(
            $this->environments[$current]['config'],
            ['Environment.name' => $current]
        );
        foreach ($config as $param => $value) {
            Configure::write($param, $value);
        }

        if (is_callable($this->environments[$current]['callable'])) {
            $this->environments[$current]['callable']();
        }

        Configure::write('Environment.setup', true);

        return true;
    }

    /**
     * Gets the current environment
     *
     * @param string $environment Name of the environment we want to force setup for
     * @param string $default Default environment name
     * @return string
     **/
    protected function currentEnvironment($environment = null, $default = 'development')
    {
        $current = ($environment === null) ? $default : $environment;
        if (empty($environment)) {
            foreach ($this->environments as $name => $config) {
                if ($this->_match($name, $config['params'])) {
                    $current = $name;
                    break;
                }
            }
        }

        return $current;
    }

    /**
     * Matches the current setup to a given environment
     *
     * @param string $environment Name of the environment
     * @param array $params Array of keys to check
     * @return bool
     **/
    protected function _match($environment, $params)
    {
        $cakeEnv = env('CAKE_ENV');
        if (!empty($cakeEnv)) {
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
