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
        // These are production only checks, so if we're not on prod, we bail out.
        if(!$this->isProduction) {
            $this->addSuccessMessage('PHP Extension Config');
            return;
        }

        // Disabled Extensions
        $disabledExtensions = ['xdebug', 'blackfire'];
        foreach($disabledExtensions as $ext) {
            if(extension_loaded($ext)) {
                $this->addFailureMessage('Extension ' . $ext . ' should not be enabled');
            }
        }

        // Opcache check - check enabled config setting
        if(ini_get('opcache.enable') == 0) {
            $this->addFailureMessage('Opcache is not enabled');
        }

        $this->addSuccessMessage('PHP Extension Config');
    }


    public function checkRabbit($host, $port, $login, $password, $vhost)
    {
        try {

            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                throw new \Exception($errstr);
            });

            $conn = new AMQPSocketConnection(
                $host, $port, $login, $password, $vhost
            );

            if(!$conn->isConnected()) {
                throw new \Exception();
            }

        } catch(\Exception $e) {
            $msg = !empty($e->getMessage()) ? ' Reason: ' . $e->getMessage() : '';
            $this->addFailureMessage('Rabbit connection failed' . $msg);
        }
        restore_error_handler();

    }

    public function checkCache()
    {
        $stores = Config::get('cache.stores');
        foreach($stores as $storeKey => $store) {
            if(!isset($store['driver'])) {
                $this->addFailureMessage('Missing driver for cache store: ' . $storeKey);
                return;
            }

            $cacheDriver = $store['driver'];
            try {
                Cache::store($store['driver'])->has('item');
                $this->addSuccessMessage('Cache connection for driver: ' . $cacheDriver);
            } catch(\Exception $e) {
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

        $extensions = ['apcu', 'pdo', 'pdo_mysql', 'curl', 'gd', 'mbstring',  'mcrypt', 'soap', 'xml'];
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

        $this->addSuccessMessage('PHP Extensions');
    }

    public function checkDatabase()
    {
        // @todo - get all connection configs
        try {
            DB::connection()->getDatabaseName();
            $this->addSuccessMessage('MySQL Connection');
        } catch (\Exception $e) {
            $this->addFailureMessage("Can't connect to DB. Reason: " . $e->getMessage());
        }
    }

    /**
     * @param string $type
     * @param string $message
     * @throws \Exception
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
                throw new \Exception('Unknown error type added: ' . $type);
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
     * @throws \Exception
     */
    private function addSuccessMessage($message)
    {
        $this->addMessage(self::MESSAGE_TYPE_SUCCESS, $message);
    }

    /**
     * @param string $message
     * @throws \Exception
     */
    private function addFailureMessage($message)
    {
        $this->addMessage(self::MESSAGE_TYPE_FAILURE, $message);
    }

<<<<<<< HEAD
=======
    public function checkSession()
    {
        if (session_status() !== PHP_SESSION_NONE) {
            $this->addFailureMessage('Sessions are initialized');
            return;
        }
        $this->addSuccessMessage('Sessions are disabled');
    }

>>>>>>> feature/all-cache-and-sessions
}
