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
use Carbon\Carbon;

class PropertyPhotosTest extends TestCase
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
     * Test photos upload.
     */
    public function testUpdatePropertyPhotos()
    {
        $property = $this->createProperty();

        Passport::actingAs($property->agent);
        
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

        $response->assertStatus(200);

        $this->assertCount(5, Storage::files('/images/properties'));

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'property_status_id' => 2, //pending 
        ]);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'property_status_id' => 2, //pending 
        ]);
    }

    /**
     * Test remove property photos.
     */
    public function testRemovePropertyPhotos()
    {
        Storage::fake();

        $property = $this->createProperty();

        Passport::actingAs($property->agent);

        $property->photos()->delete();

        $property->photos()->createMany([
            [ 'link' => Storage::url(UploadedFile::fake()->image('1.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('2.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('3.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('4.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('5.jpg')) ],
        ]);

        $propertyPhotosToBeDeleted = $property->photos()->pluck('id')->random(3)->toArray();

        $response = $this->json('DELETE', '/api/v1/properties/' . $property->id . '/photos', [
            'ids' => $propertyPhotosToBeDeleted, 
        ]);

        $response->assertStatus(200);

        $property->refresh();

        $property->photos->each(function ($photo) {
            $this->assertDatabaseHas('property_photos', [
                'id' => $photo->id, 
                'deleted_at' => null, 
            ]);
        });

        foreach ($propertyPhotosToBeDeleted as $photoId) {
            $this->assertDatabaseHas('property_photos', [
                'id' => $photoId, 
                'deleted_at' => now()->toDateTimeString(), 
            ]);
        }

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'property_status_id' => 2, //pending 
        ]);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'property_status_id' => 2, //pending 
        ]);
    }

    /**
     * Test failing to remove other property photos.
     */
    public function testFailRemoveOtherPropertyPhotos()
    {
        Storage::fake();

        $property = $this->createProperty();

        $initialHistoryCount = $property->histories()->count();

        $property->photos()->delete();

        $property->photos()->createMany([
            [ 'link' => Storage::url(UploadedFile::fake()->image('1.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('2.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('3.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('4.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('5.jpg')) ],
        ]);

        $otherProperty = $this->createProperty();

        Passport::actingAs($otherProperty->agent);

        // not owned photos
        $propertyPhotosToBeDeleted = $property->photos()->pluck('id')->random(3)->toArray();

        $response = $this->json('DELETE', '/api/v1/properties/' . $otherProperty->id . '/photos', [
            'ids' => $propertyPhotosToBeDeleted, 
        ]);

        $otherProperty->refresh();

        // chech the photos should not be soft deleted
        foreach ($propertyPhotosToBeDeleted as $photoId) {
            $this->assertDatabaseMissing('property_photos', [
                'id' => $photoId, 
                'deleted_at' => now()->toDateTimeString(), 
            ]);
        }

        $this->assertCount($initialHistoryCount, $property->fresh()->histories);
    }

    /**
     * Test updating of parent property timestamp after uploading photos.
     */
    public function testTouchParentUpdatedAtAfterPhotosUploaded()
    {
        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        $timeCreated = now()->subWeek()->toDateTimeString();

        $property = factory(Property::class)->create([
            'created_by' => $businessAccount->id,
            'created_at' => $timeCreated, 
            'updated_at' => $timeCreated, 
        ]);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'created_at' => $timeCreated, 
            'updated_at' => $timeCreated, 
        ]);

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

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'created_at' => $timeCreated, 
            'updated_at' => now()->toDateTimeString(), 
        ]);
    }

    /**
     * Test updating of parent property timestamp after removing photos.
     */
    public function testTouchParentUpdatedAtAfterPhotosRemoved()
    {
        // set time to yesterday
        Carbon::setTestNow(now()->subDay());

        $property = $this->createProperty();

        $this->removePhotoAndCheckTimestamp($property);
    }

    /**
     * Test updating of unverified parent property timestamp after removing photos.
     */
    public function testTouchUnverifiedParentUpdatedAtAfterPhotosRemoved()
    {
        // set time to yesterday
        Carbon::setTestNow(now()->subDay());

        // unverified property
        $property = $this->createProperty([
            'property_status_id' => 2, 
            'expired_at' => null,
        ]);

        $this->removePhotoAndCheckTimestamp($property);
    }

    /**
     * Check parent timestamp should be not touch if no photos are removed.
     */
    public function testDontTouchParentUpdatedAtAfterNoPhotosAreRemoved()
    {
        // set time to yesterday
        Carbon::setTestNow(now()->subDay());

        $property = $this->createProperty();
        $otherProperty = $this->createProperty();

        // revert time to today
        Carbon::setTestNow();

        Passport::actingAs($property->agent);

        $unOwnedPhotos = $otherProperty->photos()->pluck('id')->random(3)->toArray();

        $response = $this->json('DELETE', '/api/v1/properties/' . $property->id . '/photos', [
            'ids' => $unOwnedPhotos, 
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'updated_at' => $property->fresh()->getOriginal('updated_at'),
        ]);
    }

    /**
     * Remove property photos and check updated_at timestamp of parent property
     * 
     * @param \App\Property $property
     */
    private function removePhotoAndCheckTimestamp(Property $property)
    {
        // revert time to today
        Carbon::setTestNow();

        Passport::actingAs($property->agent);

        $propertyPhotosToBeDeleted = $property->photos()->pluck('id')->random(3)->toArray();

        $response = $this->json('DELETE', '/api/v1/properties/' . $property->id . '/photos', [
            'ids' => $propertyPhotosToBeDeleted, 
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'updated_at' => now()->toDateTimeString(), 
        ]);
    }
}
