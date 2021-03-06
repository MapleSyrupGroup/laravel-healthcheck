# Laravel Healthcheck Package

This package will scan your laravel 5.x's application config files and identify key dependencies of your application. It will check:

- cache, database, queue backends can be connected to.
- your required php version and php extensions
- that all migrations have been run
- that important directories are writable
- sessions are disabled

[![Build Status](https://travis-ci.org/MapleSyrupGroup/laravel-healthcheck.svg)](https://travis-ci.org/MapleSyrupGroup/laravel-healthcheck)

### How to execute from command line

``` php
php artisan infra:healthcheck

[OK] PHP Extensions
[OK] PHP Extension Config
[OK] Database connection user
[OK] Database connection content
[OK] Default database connection found
[OK] Sessions are disabled
[OK] Cache connection for driver: array
[OK] Cache connection for driver: file
[OK] Cache connection for driver: redis

```


### How to execute from HTTP

A `/healthcheck` URI is configured to trigger this from a HTTP context

```
http://some.app/healthcheck

[OK] PHP Extensions
[OK] PHP Extension Config
[OK] Database connection user
[OK] Database connection content
[OK] Default database connection found
[OK] Sessions are disabled
[OK] Cache connection for driver: array
[OK] Cache connection for driver: file
[OK] Cache connection for driver: redis

```


### Production vs Development modes

There are some production-style checks such as `xdebug` being disabled.  

If you wish to run this on a a local environment then you need to pass additional arguments.

**HTTP**
Value: `prod`
Default: `true`
Usage: `?prod=false` or `?prod=true`

**CLI**
Value: `env`
Default: `false`
Usage: `--env=production`

