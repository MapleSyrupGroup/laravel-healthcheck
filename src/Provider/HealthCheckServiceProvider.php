<?php

namespace MapleSyrupGroup\HealthCheck\Provider;

use Illuminate\Support\ServiceProvider;
use MapleSyrupGroup\HealthCheck\Command\HealthCheckCommand;
use Illuminate\Support\Facades\Route;
use MapleSyrupGroup\HealthCheck\HealthCheck;
use MapleSyrupGroup\HealthCheck\Controller\HealthCheckController;
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

        if(class_exists("Debugbar")) {
            \Debugbar::disable();
        }

        Route::get('healthcheck', '\MapleSyrupGroup\HealthCheck\Controller\HealthCheckController@execute');
    }

}
