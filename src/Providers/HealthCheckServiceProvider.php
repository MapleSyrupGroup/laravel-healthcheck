<?php

namespace MapleSyrupGroup\Wallet\Providers;

use Illuminate\Support\ServiceProvider;
use MapleSyrupGroup\Wallet\Console\Commands\HealthCheckCommand;
use Illuminate\Support\Facades\Route;
use MapleSyrupGroup\Wallet\Util\HealthCheck;
//use DebugBar\DebugBar;

/**
 * Class WalletServiceProvider
 * @package MapleSyrupGroup\Wallet
 */
class HealthCheckServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        $this->commands([HealthCheckCommand::class]);
    }

    public function boot()
    {

        \Debugbar::disable();

        Route::get('healthcheck', function () {

            $httpResponseContent = '';
            $isProd = false;
            $healthcheck = new HealthCheck($isProd);

            $healthcheck->checkExtensions();
            $healthcheck->checkExtensionsConfig();
            $healthcheck->checkDatabase();
            $healthcheck->checkRabbit(
                getenv('RABBITMQ_HOST'),
                getenv('RABBITMQ_PORT'),
                getenv('RABBITMQ_LOGIN'),
                getenv('RABBITMQ_PASSWORD'),
                getenv('RABBITMQ_VHOST')
            );
            $healthcheck->checkRedis();

            // Begin processing console messages
            $errorsOccurred = false;
            foreach($healthcheck->getMessages() as $message) {
                list($messageType, $text) = $message;
                if($messageType === HealthCheck::MESSAGE_TYPE_FAILURE) {
                    $errorsOccurred = true;
                }
                $httpResponseContent .= "$text\n";
            }

            if($errorsOccurred) {
                http_response_code(500);
            }

            return Response($httpResponseContent);
        });
    }

}
