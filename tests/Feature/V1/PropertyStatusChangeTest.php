<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Laravel\Passport\Passport;

class PropertyStatusChangeTest extends TestCase
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
     * Test admin verifies property.
     */
    public function testPropertyVerification()
    {
        // created property with pending status
        $property = $this->createProperty([
            'property_status_id' => 2, 
        ]);

        $admin = $this->createAdmin();
        Passport::actingAs($admin);

        $response = $this->json('PUT', '/api/v1/properties/' . $property->id . '/verify', []);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'property_status_id' => 1, 
            'expired_at' => now()->endOfDay()->addDays(15)->toDateTimeString(), 
        ]);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'property_status_id' => 1, 
        ]);
    }

    /**
     * Test admin verifies property fails.
     */
    public function testFailPropertyVerification()
    {
        $property = $this->createProperty();

        $this->assertEquals(1, $property->property_status_id);

        $admin = $this->createAdmin();
        Passport::actingAs($admin);

        $response = $this->json('PUT', '/api/v1/properties/' . $property->id . '/verify', []);

        $response->assertStatus(422);
    }

    /**
     * Test admin rejects property.
     */
    public function testPropertyReject()
    {
        // created property with pending status
        $property = $this->createProperty([
            'property_status_id' => 2, 
        ]);

        $admin = $this->createAdmin();
        Passport::actingAs($admin);

        $response = $this->json('PUT', '/api/v1/properties/' . $property->id . '/reject', [
            'comment' => 'This is rejected.', 
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'property_status_id' => 3, // rejected
        ]);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'property_status_id' => 3, 
        ]);
    }

    /**
     * Test admin reject property fails.
     */
    public function testFailPropertyReject()
    {
        $property = $this->createProperty();

        $this->assertEquals(1, $property->property_status_id);

        $admin = $this->createAdmin();
        Passport::actingAs($admin);

        $response = $this->json('PUT', '/api/v1/properties/' . $property->id . '/reject', []);

        $response->assertStatus(422);
    }

    /**
     * Test Property Agent set his/her property as sold or occupied.
     */
    public function testPropertySoldOrOccupied()
    {
        $property = $this->createProperty();

        Passport::actingAs($property->agent);

        $response = $this->json('PUT', '/api/v1/properties/' . $property->id . '/sold', []);

        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'property_status_id' => 4, // sold
        ]);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'property_status_id' => 4, 
        ]);
    }

    /**
     * Test Fail Property Agent set his/her property as sold or occupied.
     */
    public function testFailPropertySold()
    {
        $property = $this->createProperty([
            'property_status_id' => 2, 
        ]);

        $this->assertEquals(2, $property->property_status_id);

        Passport::actingAs($property->agent);

        $response = $this->json('PUT', '/api/v1/properties/' . $property->id . '/sold', []);

        $response->assertStatus(422);
    }

    /**
     * Test admin set property to pending from publish.
     */
    public function testPropertyUnpublish()
    {
        // create verified property
        $property = $this->createProperty();

        $admin = $this->createAdmin();
        Passport::actingAs($admin);

        $response = $this->json('PUT', "/api/v1/properties/{$property->id}/unpublish", []);

        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'property_status_id' => 2, // pending
        ]);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'property_status_id' => 2, 
        ]);
    }

    /**
     * Test admin republish expired property.
     */
    public function testPropertyRepublish()
    {
        // create expired published property
        $property = $this->createProperty([ 'expired_at' => now()->subDay()->toDateTimeString() ]);

        Passport::actingAs($property->agent);

        $response = $this->json('PUT', "/api/v1/properties/{$property->id}/republish", []);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'expired_at' => now()->addDays(\App\Property::$expiredAfterDays)->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'details->expired_at' => now()->addDays(\App\Property::$expiredAfterDays)->toDateTimeString(), 
        ]);
    }

    /**
     * Test business account extend his/her property expiration date.
     */
    public function testExtendPropertyExpiration()
    {
        $expiration = now()->addDay();

        $property = $this->createProperty([ 'expired_at' => $expiration->toDateTimeString() ]);

        Passport::actingAs($property->agent);

        $response = $this->json('PUT', "/api/v1/properties/{$property->id}/extend", []);

        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'expired_at' => $expiration->addDays(\App\Property::$expiredAfterDays)->toDateTimeString(),
        ]);
    }

    /**
     * Test business account fail to extend the expiration date because it's allready expired.
     */
    public function testFailExtendPropertyExpiration()
    {
        $property = $this->createProperty([ 'expired_at' => now()->subDays(2)->toDateTimeString() ]);

        Passport::actingAs($property->agent);

        $response = $this->json('PUT', "/api/v1/properties/{$property->id}/extend", []);

        $response->assertStatus(422);
    }
}
