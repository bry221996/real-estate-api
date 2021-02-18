<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;

class PropertyDeveloperTest extends TestCase
{
    use RefreshDatabase, TestHelperTrait;

    public function setUp()
    {
        parent::setUp();
    
        \Artisan::call('db:seed', [
            '--class' => 'DefaultsSeeder'
        ]);

        \Artisan::call('db:seed', [
            '--class' => 'TestSeeder'
        ]);

        $this->beforeApplicationDestroyed(function () {
            $this->resetDatabaseTablesIncrements();
        });
    }

    /**
     * Get list of available developer.
     */
    public function testListDevelopers()
    {
        $response = $this->json('GET', '/api/v1/developers', []);
        
        $response->assertStatus(200);

        $responseData = data_get($response->json(), 'data');

        $this->assertTrue(! empty($responseData));
    }
}
