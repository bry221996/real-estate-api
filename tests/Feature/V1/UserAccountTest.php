<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Storage;

class UserAccountTest extends TestCase
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
     * Test GET account details.
     */
    public function testAccountEndpoint()
    {
        $customer = $this->createCustomer();
       
        Passport::actingAs($customer);

        $response = $this->json('GET', '/api/v1/account');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => $this->getAccountJsonStructure(),
            ]);
    }

    /**
     * Test include of roles relation to account.
     */
    public function testIncludeRolesRelation()
    {
        $customer = $this->createCustomer();
       
        Passport::actingAs($customer);

        $response = $this->json('GET', '/api/v1/account?include=roles');

        $response->assertStatus(200)
            ->assertJsonFragment([ 'roles' ]);
    }

    /**
     * Test customer account update.
     */
    public function testAccountUpdate()
    {
        $customer = $this->createCustomer();

        Passport::actingAs($customer);

        $dataToUpdate = [
            'first_name' => 'change FN', 
            'last_name' => 'change LN', 
            'email' => 'change@sample.com', 
        ];

        $response = $this->json('PUT', '/api/v1/account', $dataToUpdate);

        $customer->refresh();

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'user' => [
                        'first_name' => 'change FN', 
                        'last_name' => 'change LN', 
                        'email' => 'change@sample.com', 
                    ], 
                ], 
            ]);

        $this->assertDatabaseHas('users', $dataToUpdate);
    }

    /**
     * Test customer account update photo.
     */
    public function testAccountUpdatePhoto()
    {
        $customer = $this->createCustomer();

        Passport::actingAs($customer);

        Storage::fake();

        $response = $this->json('POST', '/api/v1/account/photo', [
            'photo' => UploadedFile::fake()->image('1.jpg'), 
        ]);

        $user = auth()->user();

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'user' => [
                        'photo' => $user->photo, 
                    ], 
                ], 
            ]);

        $this->assertContains(str_replace('/storage/', '', $user->photo), Storage::allFiles());
    }

    /**
     * Test updating of prc number for business accounts.
     */
    public function testBusinessAccountUpdatePrcNumber()
    {
        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        Storage::fake();

        $response = $this->json('PUT', '/api/v1/account', [
            'first_name' => 'change FN', 
            'last_name' => 'change LN', 
            'email' => 'change@sample.com', 
            'prc_registration_number' => 12345678, 
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'user' => [
                        'first_name' => 'change FN', 
                        'last_name' => 'change LN', 
                        'email' => 'change@sample.com', 
                        'prc_registration_number' => 12345678, 
                    ], 
                ], 
            ]);
    }

    /**
     * Test upload of prc id photo for business accounts.
     */
    public function testBusinessAccountUploadPrcPhoto()
    {
        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        Storage::fake();

        $response = $this->json('POST', '/api/v1/account/prc_id', [
            'photo' => UploadedFile::fake()->image('1.jpg'), 
        ]);

        $user = auth()->user();

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'user' => [
                        'prc_id_link' => $user->prc_id_link, 
                    ], 
                ], 
            ]);

        $this->assertContains(str_replace('/storage/', '', $user->prc_id_link), Storage::allFiles());
    }

    /**
     * Test overwriting of prc id.
     */
    public function testBusinessAccountUpdatePhotoShouldBeOverwritten()
    {
        $businessAccount = $this->createBusinessAccount();

        $this->assertEmpty($businessAccount->prc_id_link);

        $prcDetails = [
            'prc_registration_number' => rand(), 
            'prc_id_link' => \Storage::url('sample.jpg'), 
        ];

        $businessAccount->update($prcDetails);

        $this->assertDatabaseHas('users', $prcDetails);

        Passport::actingAs($businessAccount);

        Storage::fake();

        $response = $this->json('POST', '/api/v1/account/prc_id', [
            'photo' => UploadedFile::fake()->image('new_prc.pdf'), 
        ]);

        $this->assertDatabaseMissing('users', $prcDetails);
    }

    /**
     * Test remove of prc id photo for business accounts.
     */
    public function testBusinessAccountRemovePrcPhoto()
    {
        Storage::fake();

        $businessAccount = $this->createBusinessAccount();

        $businessAccount->update([ 'prc_id_link' => 'any.jpeg' ]);

        Passport::actingAs($businessAccount);

        $response = $this->json('DELETE', '/api/v1/account/prc_id', []);

        $user = auth()->user();

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'user' => [
                        'prc_id_link' => null, 
                    ], 
                ], 
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $businessAccount->id, 
            'prc_id_link' => null, 
        ]);
    }

    /**
     * Get properties under the business account
     */
    public function testBusinessAccountGetProperties()
    {
        $properties = $this->createProperties();

        $businessAccount = $properties->first()->agent;

        Passport::actingAs($businessAccount);

        $response = $this->json('GET', '/api/v1/account/properties', []);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    $this->getPropertyJsonStructure(),
                ], 
                'links',
                'meta',
            ]);
    }

    /**
     * Test get schedule endpoint without data on database.
     */
    public function testBusinessAccountGetScheduleWithoutDataOnDatabaseShouldReturnEmpty()
    {
        $businessAccount = $this->createBusinessAccount();
        $businessAccount->schedule()->delete();

        $this->assertEmpty($businessAccount->schedules);

        Passport::actingAs($businessAccount);

        $response = $this->json('GET', '/api/v1/account/schedules', []);
        
        $response->assertStatus(200)
            ->assertSee('Schedule not configured.');
    }

    /**
     * Get default account structure.
     *
     * @return array
     */
    private function getAccountJsonStructure()
    {
        return [
            'id',
            'first_name',
            'last_name',
            'full_name',
            'gender',
            'marital_status',
            'location',
            'username',
            'email',
            'mobile',
            'points',
            'created_at',
            'updated_at',
            'customer_profile_complete',
            'business_account_is_verified',
            'has_business_account',
        ];
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
        ];
    }
}
