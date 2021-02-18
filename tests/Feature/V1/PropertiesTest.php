<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Laravel\Passport\Passport;
use Illuminate\Http\UploadedFile;
use Storage;
use App\Property;
use App\Feature;

class PropertiesTest extends TestCase
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
     * Default listing of properties should be the ones that are published.
     */
    public function testListPropertiesDefaultIsPublishedAndNotExpired()
    {
        $activeProperties = $this->createProperties();
        $pendingProperty = $this->createProperty([ 'property_status_id' => 2 ]);
        $expiredProperty = $this->createProperty([ 'expired_at' => now()->subDay()->toDateTimeString() ]);

        $response = $this->json('GET', '/api/v1/properties', []);

        $response->assertJsonCount($activeProperties->count(), 'data');
    }

    /**
     * Test get properties with authenticated user.
     */
    public function testListPropertiesWithAuth()
    {
        $customer = $this->createCustomer();

        Passport::actingAs($customer);

        $properties = $this->createProperties(30);

        $response = $this->json('GET', '/api/v1/properties', []);

        $response->assertStatus(200);

        $this->checkStructure($response);
    }

    /**
     * Test get properties without authenticated user.
     */
    public function testListPropertiesWithoutAuth()
    {
        $properties = $this->createProperties(30);

        $response = $this->json('GET', '/api/v1/properties', []);

        $response->assertStatus(200);

        $this->checkStructure($response);

        // is_interested should be false
        $response->assertJsonFragment([
            'is_interested' => false, 
        ]);
    }
    
    /**
     * Test scope to list properties regardless of current status.
     */
    public function testListPropertiesWithScopeAllPropertyStatus()
    {
        Passport::actingAs($this->createBusinessAccount());

        $activeProperties = $this->createProperties(30);

        for ($i = 0; $i < 10; $i++) { 
            $activeProperties->push($this->createProperty([ 'property_status_id' => 2]));
        }

        $response = $this->json(
            'GET', 
            '/api/v1/properties?scope=all_property_status', 
            []
        );

        $response->assertStatus(200);
        
        $response->assertJson([
            'meta' => [
                'total' => $activeProperties->count(), 
            ], 
        ]);
    }

    /**
     * Test include features and photos relation.
     */
    public function testPropertyIncludeRelations()
    {
        $properties = $this->createProperties(30);

        $response = $this->json('GET', '/api/v1/properties?include=features,photos', []);

        // check if the featuresis included
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    [
                        'features' => [],
                        'photos' => [],
                    ],
                ], 
            ]);
    }

    /**
     * Test include attachments relation.
     */
    public function testPropertyIncludeAttachmentsRelation()
    {
        $properties = $this->createProperties(30);

        $response = $this->json('GET', '/api/v1/properties?include=attachments', []);

        // check if the features is included
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    [
                        'attachments' => [],
                    ],
                ], 
            ]);
    }

    /**
     * Test show property features and photos relation.
     */
    public function testShowPropertyIncludeAttachmentsRelation()
    {
        $property = $this->createProperty();

        Passport::actingAs($property->agent);

        $response = $this->json('GET', '/api/v1/properties/' . $property->id . '?include=attachments', []);

        // check if the features and photos is included
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'attachments' => [],
                ], 
            ]);
    }

    /**
     * Get property.
     */
    public function testShowProperty()
    {
        $properties = $this->createProperties(30);

        $property = $properties->first();

        $response = $this->json('GET', '/api/v1/properties/' . $property->id , []);

        $response->assertStatus(200);

        $this->checkResourceStructure($response);
    }

    /**
     * Test show property with schedules.
     */
    public function testShowPropertyIncludeScheduleRelation()
    {
        $properties = $this->createProperties(30);

        $property = $properties->first();

        $response = $this->json('GET', '/api/v1/properties/' . $property->id . '?include=schedule', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'schedule' => $property->agent->schedule->toArray(), 
            ], 
        ]);
    }

    /**
     * Create property.
     */
    public function testCreateWithDetailsAndFeatures()
    {
        $this->createProperties();

        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        $data = factory(Property::class)->make([]);

        $data = collect($data)
            ->filter(function ($value) {
                return $value !== null;
            })
            ->all();

        $data['property_type_id'] = 1;
        $data['features'] = Feature::get()->random(5)->pluck('id');

        $response = $this->json('POST', '/api/v1/properties', $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message', 
                'meta' => [
                    'property'
                ],
            ]);

        $propertyDataFromDB = Property::where('name', $data['name'])->first();

        $date = now()->format('ymd');
        $businessAccountId = str_pad($businessAccount->id, 5, '0', STR_PAD_LEFT);
        $id = str_pad($propertyDataFromDB->id, 3, '0', STR_PAD_LEFT);

        $this->assertDatabaseHas('properties', [ 
            'id' => $propertyDataFromDB->id,
            'listing_id' => "C{$date}_{$businessAccountId}_$id", 
        ]);

        // check if features is attach to property
        $data['features']->each(function ($featureId) use ($propertyDataFromDB) {
            $this->assertDatabaseHas('property_feature', [
                'property_id' => $propertyDataFromDB->id, 
                'feature_id' => $featureId, //pending 
            ]);
        });
    }

    /**
     * Create property.
     */
    public function testCreatePropertyWithoutZipcode()
    {
        $this->createProperties();

        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        $property = factory(Property::class)->make([]);

        $property = collect($property)
            ->filter(function ($value) {
                return $value !== null;
            })
            ->forget('zipcode')
            ->all();

        $property['property_type_id'] = 1;
        $property['features'] = Feature::get()->random(5)->pluck('id');

        $response = $this->json('POST', '/api/v1/properties', $property);

        $response->assertStatus(200);
    }

    /**
     * Update property details.
     */
    public function testUpdatePropertyDetails()
    {
        $property = $this->createProperty();

        // set created_at to yesterday
        $property->update([
            'created_at' => now()->subDay()->toDateTimestring(), 
        ]);

        Passport::actingAs($property->agent);

        $propertyUpdateData = factory(Property::class)->make();

        $propertyUpdateData = collect($propertyUpdateData)
            ->filter(function ($value) {
                return $value !== null;
            })
            ->all();

        $propertyUpdateData['features'] = Feature::get()->random(5)->pluck('id');

        $response = $this->json('PUT', '/api/v1/properties/' . $property->id, $propertyUpdateData);

        $response->assertStatus(200);

        // refresh the model
        $property->refresh();

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'property_status_id' => 2, //pending 
        ]);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'property_status_id' => 2, //pending 
        ]);

        // check if features is attach to property
        $propertyUpdateData['features']->each(function ($featureId) use ($property) {
            $this->assertDatabaseHas('property_feature', [
                'property_id' => $property->id,     
                'feature_id' => $featureId, //pending 
            ]);
        });
    }

    /**
     * Unauthorize update of property.
     */
    public function testUnauthorizeUpdateProperty()
    {
        $property = $this->createProperty();

        $businessAccount = $this->createBusinessAccount();
        Passport::actingAs($businessAccount);

        $propertyUpdateData = factory(Property::class)->make();

        $propertyUpdateData = collect($propertyUpdateData)
            ->filter(function ($value) {
                return $value !== null;
            })
            ->all();

        $propertyUpdateData['features'] = Feature::get()->random(5)->pluck('id');

        $response = $this->json('PUT', '/api/v1/properties/' . $property->id, $propertyUpdateData);

        $response->assertStatus(401);

        Storage::fake();

        $response = $this->json('POST', '/api/v1/properties/' . $property->id . '/photos', [
            'photos' => [
                UploadedFile::fake()->image('1.jpg'), 
                UploadedFile::fake()->image('2.jpg'), 
                UploadedFile::fake()->image('3.jpg'), 
                UploadedFile::fake()->image('4.jpg'), 
                UploadedFile::fake()->image('5.jpg'), 
            ], 
        ]);

        $response->assertStatus(401);

        $response = $this->json('POST', '/api/v1/properties/' . $property->id . '/attachments', [
            'attachments' => [
                UploadedFile::fake()->create('1.pdf'), 
                UploadedFile::fake()->image('2.jpg'), 
                UploadedFile::fake()->create('3.docx'), 
            ], 
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test get feature list for property.
     */
    public function testListFeatures()
    {
        factory(Feature::class, 30)->create();

        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        $response = $this->json('GET', '/api/v1/features', []);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name'
                    ],
                ], 
            ]);
    }

    /**
     * Check list structure.
     *
     * @param \Illuminate\Http\Response $response
     * @return void
     */
    private function checkStructure($response)
    {
        $response->assertJsonStructure([
            'data' => [
                $this->getPropertyJsonStructure(),
            ], 
            'links',
            'meta',
        ]);
    }

    /**
     * Check single resource structure.
     *
     * @param \Illuminate\Http\Response $response
     */
    private function checkResourceStructure($response)
    {
        $response->assertJsonStructure([
            'data' => $this->getPropertyJsonStructure(), 
        ]);
    }

    /**
     * Standard property json structure.
     */
    private function getPropertyJsonStructure()
    {
        return [
            'id',           
            'listing_id',      
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
            'created_by',
            'appointment_count',
            'fulfilled_appointment_count'      
        ];
    }
}
