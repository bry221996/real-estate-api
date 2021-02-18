<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use App\Notifications\AppointmentRequest;
use Carbon\Carbon;
use App\Appointment;
use App\User;

class CustomerBookingTest extends TestCase
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
     * Property booking.
     */
    public function testBookAProperty()
    {
        $property = $this->createProperty();

        $customer = $this->createCustomer();

        Passport::actingAs($customer);

        $firstDaySlot = collect($property->schedule->setup)->filter->start_time->first();

        $dateTime = Carbon::parse($firstDaySlot['start_time'])->addDay()->toDateTimeString();

        $data = [
            'date' => Carbon::parse($dateTime)->format('Y-m-d'), 
            'start_time' => Carbon::parse($dateTime)->format('H:i'), 
        ];

        $response = $this->json('POST', "/api/v1/properties/{$property->id}/bookings", $data);
        
        $response->assertStatus(200);

        $expectedData = $data + [
            'user_id' => auth()->id(),
            'property_id' => $property->id,
        ];

        $this->assertDatabaseHas('appointments', $expectedData);
        
        $this->assertDatabaseHas('appointment_histories', $expectedData);

        $usersToNotify = User::isAdmin()->get();

        Notification::assertSentTo(
            $usersToNotify->push($property->agent),
            AppointmentRequest::class,
            function ($notification, $channels) use ($data, $property) {
                return $notification->appointment->date == $data['date']
                    && $notification->appointment->start_time == $data['start_time']
                    && $notification->property->id == $property->id;
            }
        );
    }

    /**
     * Test booking a confirmed appointment should fail.
     */
    public function testFailBookAConfirmedAppointmentDateTime()
    {
        $property = $this->createProperty();

        $customer = $this->createCustomer();

        Passport::actingAs($customer);

        $confirmedAppointment = factory(\App\Appointment::class)->create([
            'property_id' => $property->id, 
            'user_id' => $this->createCustomer(), 
            'status_id' => 1, 
        ]);

        $response = $this->json('POST', "/api/v1/properties/{$property->id}/bookings", [
            'date' => $confirmedAppointment->date,
            'start_time' => $confirmedAppointment->start_time,
        ]);
        
        $response->assertStatus(422);
    }

    /**
     * List customer bookings.
     */
    public function testListBookings()
    {
        $properties = $this->createProperties();

        $customer = $this->createCustomer();

        $properties->each(function ($property) use ($customer) {
            factory(\App\Appointment::class)->create([
                'user_id' => $customer->id, 
                'property_id' => $property->id, 
            ]);
        });

        Passport::actingAs($customer);

        $response = $this->json('GET', '/api/v1/account/bookings', []);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [ $this->getAppointmentJsonStructure() ], 
            'links' => [
                'first',
                'last' ,
                'prev' ,
                'next' ,
            ],
            'meta' => [
                'current_page',
                'from',        
                'last_page',   
                'path',        
                'per_page',    
                'to',          
                'total',       
            ], 
        ]);
    }

    /**
     * List customer bookings include property and property agent.
     */
    public function testListIncludePropertyAndAngent()
    {
        $properties = $this->createProperties();

        $customer = $this->createCustomer();

        $properties->each(function ($property) use ($customer) {
            factory(\App\Appointment::class)->create([
                'user_id' => $customer->id, 
                'property_id' => $property->id, 
            ]);
        });

        Passport::actingAs($customer);

        $response = $this->json('GET', '/api/v1/account/bookings?include=property,property.agent', []);

        $response->assertJson([
            'data' => [
                [
                    'property' => [
                        'agent' => [], 
                    ]
                ]
            ], 
        ]);
    }

    /**
     * List customer bookings with previous details.
     */
    public function testListWithRequestQueryScopeWithPreviousDetails()
    {
        $properties = $this->createProperties();

        $customer = $this->createCustomer();

        $properties->each(function ($property) use ($customer) {
            factory(\App\Appointment::class)->create([
                'user_id' => $customer->id, 
                'property_id' => $property->id, 
            ]);
        });

        Passport::actingAs($customer);

        $response = $this->json('GET', '/api/v1/account/bookings?scope=with_previous_details,etc', []);

        $response->assertStatus(200);

        $structure = array_merge([
            'previous_appointment_details',
            'is_rescheduled_from_confirmed_status',
        ], $this->getAppointmentJsonStructure());

        $response->assertJsonStructure([
            'data' => [ $structure ], 
            'links' => [
                'first',
                'last' ,
                'prev' ,
                'next' ,
            ],
            'meta' => [
                'current_page',
                'from',        
                'last_page',   
                'path',        
                'per_page',    
                'to',          
                'total',       
            ], 
        ]);
    }

    /**
     * Get last booking of user to property.
     */
    public function testGetAuthUserCurrentBookingOnProperty()
    {
        $property = $this->createProperty();

        $customer = $this->createCustomer();

        Passport::actingAs($customer);

        // create booking to property
        $booking = factory(Appointment::class)->create([
            'property_id' => $property->id,
            'user_id' => $customer->id, 
        ]);

        $response = $this->json('GET', "api/v1/properties/{$property->id}/booking_status", []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => $booking->fresh()->toArray(), 
        ]);
    }

    /**
     * Get last booking of user to property where user has no booking.
     */
    public function testGetEmptyAuthUserCurrentBooking()
    {
        $property = $this->createProperty();

        $customer = $this->createCustomer();

        Passport::actingAs($customer);

        $response = $this->json('GET', "api/v1/properties/{$property->id}/booking_status", []);

        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    }

    /**
     * Get default sructure for appointment model
     *
     * @return array
     */
    private function getAppointmentJsonStructure()
    {
        return [
            'id',
            'user_id',
            'property_id',
            'date',
            'start_time',
            'end_time',
            'status_id',
            'created_at',
            'updated_at',
            'status',
        ];
    }
}
