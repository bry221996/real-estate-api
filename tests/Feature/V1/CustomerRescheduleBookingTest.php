<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Notification;
use App\Notifications\AppointmentRequest;
use Carbon\Carbon;
use App\Appointment;

class CustomerRescheduleBookingTest extends TestCase
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

        Notification::fake();
    }
    
    /**
     * Customer reschedule a pending booking. 
     * Appointment should only be updated but added on appointment history.
     */
    public function testReschedulePendingBookingShoulOnlyBeUpdated()
    {
        $this->checkRescheduleOfBookingWithStatusId(2); // pending status_id
    }

    /**
     * Customer reschedule a confirmed booking.
     * Appointment should only be updated but added on appointment history.
     */
    public function testRescheduleConfirmedBookingShoulOnlyBeUpdated()
    {
        $this->checkRescheduleOfBookingWithStatusId(1); // confirmed status_id
    }

    /**
     * Customer reschedule rejected booking.
     * Appointment should only be updated but added on appointment history.
     */
    public function testRescheduleRejectedBookingShoulOnlyBeUpdated()
    {
        $this->checkRescheduleOfBookingWithStatusId(3); // pending status_id
    }

    /**
     * Customer reschedule confirmed booking scheduled today.
     */
    public function testFailRescheduleConfirmedBookingThatIsScheduledToday()
    {
        $property = $this->createProperty();

        $customer = $this->createCustomer();

        // create confirmed booking for the property for today
        $booking = factory(Appointment::class)->create([
            'property_id' => $property->id, 
            'user_id' => $customer->id, 
            'status_id' => 1, // confirmed
            'date' => now()->toDateString(), 
        ]);

        // verify the confirmed booking exists
        $this->assertTrue($customer->bookings()->confirmed()->get()->isNotEmpty());

        Passport::actingAs($customer);

        $data = [
            'date' => Carbon::parse($booking->date)->format('Y-m-d'), 
            'start_time' => Carbon::parse($booking->start_time)->format('H:i'), 
        ];

        $response = $this->json('PUT', "/api/v1/properties/{$property->id}/bookings/current", $data);

        $response->assertStatus(422);
        
        $this->assertDatabaseMissing('appointments', [
            'id' => $booking->id, 
            'status_id' => 5, // rescheduled
        ]);

        $this->assertDatabaseHas('appointments', $booking->getOriginal());
    }

    /**
     * Test reschedule of booking.
     *
     * @param int $appointmentStatusId
     * @return void
     */
    private function checkRescheduleOfBookingWithStatusId(int $appointmentStatusId)
    {
        $property = $this->createProperty();

        $customer = $this->createCustomer();

        $booking = factory(Appointment::class)->create([
            'property_id' => $property->id, 
            'user_id' => $customer->id, 
            'status_id' => $appointmentStatusId,
        ]);

        $this->assertDatabaseHas('appointments', [
            'property_id' => $property->id, 
            'user_id' => $customer->id, 
            'status_id' => $appointmentStatusId,
        ]);

        Passport::actingAs($customer);

        // reschedule to next week
        $data = [
            'date' => Carbon::parse($booking->date)->addWeek()->format('Y-m-d'), 
            'start_time' => Carbon::parse($booking->start_time)->format('H:i'), 
        ];

        $response = $this->json('PUT', "/api/v1/properties/{$property->id}/bookings/current", $data);

        $response->assertStatus(200);

        // expected database data
        $appointmentData = [
            'property_id' => $property->id, 
            'user_id' => $customer->id, 
            'status_id' => $appointmentStatusId == 1 ? 5 : 2,
        ];

        // check booking if updated
        $this->assertDatabaseHas('appointments', $appointmentData + [
            'id' => $booking->id, 
        ]);

        // check if details are copied to histories
        $this->assertDatabaseHas('appointment_histories', $appointmentData + [
            'appointment_id' => $booking->id, 
        ]);

        // check previous details are also in histories
        $this->assertDatabaseHas('appointment_histories', [
            'appointment_id' => $booking->id, 
        ] + $appointmentData);

        // assert the booking request is sent to the business account
        Notification::assertSentTo(
            $property->agent,
            AppointmentRequest::class,
            function ($notification, $channels) use ($data, $property) {
                return $notification->appointment->date == $data['date']
                    && $notification->appointment->start_time == $data['start_time']
                    && $notification->property->id == $property->id;
            }
        );
    }
}
