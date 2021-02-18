<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;

class BusinessAccountScheduleTest extends TestCase
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

        \Notification::fake();

        $this->beforeApplicationDestroyed(function () {
            $this->resetDatabaseTablesIncrements();
        });
    }

    /**
     * Test listing of schedules.
     */
    public function testScheduleListing()
    {
        $businessAccount = $this->createBusinessAccount();
        $businessAccount->schedule()->delete();

        Passport::actingAs($businessAccount);

        // recreate the schedule
        $schedule = factory(\App\BusinessAccountSchedule::class)->create([
            'user_id' => auth()->id(), 
            'schedule_type_id' => 1, 
        ]);

        $schedule = collect($schedule);

        $response = $this->json('GET', '/api/v1/account/schedules', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => $schedule->get('setup'), 
            'meta' => [
                'schedule_type_id' => $schedule->get('schedule_type_id'), 
            ], 
        ]);
    }

    /**
     * Create schedule for regular business hours.
     * Monday to Friday schedule.
     */
    public function testCreateRegularBusinessHours()
    {
        $businessAccount = $this->createBusinessAccount();
        $businessAccount->schedule()->delete();

        Passport::actingAs($businessAccount);

        $scheduleTypeId = 1;

        $response = $this->json('POST', '/api/v1/account/schedules', [
            'schedule_type_id' => $scheduleTypeId,
        ]);

        $response->assertStatus(200);

        $this->checkScheduleType($scheduleTypeId);
    }

    /**
     * Create schedule for weekends.
     * Saturday and Sunday.
     */
    public function testCreateWeeklyBusinessHours()
    {
        $businessAccount = $this->createBusinessAccount();
        $businessAccount->schedule()->delete();

        Passport::actingAs($businessAccount);

        $scheduleTypeId = 2;

        $response = $this->json('POST', '/api/v1/account/schedules', [
            'schedule_type_id' => $scheduleTypeId,
        ]);

        $response->assertStatus(200);

        $this->checkScheduleType($scheduleTypeId);
    }

    /**
     * Create schedule for regular business hours.
     * Monday to Friday schedule.
     */
    public function testCreateAffterOfficeBusinessHours()
    {
        $businessAccount = $this->createBusinessAccount();
        $businessAccount->schedule()->delete();

        Passport::actingAs($businessAccount);

        $scheduleTypeId = 3;

        $response = $this->json('POST', '/api/v1/account/schedules', [
            'schedule_type_id' => $scheduleTypeId,
        ]);

        $response->assertStatus(200);

        $this->checkScheduleType($scheduleTypeId);
    }
    /**
     * Create schedule for weekdays after office.
     * Saturday and Sunday.
     */
    public function testCreate247OfficeBusinessHours()
    {
        $businessAccount = $this->createBusinessAccount();
        $businessAccount->schedule()->delete();

        Passport::actingAs($businessAccount);

        $scheduleTypeId = 4;

        $response = $this->json('POST', '/api/v1/account/schedules', [
            'schedule_type_id' => $scheduleTypeId,
        ]);

        $response->assertStatus(200);

        $this->checkScheduleType($scheduleTypeId);
    }

    /**
     * When creating schedules missing days should be filled.
     */
    public function testCreateSchedulesShouldBeSevenDays()
    {
        $businessAccount = $this->createBusinessAccount();
        $businessAccount->schedule()->delete();

        Passport::actingAs($businessAccount);

        $scheduleTypeId = 4;

        $response = $this->json('POST', '/api/v1/account/schedules', [
            'schedule_type_id' => $scheduleTypeId,
        ]);

        $response->assertStatus(200);

        $this->checkScheduleType($scheduleTypeId);

        $uniqueDays = collect(auth()->user()->schedule->setup)->pluck('day')->unique();

        // should be equal to 1 - 7 
        $this->assertEquals($uniqueDays->toArray(), range(1, 7));
    }

    /**
     * Test updating of schedules.
     */
    public function testUpdateSchedule()
    {
        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($businessAccount);

        $existingSchedule = factory(\App\BusinessAccountSchedule::class)->create([ 'user_id' => auth()->id() ]);

        $newScheduleTypeId = collect([1, 2, 3, 4])->diff($existingSchedule->schedule_type_id)->random();

        $response = $this->json('PUT', '/api/v1/account/schedules', [ 'schedule_type_id' => $newScheduleTypeId ]);

        $response->assertStatus(200);

        $this->checkScheduleType($newScheduleTypeId);
    }

    /**
     * Test business account should only have 1 schedule.
     */
    public function testBuinessAccoutShouldOnlyHaveOneSchedule()
    {
        $businessAccount = $this->createBusinessAccount();

        $schedules = \App\BusinessAccountSchedule::where('user_id', $businessAccount->id)->get();
        
        $this->assertCount(1, $schedules);

        // add another schedule
        $this->json('POST', '/api/v1/account/schedules', [
            'schedule_type_id' => 4,
        ]);

        // refresh schedules
        $schedules->fresh();

        $this->assertCount(1, $schedules);
    }

    /**
     * Test schedule according to type.
     *
     * @param int $scheduleTypeId
     * @return void
     */
    private function checkScheduleType($scheduleTypeId)
    {
        $expectedData = factory(\App\BusinessAccountSchedule::class)->make([
            'user_id' => auth()->id(), 
            'schedule_type_id' => $scheduleTypeId, 
        ]);

        $this->assertEquals(
            auth()->user()->schedule->first(['user_id', 'schedule_type_id', 'setup'])->toArray(),
            $expectedData->toArray()
        );
    }
}
