<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Laravel\Passport\Passport;
use App\Notifications\AppointmentRequest;
use Notification;
use Carbon\Carbon;

class RequestAppointmentSmsMessageTest extends TestCase
{
    use RefreshDatabase, TestHelperTrait;

    public $customer;
    public $property;

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

        $this->customer = $this->createCustomer();

        $this->property = $this->createProperty();

        Passport::actingAs($this->customer);
    }

    /**
     * Test book a property message notification.
     */
    public function testBookPropertyMessage()
    {
        $appointment = factory(\App\Appointment::class)->make([
            'user_id' => $this->customer->id, 
            'property_id' => $this->property->id, 
        ]);

        $response = $this->json('POST', "/api/v1/properties/{$this->property->id}/bookings", [
            'date' => $appointment->date, 
            'start_time' => $appointment->start_time, 
        ]);
        
        $response->assertStatus(200);

        $dateTime = Carbon::parse("{$appointment->date} {$appointment->start_time}")
            ->format('d M Y h:i A');

        $message = "
            Lazatu Booking Request
            
            FROM: {$this->customer->full_name}
            DATE: {$dateTime}
            LOCATION: {$this->property->formatted_address}
        ";

        $message = preg_replace('/\n\s+/', PHP_EOL, trim($message));

        Notification::assertSentTo(
            $this->property->agent,
            AppointmentRequest::class,
            function ($notification, $channels) use ($message) {
                return $notification->message == $message;
            }
        );
    }
}
