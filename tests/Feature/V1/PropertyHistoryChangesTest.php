<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Illuminate\Http\UploadedFile;
use Storage;
use App\Property;
use App\Feature;
use App\PropertyHistory;
use Carbon\Carbon;

class PropertyHistoryChangesTest extends TestCase
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
     * Test listing of changed details.
     */
    public function testListChanges()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $property = $this->createProperty();

        $response = $this->json('GET', "/api/v1/properties/{$property->id}/changes", []);

        $response->assertStatus(200);

        $this->assertTrue(
            $property->histories()->count() 
            >= count(data_get($response->json(), 'data'))
        );
    }

    /**
     * Get change history that didn't change the name and adress.
     */
    public function testListMultipleChangesExceptNameAndAddress()
    {
        $property = $this->createProperty();

        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $changeCount = range(1, 3);

        foreach ($changeCount as $count) {
            // dont change the name and address
            $propertyUpdateData = factory(Property::class)->make([
                'name' => $property->name,
                'address' => $property->address
            ]);

            $property->update($propertyUpdateData->getAttributes());

            factory(PropertyHistory::class)->create([ 
                'property_id' => $property->id,
                'created_at' => now()->addDay()->toDateTimeString(), 
                'updated_at' => now()->addDay()->toDateTimeString(), 
            ]);
        }

        $response = $this->json('GET', "/api/v1/properties/{$property->id}/changes", []);

        $response->assertStatus(200);

        $response->assertJsonCount(count($changeCount), 'data');

        $response->assertJsonMissing([
            'name', 
            'address', 
        ]);
    }

    /**
     * Check history of property features changes.
     */
    public function testListChangesOnFeturesAttribute()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $property = $this->createProperty();

        $newFeatures = Feature::get()->random(5)->pluck('id');

        $property->features()->sync($newFeatures);

        factory(PropertyHistory::class)->create([
            'property_id' => $property->id,
            'created_at' => now()->addDay()->toDateTimeString(), 
            'updated_at' => now()->addDay()->toDateTimeString(), 
        ]);

        $response = $this->json('GET', "/api/v1/properties/{$property->id}/changes", []);

        $response->assertJsonFragment([ 'features' ]);

        $response->assertStatus(200);
    }

    /**
     * Check changes after removing a photo.
     */
    public function testListChangesAfterPhotosRemoved()
    {
        // set to previous month
        $timeCreated = now()->subMonth();
        Carbon::setTestNow($timeCreated);

        // set property to unverified status
        $property = $this->createProperty([
            'property_status_id' => 2, 
            'expired_at' => null,
        ]);
        
        Passport::actingAs($property->agent);

        // revert time to today
        Carbon::setTestNow();

        $response = $this->json('DELETE', '/api/v1/properties/' . $property->id . '/photos', [
            'ids' => [ $property->photos()->pluck('id')->random() ], 
        ]);

        $response->assertStatus(200);

        Passport::actingAs($this->createAdmin());
        
        $response = $this->json('GET', "/api/v1/properties/{$property->id}/changes", []);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                [
                    'photos' => [
                        'from',
                        'to',
                    ],
                    'updated_at' => [
                        'from',
                        'to',
                    ],
                ]
            ], 
        ]);
    }

    /**
     * Change property status multiple times.
     */
    public function testMultipleChangeInPropertyStatus()
    {
        // set to previous month
        $timeCreated = now()->subMonth();
        Carbon::setTestNow($timeCreated);

        // set property to unverified status
        $property = $this->createProperty([
            'property_status_id' => 2, 
            'expired_at' => null,
        ]);

        Passport::actingAs($this->createAdmin());

        $days = collect(range(1, 7))->reverse();

        $days->each(function ($day) use ($property) {
            Carbon::setTestNow();
            Carbon::setTestNow(now()->subDays($day));

            if ($property->property_status_id == 1) {
                $property->update([ 'property_status_id' => 2 ]);
            } else {
                $property->update([ 'property_status_id' => 1 ]);
            }

            $property->touch();

            $property->saveToHistory();
        });

        $response = $this->json('GET', "/api/v1/properties/{$property->id}/changes", []);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                [
                    'updated_at', 
                    'property_status_id', 
                ],
            ], 
        ]);
    }

    /**
     * Check changes of attachments in property history.
     */
    public function testPropertyAttachmentsHistoryChanges()
    {
        // set to previous days
        $timeCreated = now()->subDays(3);
        Carbon::setTestNow($timeCreated);

        $property = $this->createProperty();

        Passport::actingAs($this->createAdmin());

        // set to yesterday
        $timeUpdated = now()->subDay();
        Carbon::setTestNow($timeUpdated);

        // add the attachment
        factory(\App\PropertyAttachment::class, 1)->create([ 'property_id' => $property->id ]);
        $property->touch();

        $property->saveToHistory();

        // revert to today
        Carbon::setTestNow();

        $response = $this->json('GET', "/api/v1/properties/{$property->id}/changes", []);

        $response->assertStatus(200);

        $response->assertSee('updated_at');
    }

    /**
     * Test different changes in property details.
     */
    public function testMultipleMixedChangesInPropertyDetails()
    {
        // set to previous days
        $timeCreated = now()->subDays(3);
        Carbon::setTestNow($timeCreated);

        $property = $this->createProperty([
            'property_status_id' => 2, 
            'expired_at' => null, 
        ]);

        $expectedData = collect();

        $this->assertCount(1, $property->histories()->get());

        Passport::actingAs($this->createAdmin());

        // revert time to today
        Carbon::setTestNow();

        // first edit - set property to verified
        Carbon::setTestNow(now()->subHours(8));

        $property->update([
            'property_status_id' => 1, 
            'expired_at' => now()->addDays(Property::$expiredAfterDays), 
        ]);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'updated_at' => now()->toDateTimeString(), 
        ]);

        $property->saveToHistory();

        $this->assertCount(2, $property->histories()->get());

        $expectedData->prepend([
            'property_status_id' => [
                'from' => 2, 
                'to' => 1, 
            ], 
        ]);

        // second edit - remove an attachment
        Carbon::setTestNow(now()->addHour());

        $attachmentsBefore = $property->attachments;
        $property->attachments()->first()->delete();
        $property->touch();
        $property->saveToHistory();

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'updated_at' => now()->toDateTimeString(), 
        ]);

        $this->assertCount(3, $property->histories()->get());

        $expectedData->prepend([
            'attachments' => [
                'from' => $attachmentsBefore->toArray(), 
                'to' => $property->fresh()->attachments->toArray(), 
            ], 
        ]);

        // third edit - upload photos
        Carbon::setTestNow(now()->addHour());

        $photosBefore = $property->photos;
        factory(\App\PropertyPhoto::class, 2)->create([ 'property_id' => $property->id ]);
        $property->saveToHistory();

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'updated_at' => now()->toDateTimeString(), 
        ]);

        $this->assertCount(4, $property->histories()->get());

        $expectedData->prepend([
            'photos' => [
                'from' => $photosBefore->toArray(), 
                'to' => $property->fresh()->photos->toArray(), 
            ], 
        ]);

        // fourth edit - remove a photo
        Carbon::setTestNow(now()->addHour());

        $photosBefore = $property->fresh()->photos;
        $property->photos()->first()->delete();
        $property->saveToHistory();

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'updated_at' => now()->toDateTimeString(), 
        ]);

        $this->assertCount(5, $property->histories()->get());

        $expectedData->prepend([
            'photos' => [
                'from' => $photosBefore->toArray(), 
                'to' => $property->fresh()->photos->toArray(), 
            ], 
        ]);

        $response = $this->json('GET', "/api/v1/properties/{$property->id}/changes", []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => $expectedData->toArray(), 
        ]);
    }

    /**
     * Test list changes when photos and attachments attribute are changed.
     */
    public function testChangeInPhotosAndAttachments()
{
        \Storage::fake();

        // set to yesterday
        Carbon::setTestNow(now()->subDay());

        $property = $this->createProperty();

        Passport::actingAs($property->agent);

        // revert time now
        Carbon::setTestNow();

        $response = $this->json('DELETE', "/api/v1/properties/{$property->id}/attachments", [
            'ids' => $property->attachments->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200);

        $response = $this->json('POST', "/api/v1/properties/{$property->id}/photos", [
            'photos' => [
                UploadedFile::fake()->image('1.jpg'), 
            ], 
        ]);

        $response->assertStatus(200);


        Passport::actingAs($this->createAdmin());

        $response = $this->json('GET', "/api/v1/properties/{$property->id}/changes", []);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                [
                    'photos',
                    'updated_at',
                ],
                [
                    'attachments',
                    'updated_at',
                ],
            ], 
        ]);
    }
}
