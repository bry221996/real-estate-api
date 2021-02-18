<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Storage;
use Illuminate\Http\UploadedFile;

class PropertyHistoryTest extends TestCase
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
     * Property details must be recorded on history after create.
     */
    public function testPropertyMustHaveHistoryOnProfileComplete()
    {
        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        $property = factory(\App\Property::class)->create([ 'created_by' => $businessAccount->id ]);

        $randomFeatures = \App\Feature::get()->random(5)->pluck('id');

        $property->features()->attach($randomFeatures);

        Storage::fake();

        $response = $this->json('POST', "/api/v1/properties/{$property->id}/photos", [
            'photos' => [
                UploadedFile::fake()->image('1.jpg'), 
                UploadedFile::fake()->image('2.jpg'), 
                UploadedFile::fake()->image('3.jpg'), 
                UploadedFile::fake()->image('4.jpg'), 
                UploadedFile::fake()->image('5.jpg'), 
            ], 
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'property_status_id' => 2,
        ]);
        
        $this->assertEquals(
            $property->fresh()->loadMissing('attachments')->toArray(), 
            $property->histories()->first()->details
        );
    }
}
