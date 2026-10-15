<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static $databaseTestLock;

    public function createApplication()
    {
        if (! self::$databaseTestLock) {
            self::$databaseTestLock = fopen(sys_get_temp_dir().'/holatales-unit-20261014.lock', 'c');
            if (! flock(self::$databaseTestLock, LOCK_EX | LOCK_NB)) {
                throw new \RuntimeException('Disposable database is in use by another test or live QA process.');
            }
        }
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.'.$connection.'.database');
        if ($database !== 'holatales_unit_20261014' && ! ($connection === 'sqlite' && $database === ':memory:')) {
            throw new \RuntimeException('Tests require the disposable holatales_unit_20261014 database or SQLite :memory:.');
        }

        return $app;
    }
}
