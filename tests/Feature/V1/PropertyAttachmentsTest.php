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

class PropertyAttachmentsTest extends TestCase
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
     * Test attachments upload.
     */
    public function testUpdatePropertyAttachments()
    {
        $property = $this->createProperty();

        Passport::actingAs($property->agent);

        Storage::fake();

        $initialAttachmentsCount = $property->attachments()->count();

        $response = $this->json('POST', '/api/v1/properties/' . $property->id . '/attachments', [
            'attachments' => [
                UploadedFile::fake()->create('1.pdf'), 
                UploadedFile::fake()->image('2.jpg'), 
                UploadedFile::fake()->create('3.docx'), 
            ], 
        ]);

        $response->assertStatus(200);

        $this->assertCount(3, Storage::files('/images/attachments'));

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'property_status_id' => 2, //pending 
        ]);

        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->id, 
            'property_status_id' => 2, //pending 
        ]);

        $this->assertTrue($property->fresh()->attachments->count() > $initialAttachmentsCount);
    }

    /**
     * Test remove property attachments.
     */
    public function testRemovePropertyAttachments()
    {
        $property = $this->createProperty();

        Passport::actingAs($property->agent);

        Storage::fake();

        $toBeRemovedAttachments = $property->attachments->pluck('id')->random(3)->toArray();

        $response = $this->json('DELETE', '/api/v1/properties/' . $property->id . '/attachments', [
            'ids' => $toBeRemovedAttachments,
        ]);

        $response->assertStatus(200);

        foreach ($toBeRemovedAttachments as $attachmentId) {
            $this->assertDatabaseHas('property_attachments', [
                'id' => $attachmentId, 
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
     * Test failt to remove other property attachments.
     */
    public function testFailRemoveOtherPropertyAttachments()
    {
        Storage::fake();

        $otherProperty = $this->createProperty();

        $otherProperty->attachments()->delete();

        $otherProperty->attachments()->createMany([
            [ 'link' => Storage::url(UploadedFile::fake()->create('1.pdf')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('2.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('12.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->image('123.jpg')) ],
            [ 'link' => Storage::url(UploadedFile::fake()->create('3.docx')) ],
        ]);

        $property = $this->createProperty();

        Passport::actingAs($property->agent);

        $otherPropertyAttachments = $otherProperty->attachments->pluck('id')->random(3)->toArray();

        $response = $this->json('DELETE', '/api/v1/properties/' . $property->id . '/attachments', [
            'ids' => $otherPropertyAttachments,
        ]);

        $response->assertStatus(200);

        foreach ($otherPropertyAttachments as $attachmentId) {
            $this->assertDatabaseMissing('property_attachments', [
                'id' => $attachmentId, 
                'deleted_at' => now()->toDateTimeString(), 
            ]);
        }
    }

    /**
     * Check parent timestamp if it's updated when attachments are uploaded.
     */
    public function testTouchParentUpdatedAtAfterAttachmentsAreUploaded()
    {
        // set time to yesterday
        Carbon::setTestNow(now()->subDay());

        $property = $this->createProperty();

        // Revert time to today
        Carbon::setTestNow();

        Passport::actingAs($property->agent);

        Storage::fake();

        $response = $this->json('POST', "/api/v1/properties/{$property->id}/attachments", [
            'attachments' => [
                UploadedFile::fake()->create('1.pdf'), 
                UploadedFile::fake()->image('2.jpg'), 
                UploadedFile::fake()->create('3.docx'), 
            ], 
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'updated_at' => now()->toDateTimeString(), 
        ]);

        $this->assertNotEquals($property->getOriginal('updated_at'), now()->toDateTimeString());
    }

    /**
     * Check parent timestamp if it's updated when attachments are removed.
     */
    public function testTouchParentUpdatedAtAfterAttachmentsAreRemoved()
    {
        // set time to yesterday
        Carbon::setTestNow(now()->subDay());

        $property = $this->createProperty();

        $this->removeAttachmentAndCheckTimeStamp($property);
    }

    /**
     * Check unverified property timestamp if it's updated when attachments are removed.
     */
    public function testTouchUnverifiedParentUpdatedAtAfterAttachmentsAreRemoved()
    {
        // set time to yesterday
        Carbon::setTestNow(now()->subDay());

        $property = $this->createProperty();

        $this->removeAttachmentAndCheckTimeStamp($property);
    }

    /**
     * Check parent timestamp should be not touch if no attachments are removed.
     */
    public function testDontTouchParentUpdatedAtAfterNoAttachmentsAreRemoved()
    {
        // set time to yesterday
        Carbon::setTestNow(now()->subDay());

        $property = $this->createProperty();
        $otherProperty = $this->createProperty();

        // Revert time to today
        Carbon::setTestNow();

        Passport::actingAs($property->agent);

        $unOwnedAttachments = $otherProperty->attachments->pluck('id')->random(2)->toArray();

        $response = $this->json('DELETE', "/api/v1/properties/{$property->id}/attachments", [
            'ids' => $unOwnedAttachments,
        ]);

        $response->assertStatus(200);

        $this->assertNotEquals($property->fresh()->getOriginal('updated_at'), now()->toDateTimeString());

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'updated_at' => $property->fresh()->getOriginal('updated_at'), 
        ]);
    }

    /**
     * Remove attachment and check parent timestamp
     *
     * @param \App\Property $property
     */
    private function removeAttachmentAndCheckTimeStamp(Property $property)
    {
        // Revert time to today
        Carbon::setTestNow();

        Passport::actingAs($property->agent);

        Storage::fake();

        $response = $this->json('POST', "/api/v1/properties/{$property->id}/attachments", [
            'attachments' => [
                UploadedFile::fake()->create('1.pdf'), 
                UploadedFile::fake()->image('2.jpg'), 
                UploadedFile::fake()->create('3.docx'), 
            ], 
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id, 
            'updated_at' => now()->toDateTimeString(), 
        ]);

        $this->assertNotEquals($property->getOriginal('updated_at'), now()->toDateTimeString());
    }
}
