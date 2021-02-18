<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;

class PropertyFeatureTest extends TestCase
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
     * Test adding of feature list for property.
     * 
     */
    public function testCreateFeature()
    {
        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        $response = $this->json('POST', '/api/v1/features', [
            'name' => 'Air Conditioned', 
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test fail adding of feature list for property.
     */
    public function testFailCreateFeature()
    {
        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        $response = $this->json('POST', '/api/v1/features', [
            'name' => 'Air Conditioned', 
        ]);

        $response = $this->json('POST', '/api/v1/features', [
            'name' => 'Air Conditioned', 
        ]);

        $response->assertStatus(422);
    }

}
