<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use App\Property;

class UserInterestTest extends TestCase
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
     * Test add to user interest list.
     */
    public function testAddPropertyToInterestList()
    {
        $this->createProperties();
        $property = $this->createProperty();
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        // add the property to interest of the other user
        $otherCustomer->interests()->attach($property->id);

        Passport::actingAs($customer);

        $response = $this->json('POST', '/api/v1/properties/' . $property->id . '/interest', []);

        $response->assertStatus(200);

        $this->assertDatabaseHas('interested_user', [
            'user_id' => $customer->id, 
            'property_id' => $property->id, 
        ]);

        // try spamming
        $this->json('POST', '/api/v1/properties/' . $property->id . '/interest', []);
        $this->json('POST', '/api/v1/properties/' . $property->id . '/interest', []);

        $hasDuplicate = $customer->interests()->where('properties.id', $property->id)->count() > 1;

        $this->assertFalse($hasDuplicate);

        // check is_interested attribute is true
        $response = $this->json('GET', '/api/v1/properties/' . $property->id, []);

        $response->assertStatus(200)
                ->assertJsonFragment([
                    'is_interested' => true, 
                ]);

        $response = $this->json('GET', '/api/v1/properties', []);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $property->id, 
                'is_interested' => true, 
            ]);
    }

    /**
     * Test remove to user interest list.
     */
    public function testRemovePropertyToInterestList()
    {
        $properties = $this->createProperties(30);
        $customer = $this->createCustomer();

        // add to user interest list
        $interestedProperties = $properties->random(5)->pluck('id');
        $customer->interests()->attach($interestedProperties->toArray());

        // count user interests
        $this->assertCount(5, $customer->interests);

        $toBeRemovedFromInterest = $interestedProperties->random();

        Passport::actingAs($customer);

        $response = $this->json('DELETE', '/api/v1/properties/' . $toBeRemovedFromInterest . '/interest', []);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('interested_user', [
            'user_id' => $customer->id, 
            'property_id' => $toBeRemovedFromInterest, 
        ]);
    }

    /**
     * Test listing of user interests.
     */
    public function testListUserInterest()
    {
        $properties = $this->createProperties(30);
        $customer = $this->createCustomer();

        // add to user interest list
        $interestedProperties = $properties->random(5)->pluck('id');
        $customer->interests()->attach($interestedProperties->toArray());

        Passport::actingAs($customer);

        $response = $this->json('GET', '/api/v1/account/interests?include=features,photos', []);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                [
                    'id',                 
                    'name',               
                    'building_name',      
                    'description',        
                    'lot_size',           
                    'floor_size',         
                    'bathroom_count',     
                    'bedroom_count',      
                    'garage_count',       
                    'address',            
                    'city',               
                    'zip_code',           
                    'latitude',           
                    'longitude',          
                    'developer',          
                    'expired_at',         
                    'furnished_type',  
                    'offer_type',      
                    'property_type',   
                    'price',              
                    'price_per_sqm',      
                    'occupied',           
                    'verified_by',        
                    'property_status', 
                    'created_at',         
                    'updated_at',         
                    'is_interested',      
                ],
            ], 
            'links',
            'meta',
        ]);

        $responseData = $response->getOriginalContent();

        $this->assertCount(5, $responseData['data']);
    }
}
