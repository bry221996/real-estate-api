<?php

use Illuminate\Foundation\Inspiring;

Artisan::command('cc:db-reset', function () {
    $this->call('migrate:fresh');
    $this->call('db:seed');
})->describe('Reset database with default data');
