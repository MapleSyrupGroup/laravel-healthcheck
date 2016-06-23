<?php
namespace MapleSyrupGroup\HealthCheck\Command;

use Illuminate\Console\Command;
use MapleSyrupGroup\HealthCheck\HealthCheck;

/**
 * @package MapleSyrupGroup\Wallet\Commands
 */
class HealthCheckCommand extends Command
{

    protected $description = 'Infra healthcheck';
    protected $signature = "infra:healthcheck";
    public $errors = [];

    public function fire()
    {

        $isProduction = $this->isProduction();
        $this->healthcheck = new HealthCheck($isProduction);

        $this->healthcheck->checkExtensions();
        $this->healthcheck->checkExtensionsConfig();
        $this->healthcheck->checkDatabase();
        $this->healthcheck->checkRabbit(
            getenv('RABBITMQ_HOST'),
            getenv('RABBITMQ_PORT'),
            getenv('RABBITMQ_LOGIN'),
            getenv('RABBITMQ_PASSWORD'),
            getenv('RABBITMQ_VHOST')
        );
        $this->healthcheck->checkRedis();

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
     * @return bool
     */
    public function isProduction()
    {
        return $this->option('env') === 'production';
    }

}

//$walletDrive = Flysystem::connection(config('payments.file_drivers.main_storage'));
//$tmpDir = Flysystem::connection(config('payments.file_drivers.payments_tmp'));
//        var_dump(__METHOD__, get_class(Queue::connection())); exit;
//        var_dump(__METHOD__, Queue::connected()); exit;