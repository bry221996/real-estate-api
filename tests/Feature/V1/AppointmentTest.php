<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Carbon\Carbon;
use App\Appointment;

class AppointmentTest extends TestCase
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
     * Test artisan command is working fine.
     */
    public function testCommandTaskCleanAllPreviousAppointments()
    {
        $property = $this->createProperty();

        $appointments = factory(Appointment::class, 20)->create([
            'property_id' => $property->id, 
            'user_id' => $this->createCustomer(),
            'date' => function () {
                // return Carbon::today()->subDays(rand(1, 30))->toDateString();
                return Carbon::today()->subDay()->toDateString();
            },
            'start_time' => collect($property->schedule->setup)->pluck('start_time')->filter()->first(), 
            'status_id' => function () {
                return collect([ 1, 2, 5 ])->random();
            }, 
        ]);

        $confirmedAppointments = $appointments->where('status_id', 1);
        $pendingAppointments = $appointments->whereIn('status_id', [ 2, 5 ]);

        \Artisan::call('task:clean-all-previous-appointment');

        // compare confirmed appointment count that is mark as completed
        $this->assertEquals($confirmedAppointments->count(), Appointment::completed()->count());

         // compare pending and reschedule appointment count that is mark as expired
        $this->assertEquals($pendingAppointments->count(), Appointment::where('status_id', 7)->count());
    }

    public function testTaskCleanAllPreviouseAppointmentsShouldNotAlterFutureAppointments()
    {
        $property = $this->createProperty();

        $appointments = factory(Appointment::class, 20)->create([
            'property_id' => $property->id, 
            'user_id' => $this->createCustomer(),
            'date' => function () {
                return Carbon::now()->addDays(rand(1, 30))->toDateString();
            },
            'start_time' => collect($property->schedule->setup)->pluck('start_time')->filter()->first(), 
            'status_id' => function () {
                return collect([ 1, 2 ])->random();
            }, 
        ]);

        \Artisan::call('task:clean-all-previous-appointment');

        // compare no confirmed appointment is altered
        $this->assertEquals(0, Appointment::where('status_id', 6)->count());

        // compare no pending appointment is altered
        $this->assertEquals(0, Appointment::where('status_id', 7)->count());
    }
}
