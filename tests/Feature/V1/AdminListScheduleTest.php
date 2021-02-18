<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Carbon\Carbon;
use App\Appointment;

class AdminListScheduleTest extends TestCase
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
     * Test when admin request list of business account schedule.
     */
    public function testListBusinessAccountWeeklySchedule()
    {
        $businessAccount = $this->createBusinessAccount();
        $businessAccount->schedule()->delete();

        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        // recreate the schedule
        $schedule = factory(\App\BusinessAccountSchedule::class)->create([
            'user_id' => $businessAccount->id, 
            'schedule_type_id' => 1, 
        ]);

        $schedule = collect($schedule);

        $response = $this->json('GET', "/api/v1/users/{$businessAccount->id}/schedules", []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => $schedule->get('setup'), 
            'meta' => [
                'schedule_type_id' => $schedule->get('schedule_type_id'), 
            ], 
        ]);
    }
}