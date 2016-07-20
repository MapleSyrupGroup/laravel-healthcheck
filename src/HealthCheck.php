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
use Illuminate\Database\Migrations\Migrator;

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
     * @var Migrator
     */
    private $migrator;

    public function __construct(Migrator $migrator)
    {
        $this->migrator = $migrator;
    }

    /**
     * @param bool $isProduction
     */
    public function setIsProduction($isProduction)
    {
        $this->isProduction = $isProduction;
    }


    /**
     * Checking if an extension is installed is not enough, we have configs we would like to inspect too.
     * Such as opcache.enable=1
     */
    public function checkExtensionsConfig()
    {

        // Disabled Extensions
        $disabledExtensions = ['xdebug', 'blackfire'];
        foreach ($disabledExtensions as $ext) {
            if (extension_loaded($ext)) {
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

    /**
     * Make an AMQP connection to our rabbit server
     *
     */
    public function checkRabbit()
    {

        list($host, $port, $login, $password, $vhost) = [
            getenv('RABBITMQ_HOST'), getenv('RABBITMQ_PORT'), getenv('RABBITMQ_LOGIN'),
            getenv('RABBITMQ_PASSWORD'), getenv('RABBITMQ_VHOST')
        ];

        try {
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                throw new Exception($errstr);
            });

            $conn = new AMQPSocketConnection($host, $port, $login, $password, $vhost);

            if (!$conn->isConnected()) {
                throw new Exception();
            }
        } catch (Exception $e) {
            $msg = !empty($e->getMessage()) ? ' Reason: ' . $e->getMessage() : '';
            $this->addFailureMessage('RabbitMQ connection failed' . $msg);
        }
        restore_error_handler();
    }

    /**
     * All cache backends in cache.php are being connected to here.
     */
    public function checkCache()
    {
        $stores = Config::get('cache.stores');

        if ($stores === null || empty($stores)) {
            $this->addSuccessMessage('No cache config found. Nothing to check');
            return;
        }

        foreach ($stores as $storeKey => $store) {
            if (!isset($store['driver'])) {
                $this->addFailureMessage('Missing driver for cache store: ' . $storeKey);
                return;
            }

            $cacheDriver = $store['driver'];
            try {
                // This is us now probing the cache backend, on a cheap has() call.
                Cache::store($store['driver'])->has('item');
                $this->addSuccessMessage('Cache connection for driver: ' . $cacheDriver);
            } catch (Exception $e) {
                $this->addFailureMessage(
                    sprintf("Cache connection for driver: %s. Reason: %s", $cacheDriver, $e->getMessage())
                );
                continue;
            }
        }
    }

    /**
     * Our mandatory list of php extensions here are being checked
     */
    public function checkExtensions()
    {
        $extensions = ['pdo', 'pdo_mysql', 'curl', 'gd', 'mbstring', 'mcrypt', 'soap', 'xml'];
        if ($this->isProduction) {
            $extensions += ['gnupg', 'newrelic'];
        }

        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                continue;
            }
            $this->addFailureMessage('Extension: ' . $ext . ' not loaded. Enable it!');
            return;
        }

        $this->addSuccessMessage('PHP Extensions: ' . implode(', ', $extensions));
    }

    /**
     * All database backends in database.php are being connected to here.
     */
    public function checkDatabase()
    {
        $connections = Config::get('database.connections');

        if ($connections === null || empty($connections)) {
            $this->addSuccessMessage('No database config found. Nothing to check');
            return;
        }

        foreach ($connections as $connectionKey => $connection) {
            if (!isset($connection['driver'])) {
                $this->addFailureMessage('Missing driver for database connection: ' . $connectionKey);
                return;
            }

            try {
                DB::connection($connectionKey)->getDatabaseName();
                $this->addSuccessMessage(sprintf('Database connection %s', $connectionKey));
            } catch (Exception $e) {
                $this->addFailureMessage(
                    sprintf("Database connection: %s. Unable to connect. Reason: %s", $connectionKey, $e->getMessage())
                );
            }
        }

        try {
            // Check default configured database name
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
        switch ($type) {
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

    /**
     * Sessions are not allowed on qplatform! So we make sure of this.
     */
    public function checkSession()
    {
        if (session_status() !== PHP_SESSION_NONE) {
            $this->addFailureMessage('Sessions are initialized');
            return;
        }
        $this->addSuccessMessage('Sessions are disabled');
    }

    /**
     * Some directories are required to be writeable by the fpm user. Let's check them.
     */
    public function checkPermissions()
    {
        $writeableDirectories = [
            base_path('bootstrap/cache'),
            storage_path('logs/laravel.log'),
            storage_path('app'),
            storage_path('framework/cache')
        ];

        foreach ($writeableDirectories as $dir) {
            if (!is_writable($dir)) {
                $this->addFailureMessage(sprintf('Directory %s is not writeable', $dir));
                continue;
            }
            $this->addSuccessMessage(sprintf('Directory %s is writeable', $dir));
        }
    }

    /**
     * Here we check migrations for the default configured connection
     */
    public function checkMigrations()
    {
        $migrationsBasePath = base_path('database/migrations');
        $ran = $this->migrator->getRepository()->getRan();
        $migrationFiles = $this->migrator->getMigrationFiles($migrationsBasePath);

        foreach ($migrationFiles as $migration) {
            $isRan = in_array($migration, $ran);
            if (!$isRan) {
                $this->addFailureMessage(sprintf('Migration: %s has not been ran', $migration));
                continue;
            }
            $this->addSuccessMessage(sprintf('Migration: %s has been ran', $migration));
        }
    }
}
