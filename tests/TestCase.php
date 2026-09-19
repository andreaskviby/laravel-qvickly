<?php

namespace Andreaskviby\Qvickly\Tests;

use Andreaskviby\Qvickly\QvicklyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const SECRET = 'test-secret-key';

    protected function getPackageProviders($app): array
    {
        return [QvicklyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('qvickly.id', '21912');
        $app['config']->set('qvickly.secret', self::SECRET);
        $app['config']->set('qvickly.test', true);
    }
}
