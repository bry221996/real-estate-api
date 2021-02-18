<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;

class UserPropertiesTest extends TestCase
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
     * Test list without queries.
     */
    public function testListBusinessAccountProperties()
    {
        $this->createMultipleProperties();

        $businessAccountProperties = $this->createProperties();

        Passport::actingAs($businessAccountProperties->first()->agent);

        $response = $this->json('GET', '/api/v1/account/properties', []);

        $response->assertStatus(200);

        $responseData = collect(data_get($response->json(), 'data'));

        $this->assertEquals(
            $businessAccountProperties->pluck('id')->sort()->values(), 
            $responseData->pluck('id')->sort()->values()
        );
    }

    /**
     * Generate multiple properties.
     *
     * @return void
     */
    private function createMultipleProperties()
    {
        collect(range(1, 10))->each(function () {
            $this->createProperties();
        });
    }
}
