<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Notification;
use App\Notifications\AppointmentCancelled;
use Tests\Feature\V1\Setup\AppointmentFactory;

class CustomerCancelBookingTest extends TestCase
{
    use RefreshDatabase;

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
     * Customer cancels confirmed booking.
     */
    public function testCancelConfirmedBooking()
    {
        $this->cancelBookingWithStatus('confirmed');
    }
    
    /**
     * Customer cancels pending booking.
     */
    public function testCancelPendingBooking()
    {
        $this->cancelBookingWithStatus('pending');
    }

    /**
     * Customer cancels rejected booking.
     */
    public function testCancelRejectedBooking()
    {
        $this->cancelBookingWithStatus('rejected');
    }

    /**
     * Test the customer can cancel the appointment six hours before the appointment.
     */
    public function testCanCancelSixHoursBeforeAppointment()
    {
        $booking = (new AppointmentFactory())->setStates([ 'confirmed' ])->create([
            'date' => now()->toDateString(), 
            'start_time' => now()->addHours(6)->toTimeString(), 
        ]);

        $property = $booking->property;

        $customer = $booking->customer;

        Passport::actingAs($customer);

        $response = $this->json('DELETE', "/api/v1/properties/{$property->id}/bookings/current", []);

        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $booking->id, 
            'property_id' => $property->id, 
            'user_id' => $customer->id, 
            'status_id' => 4, 
        ]);
    }

    /**
     * Test the customer can cancel the appointment in random days before the appointment.
     */
    public function testCanCancelInRandomDaysBeforeAppointment()
    {
        $booking = (new AppointmentFactory())->setStates([ 'confirmed' ])->create([
            'date' => today()->addDays(rand(2, 10))->toDateString(), 
        ]);

        $property = $booking->property;

        $customer = $booking->customer;

        Passport::actingAs($customer);

        $response = $this->json('DELETE', "/api/v1/properties/{$property->id}/bookings/current", []);

        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $booking->id, 
            'property_id' => $property->id, 
            'user_id' => $customer->id, 
            'status_id' => 4, 
        ]);
    }

    /**
     * Test the customer fail to cancel the appointment six hours before the appointment.
     */
    public function testFailCancelSixHoursBeforeAppointment()
    {
        $booking = (new AppointmentFactory())->setStates([ 'confirmed' ])->create([
            'date' => now()->toDateString(), 
            'start_time' => now()->addHours(2)->toTimeString(), 
        ]);

        $property = $booking->property;

        $customer = $booking->customer;

        Passport::actingAs($customer);

        $response = $this->json('DELETE', "/api/v1/properties/{$property->id}/bookings/current", []);

        $response->assertStatus(422);
    }

    /**
     * Test cancelling of booking with the specified status.
     *
     * @param string $appointmentStatusId
     * @return void
     */
    private function cancelBookingWithStatus(string $status)
    {
        $booking = (new AppointmentFactory())->setStates([ $status ])->create([
            'date' => now()->toDateString(), 
            'start_time' => now()->addHours(6)->toTimeString(), 
        ]);

        $property = $booking->property;

        $customer = $booking->customer;

        $this->assertDatabaseHas('appointment_histories', $booking->getOriginal());

        Passport::actingAs($customer);

        $response = $this->json('DELETE', "/api/v1/properties/{$property->id}/bookings/current", []);

        $response->assertStatus(200);

        $appointmentData = [
            'property_id' => $property->id, 
            'user_id' => $customer->id, 
            'status_id' => 4, 
        ];

        // check if details are updated
        $this->assertDatabaseHas('appointments', $appointmentData + [
            'id' => $booking->id, 
        ]);

        // check if details are copied to histories
        $this->assertDatabaseHas('appointment_histories', $appointmentData + [
            'appointment_id' => $booking->id, 
        ]);

        // check previous details are on histories
        $this->assertDatabaseHas('appointment_histories', $appointmentData + [
            'appointment_id' => $booking->id,
            'property_id' => $property->id, 
            'user_id' => $customer->id, 
            'status_id' => $booking->status_id, 
        ]);

        Notification::assertSentTo(
            $booking->property->agent,
            AppointmentCancelled::class,
            function ($notification, $channels) use ($booking) {
                return $notification->appointment->id == $booking->id;
            }
        );
    }
}
