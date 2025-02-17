<?php

namespace AesirCloud\LaravelDomains\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use AesirCloud\LaravelDomains\Providers\DomainServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        // Register your package's service provider so commands & stubs are available
        return [
            DomainServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Optionally configure environment. For example, an in-memory SQLite DB:
        // $app['config']->set('database.default', 'testdb');
        // $app['config']->set('database.connections.testdb', [
        //     'driver'   => 'sqlite',
        //     'database' => ':memory:',
        //     'prefix'   => '',
        // ]);
    }
}
