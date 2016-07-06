<?php
namespace MapleSyrupGroup\HealthCheck\Command;

use Illuminate\Console\Command;
use MapleSyrupGroup\HealthCheck\HealthCheck;
use Illuminate\Database\Migrations\Migrator;

class HealthCheckCommand extends Command
{

    protected $description = 'Infra healthcheck';
    protected $signature = "infra:healthcheck";
    public $errors = [];

    /**
     * @var HealthCheck
     */
    private $healthcheck;

    /**
     * HealthCheckCommand constructor.
     * @param HealthCheck $healthCheck
     */
    public function __construct(HealthCheck $healthCheck)
    {
        parent::__construct();
        $this->healthcheck = $healthCheck;
    }

    public function fire()
    {
        $this->healthcheck->setIsProduction($this->isProduction());
        $this->healthcheck->checkExtensions();
        $this->healthcheck->checkExtensionsConfig();
        $this->healthcheck->checkSession();
        $this->healthcheck->checkPermissions();
        $this->healthcheck->checkDatabase();
        $this->healthcheck->checkRabbit();
        $this->healthcheck->checkCache();
        $this->healthcheck->checkMigrations();

        // Begin outputting console messages
        foreach($this->healthcheck->getMessages() as $message) {
            list($messageType, $text) = $message;
            if($messageType === HealthCheck::MESSAGE_TYPE_FAILURE) {
                $this->error($text);
            }
            if($messageType === HealthCheck::MESSAGE_TYPE_SUCCESS) {
                $this->info($text);
            }
        }
    }

    /**
     * If 'env' is not passed, we default it to null, thus the null check.
     *
     * @return bool
     */
    public function isProduction()
    {
        return $this->option('env') === null || $this->option('env') === 'production';
    }
    
}