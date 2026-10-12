<?php

namespace Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();

        // Set before any Redis connection opens: parallel processes share the test indexes.
        $application->make(Repository::class)->set('database.redis.options.prefix', testRedisPrefix());

        return $application;
    }
}
