<?php

namespace MapleSyrupGroup\HealthCheck\Provider;

use Illuminate\Support\ServiceProvider;
use MapleSyrupGroup\HealthCheck\Command\HealthCheckCommand;
use Illuminate\Support\Facades\Route;
use MapleSyrupGroup\HealthCheck\HealthCheck;
use MapleSyrupGroup\HealthCheck\Controller\HealthCheckController;

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
        $this->app->bind(HealthCheck::class, function ($app) {
            $migrator = $app['migrator'];
            return new HealthCheck($migrator);
        });
        $this->commands([HealthCheckCommand::class]);
    }

    public function boot()
    {
        if (class_exists("Debugbar")) {
            \Debugbar::disable();
        }

        Route::get(
            'healthcheck',
            '\MapleSyrupGroup\HealthCheck\Controller\HealthCheckController@executeReadiness'
        );
        Route::get(
            'healthcheck-readiness',
            '\MapleSyrupGroup\HealthCheck\Controller\HealthCheckController@executeReadiness'
        );
        Route::get(
            'healthcheck-liveness',
            '\MapleSyrupGroup\HealthCheck\Controller\HealthCheckController@executeLiveness'
        );
    }

    public function provides()
    {
        return ['healthcheck'];
    }
}
