<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Setup\PropertyFactory;
use Tests\Feature\V1\Setup\UserFactory;
use Laravel\Passport\Passport;

class PropertyIncludeAttributeTest extends TestCase
{
    use RefreshDatabase;

    private $property;
    private $customer;

    public  function setUp()
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
    
        $this->property = (new PropertyFactory)->create();

        $this->customer = (new UserFactory)
            ->setBookingsToProperty($this->property, 5)
            ->setRoles('customer')
            ->create();

        Passport::actingAs($this->customer);
    }

    /**
     * Check current_booking key.
     */
    public function testResponseHasCurrentBookingAttribute()
    {
        $response = $this->json(
            'GET',
            "/api/v1/properties/{$this->property->id}?include=current_booking",
            []
        );

        $response->assertStatus(200)
            ->assertJsonFragment([ 'current_booking' ]);
    }

    /**
     * Check if the current booking is owned.
     */
    public function testCurrentBookingIsOwned()
    {
        $otherCustomer = (new UserFactory)
            ->setBookingsToProperty($this->property, 3)
            ->setRoles('customer')
            ->create();

        $response = $this->json(
            'GET',
            "/api/v1/properties/{$this->property->id}?include=current_booking",
            []
        );

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'current_booking' => [
                        'user_id' => auth()->id(), 
                    ], 
                ], 
            ]);
    }
}
