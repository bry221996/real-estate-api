<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use App\Jobs\NotifyUsersTwoHoursBeforeAppointment;
use App\Notifications\UserAppointmentReminder;
use Bus;
use Queue;
use Notification;

class UserAppointmentNotficationTest extends TestCase
{
    use RefreshDatabase, TestHelperTrait;

    private $property;

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

        $this->property = $this->createProperty();

        $this->customerBookings = factory(\App\Appointment::class, 3)->states('confirmed')->create([
            'property_id' => $this->property, 
            'user_id' => $this->createCustomer()->getKey(), 
            'date' => today()->toDateString(), 
            'start_time' => now()->addHours(2)->toDateTimeString(), 
        ]);
    }

    /**
     * Test dispatch notification for the customers with appointment in two hours.
     */
    public function testDispatchNotificationForUsersWithAppointmentsInTwoHours()
    {
        Bus::fake();

        // 3 hrs before appointment
        factory(\App\Appointment::class, 10)->states('confirmed')->create([
            'property_id' => $this->property, 
            'user_id' => $this->createCustomer()->getKey(), 
            'date' => today()->toDateString(), 
            'start_time' => now()->addHours(3)->toDateTimeString(), 
        ]);

        NotifyUsersTwoHoursBeforeAppointment::dispatch();

        $this->customerBookings->load([ 'customer', 'property.agent' ]);

        Bus::assertDispatched(
            NotifyUsersTwoHoursBeforeAppointment::class, 
            function ($job) {
                return $job->appointments->pluck('user_id')
                        ->diff($this->customerBookings->pluck('user_id'))
                        ->isEmpty()
                    && $job->appointments->pluck('property.agent')
                        ->diff($this->customerBookings->pluck('property.agent'))
                        ->isEmpty();
            }
        );
    }

    /**
     * Test the dispatch notification is queued.
     */
    public function testNotificationForUsersWithAppointmentsInTwoHoursMustBeQueued()
    {
        Queue::fake();
        
        NotifyUsersTwoHoursBeforeAppointment::dispatch();

        Queue::assertPushed(NotifyUsersTwoHoursBeforeAppointment::class);
    }

    /**
     * Test the dispatch notification is sent.
     */
    public function testNotificationForUsersWithAppointmentsInTwoHoursIsSent()
    {
        Notification::fake();
        
        NotifyUsersTwoHoursBeforeAppointment::dispatch();

        $this->customerBookings->load([ 'customer', 'property.agent' ]);

        Notification::assertSentTo(
            [ $this->customerBookings->pluck('customer') ], 
            UserAppointmentReminder::class
        );

        Notification::assertSentTo(
            [ $this->customerBookings->pluck('property.agent') ], 
            UserAppointmentReminder::class
        );
    }
}
