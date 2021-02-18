<?php

namespace Tests;

use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        Hash::setRounds(4);

        return $app;
    }

    /**
     * Reset all mysql database tables increments.
     *
     * @return void
     */
    public function resetDatabaseTablesIncrements()
    {
        $tables = collect(DB::select('SHOW TABLES'));

        $tables->each(function ($table) {
            $tableName = collect($table)->first();

            DB::statement("ALTER TABLE $tableName AUTO_INCREMENT = 1");
        });
    }

    /**
     * Clear Redis Test Database.
     */
    public function clearRedis()
    {
        Redis::flushdb();
    }
}
