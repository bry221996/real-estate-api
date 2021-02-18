<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Laravel\Passport\Passport;
use App\Notifications\AppointmentRejected;
use Notification;
use Carbon\Carbon;

class RejectedAppointmentSmsMessageTest extends TestCase
{
    use RefreshDatabase, TestHelperTrait;

    public $customer;
    public $property;
    public $appointment;

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

        $this->property = $this->createProperty([ 'property_type_id' => rand(1, 2) ]);

        $this->appointment = factory(\App\Appointment::class)->create([
            'user_id' => $this->customer->id, 
            'property_id' => $this->property->id, 
        ]);

        Passport::actingAs($this->property->agent);
    }

    /**
     * Test reject booking message to condominium or office property.
     */
    public function testRejectedBookingMessageToCondominiumOrOfficeProperty()
    {
        $response = $this->json('POST', "/api/v1/appointments/{$this->appointment->id}/reject", []);
        
        $response->assertStatus(200);

        Notification::assertSentTo(
            $this->customer,
            AppointmentRejected::class,
            function ($notification, $channels) {
                return $notification->message == $this->generateMessage();
            }
        );
    }

    /**
     * Test reject booking message to house and lot property.
     */
    public function testRejectedBookingMessageToHouseAndLot()
    {
        $this->property->update([ 'property_type_id' => 3 ]);

        $response = $this->json('POST', "/api/v1/appointments/{$this->appointment->id}/reject", []);
        
        $response->assertStatus(200);

        Notification::assertSentTo(
            $this->customer,
            AppointmentRejected::class,
            function ($notification, $channels) {
                return $notification->message == $this->generateMessage();
            }
        );
    }

    /**
     * Generate the message according to property status type.
     */
    private function generateMessage()
    {
        $dateTime = Carbon::parse("{$this->appointment->date} {$this->appointment->start_time}")
            ->format('d M Y h:i A');

        $this->property->city = str_contains(strtolower($this->property->city), 'city')
            ? $this->property->city
            : str_finish($this->property->city, ' city');

        $address = trim("House, {$this->property->street}, {$this->property->city}");
            
        if (collect([ 1, 2 ])->contains($this->property->property_type_id)) {
            $address = preg_replace('/\n\s+/', ' ', trim("
                {$this->property->unit}, {$this->property->building_name},
                {$this->property->city}
            "));
        }

        $message = "
            Hi Lazatu Customer, 

            Your booking request for {$dateTime} at {$address} has been declined. You may reschedule your booking.
        ";

        return preg_replace('/\n\s+/', PHP_EOL, trim($message));
    }
}
