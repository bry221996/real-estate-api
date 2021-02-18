<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Illuminate\Support\Facades\Redis;
use Laravel\Passport\Passport;
use App\Property;

class PropertyHitsTest extends TestCase
{
    use RefreshDatabase, TestHelperTrait;
    
    private $property;
    private $customer;

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

        $this->clearRedis();

        $this->createProperties();

        $this->property = Property::first();
        $this->customer = $this->createCustomer();

        Passport::actingAs($this->customer);
    }

    /**
     * Test Saving property hits.
     */
    public function testSavingPropertyHits()
    {
        $this->assertEquals(0, $this->property->fresh()->hits);

        $this->json('POST', '/api/v1/properties/' . $this->property->id . '/hits')
            ->assertStatus(200);
        
        $this->assertEquals(1, $this->property->fresh()->hits);
    }

    /**
     * Test different customer hits to property should be saved.
     */
    public function testDifferentCustomerHitsOnProperty()
    {
        $this->json('POST', '/api/v1/properties/' . $this->property->id . '/hits')
            ->assertStatus(200);

        $this->assertEquals(1, $this->property->fresh()->hits);

        $customer2 = $this->createCustomer();

        Passport::actingAs($customer2);

        $this->json('POST', '/api/v1/properties/' . $this->property->id . '/hits')
            ->assertStatus(200);

        $this->assertEquals(2, $this->property->fresh()->hits);
    }

    /**
     * Test saving unique property hits by every customer.
     */
    public function testUniquePropertyHits()
    {
        $this->json('POST', '/api/v1/properties/' . $this->property->id . '/hits')
            ->assertStatus(200);

        $this->assertEquals(1, $this->property->fresh()->hits);
    
        $this->json('POST', '/api/v1/properties/' . $this->property->id . '/hits')
            ->assertStatus(200);

        $this->assertEquals(1, $this->property->fresh()->hits);
    }
}
