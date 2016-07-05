<?php

namespace MapleSyrupGroup\HealthCheck;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;

use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Console\Command;
use Log;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use Exception;

class HealthCheck
{

    const MESSAGE_TYPE_SUCCESS = 'success';
    const MESSAGE_TYPE_FAILURE = 'failure';

    /**
     * @var array
     */
    private $messages = [];

    /**
     * @var bool
     */
    private $isProduction = false;

    /**
     * HealthCheck constructor.
     * @param bool $isProduction
     */
    public function __construct($isProduction)
    {
        $this->isProduction = $isProduction;

    }

    public function checkExtensionsConfig()
    {

        // Disabled Extensions
        $disabledExtensions = ['xdebug', 'blackfire'];
        foreach($disabledExtensions as $ext) {
            if(extension_loaded($ext)) {
                $this->addFailureMessage('PHP Extension: ' . $ext . ' is enabled');
                continue;
            }
            $this->addSuccessMessage('PHP Extension ' . $ext . ' is disabled');
        }

        // Opcache check - check enabled config setting
        ini_get('opcache.enable') == 0
            ? $this->addFailureMessage('Opcache is disabled')
            : $this->addSuccessMessage('Opcache is enabled');

    }


    public function checkRabbit($host, $port, $login, $password, $vhost)
    {
        try {

            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                throw new Exception($errstr);
            });

            $conn = new AMQPSocketConnection(
                $host, $port, $login, $password, $vhost
            );

            if(!$conn->isConnected()) {
                throw new Exception();
            }

        } catch(Exception $e) {
            $msg = !empty($e->getMessage()) ? ' Reason: ' . $e->getMessage() : '';
            $this->addFailureMessage('RabbitMQ connection failed' . $msg);
        }
        restore_error_handler();

    }

    public function checkCache()
    {
        $stores = Config::get('cache.stores');

        if($stores === null || empty($stores)) {
            $this->addSuccessMessage('No cache config found. Nothing to check');
            return;
        }

        foreach($stores as $storeKey => $store) {
            if(!isset($store['driver'])) {
                $this->addFailureMessage('Missing driver for cache store: ' . $storeKey);
                return;
            }

            $cacheDriver = $store['driver'];
            try {
                Cache::store($store['driver'])->has('item');
                $this->addSuccessMessage('Cache connection for driver: ' . $cacheDriver);
            } catch(Exception $e) {
                $this->addFailureMessage(sprintf("Cache connection for driver: %s. Reason: %s", $cacheDriver, $e->getMessage()));
                continue;
            }
        }
    }

    public function checkExtensions()
    {
        // Taken from production docker file
//      php56w php56w-cli php56w-bcmath php56w-common php56w-gd php56w-mbstring php56w-mysqlnd \
//      php56w-opcache php56w-pdo php56w-process  php56w-xml php56w-xmlrpc php56w-fpm php56w-mcrypt \
//      php56w-pecl-memcache php56w-soap  php56w-xml php56w-xmlrpc php56w-pdo newrelic-php5 \
//      php56w-pecl-gnupg-geterrorinfo

        $extensions = ['pdo', 'pdo_mysql', 'curl', 'gd', 'mbstring',  'mcrypt', 'soap', 'xml'];
        if($this->isProduction) {
            $extensions += ['gnupg', 'newrelic'];
        }

        foreach($extensions as $ext) {
            if(extension_loaded($ext)) {
                continue;
            }
            $this->addFailureMessage('Extension: ' . $ext . ' not loaded. Enable it!');
            return;
        }

        $this->addSuccessMessage('PHP Extensions: ' . implode(', ', $extensions));
    }

    public function checkDatabase()
    {
        // @todo - get all connection configs
        $connections = Config::get('database.connections');

        if($connections === null || empty($connections)) {
            $this->addSuccessMessage('No database config found. Nothing to check');
            return;
        }

        foreach($connections as $connectionKey => $connection) {

            if(!isset($connection['driver'])) {
                $this->addFailureMessage('Missing driver for database connection: ' . $connectionKey);
                return;
            }

            try {
                // @todo - change from Store() to something DB specific - perhaps a connect() method
                DB::connection($connectionKey)->getDatabaseName();
                $this->addSuccessMessage(sprintf('Database connection %s', $connectionKey));
            } catch(Exception $e) {
                $this->addFailureMessage(sprintf("Database connection: %s. Unable to connect. Reason: %s", $connectionKey, $e->getMessage()));
            }
        }

        try {
            // Check default configureds
            DB::connection()->getDatabaseName();
            $this->addSuccessMessage('Default database connection found');
        } catch (Exception $e) {
            $this->addFailureMessage("Default database connection not found. Reason: " . $e->getMessage());
        }
    }

    /**
     * @param string $type
     * @param string $message
     * @throws Exception
     */
    private function addMessage($type, $message)
    {
        switch($type) {
            case self::MESSAGE_TYPE_SUCCESS:
                $this->messages[] = [self::MESSAGE_TYPE_SUCCESS, '[OK] ' . $message];
                break;
            case self::MESSAGE_TYPE_FAILURE:
                $this->messages[] = [self::MESSAGE_TYPE_FAILURE, '[FAIL] ' . $message];
                break;
            default:
                throw new Exception('Unknown error type added: ' . $type);
                break;
        }
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param string $message
     * @throws Exception
     */
    private function addSuccessMessage($message)
    {
        $this->addMessage(self::MESSAGE_TYPE_SUCCESS, $message);
    }

    /**
     * @param string $message
     * @throws Exception
     */
    private function addFailureMessage($message)
    {
        $this->addMessage(self::MESSAGE_TYPE_FAILURE, $message);
    }

    public function checkSession()
    {
        if (session_status() !== PHP_SESSION_NONE) {
            $this->addFailureMessage('Sessions are initialized');
            return;
        }
        $this->addSuccessMessage('Sessions are disabled');
    }
}
