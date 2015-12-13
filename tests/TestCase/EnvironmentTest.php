<?php
namespace Josegonzalez\Environments\Test\TestCase;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Josegonzalez\Environments\Environment;

class EnvironmentTest extends TestCase
{

    public function setUp()
    {
        parent::setUp();

        Configure::write('debug', 0);
        $this->Environment = Environment::getInstance();

        $envs = [
            [
                'name' => 'staging',
                'params' => [
                    'SERVER_NAME' => 'example.tld'
                ],
                'config' => [
                    'debug' => 2,
                    'Session.name' => 'staging-session',
                    'security' => 'low'
                ],
                'callable' => null
            ],
            [
                'name' => 'production',
                'params' => [
                    'SERVER_NAME' => 'production.tld',
                    'SERVER_ADDR' => '8.8.8.8'
                ],
                'config' => [
                    'debug' => 1,
                    'Session.name' => 'production-session'
                ],
                'callable' => null
            ],
            [
                'name' => 'preproduction',
                'params' => [
                    'SERVER_NAME' => ['preproduction.tld', 'preprod.local'],
                ],
                'config' => [
                    'debug' => 1,
                    'Session.name' => 'preproduction-session'
                ],
                'callable' => function () {
                    Configure::write('Environment.callback', true);
                }
            ],
            [
                'name' => 'dev1',
                'params' => false,
                'config' => [],
                'callable' => null
            ],
            [
                'name' => 'dev2',
                'params' => [
                    'is_bool' => 'Hello, World!'
                ],
                'config' => [],
                'callable' => [],
            ]
        ];

        foreach ($envs as $env) {
            Environment::configure($env['name'], $env['params'], $env['config'], $env['callable']);
        }

        Configure::read('Environment.setup', false);
        $_SERVER['CAKE_ENV'] = null;
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->Environment, $_SERVER['CAKE_ENV']);
    }

    public function testConfigure()
    {
        $this->assertArrayHasKey('staging', $this->Environment->environments);
        $this->assertArrayHasKey('production', $this->Environment->environments);
    }

    /**
     * @expectedException Cake\Core\Exception\Exception
     * @expectedExceptionMessage Environment development does not exist.
     */
    public function testStart()
    {
        Environment::start();
    }

    /**
     * Test whether the environment falls back to default, if nothing is matched
     */
    public function testStartDefault()
    {
        Environment::start(null, 'staging');
        $this->assertEquals('staging', Configure::read('Environment.name'));
    }

    /**
     * Test that the environment setup returns false, as the setup is finished already.
     */
    public function testStartSetupFinished()
    {
        Configure::write('Environment.setup', true);
        $this->assertFalse(Environment::start());
    }

    /**
     * Test that the environment falls back to staging, since one of the
     * config attributes doesn't match
     */
    public function testStartFalseAttribute()
    {
        $_SERVER['SERVER_NAME'] = 'production.tld';
        $_SERVER['SERVER_ADDR'] = '255.255.255.255';

        Environment::start(null, 'staging');
        $this->assertEquals('staging', Configure::read('Environment.name'));
    }

    /**
     * Testing in_array in config array
     */
    public function testStartInArray()
    {
        Configure::write('Environment.callback', false);
        $_SERVER['SERVER_NAME'] = 'preprod.local';
        Environment::start();

        $this->assertEquals('preproduction', Configure::read('Environment.name'));
        $this->assertEquals('preproduction', Environment::is());
        $this->assertTrue(Environment::is('preproduction'));
        $this->assertEquals('preproduction-session', Configure::read('Session.name'));
        $this->assertTrue(Configure::read('Environment.callback'));
    }

    /**
     * Test whether the CAKE_ENV works
     */
    public function testStartEnv()
    {
        $_SERVER['CAKE_ENV'] = 'production';
        Environment::start(null, 'staging');

        $this->assertTrue(Environment::is('production'));
    }

    /**
     * Test the bool attribute
     */
    public function testStartBool()
    {
        Environment::configure('dev1', true, [], null);
        Environment::start(null, 'staging');
        $this->assertEquals('dev1', Environment::is());
    }

    /**
     * Test whether functions in config works
     */
    public function testStartFunctions()
    {
        Environment::configure('dev2', [
            'is_bool' => false
        ], [], null);
        Environment::start(null, 'staging');
        $this->assertEquals('dev2', Environment::is());
    }
}
