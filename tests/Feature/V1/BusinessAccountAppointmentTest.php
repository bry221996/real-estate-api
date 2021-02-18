<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentRejected;
use Carbon\Carbon;
use Notification;
use App\Appointment;

class BusinessAccountAppointmentTest extends TestCase
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
     * Test listing of business account appointments.
     */
    public function testListAppointments()
    {
        $properties = $this->createPropertiesWithPendingAppointments();

        $agent = $properties->first()->agent;

        Passport::actingAs($agent);

        $response = $this->json('GET', '/api/v1/account/appointments', []);
        
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
     * Test listing of business account appointments include property and property customer.
     */
    public function testAppointmentsIncludePropertyAndCustomerRelations()
    {
        $properties = $this->createPropertiesWithPendingAppointments();

        $agent = $properties->first()->agent;

        Passport::actingAs($agent);

        $response = $this->json('GET', '/api/v1/account/appointments?include=property,customer', []);
        
        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                [
                    'property' => [],
                    'customer' => [],
                ]
            ], 
        ]);
    }

    /**
     * Test business account confirms a pending booking.
     */
    public function testConfirmPendingCustomerBooking()
    {
        $properties = $this->createPropertiesWithPendingAppointments();

        $agent = $properties->first()->agent;

        Passport::actingAs($agent);

        $appointment = $agent->appointments()->pending()->first();

        $response = $this->json('POST', "/api/v1/appointments/{$appointment->id}/confirm", []);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id, 
            'status_id' => 1, 
        ]);

        Notification::assertSentTo(
            $appointment->customer,
            AppointmentConfirmed::class,
            function ($notification, $channels) use ($appointment) {
                return $notification->appointment->id == $appointment->id;
            }
        );
    }

    /**
     * Test business account confirms a rescheduled booking.
     */
    public function testConfirmRescheduledCustomerBooking()
    {
        $property = $this->createProperty();

        Passport::actingAs($property->agent);

        $appointment = factory(Appointment::class)->states('reschedule')->create([
            'user_id' => $this->createCustomer()->id, 
            'property_id' => $property->id, 
        ]);

        // check if the appointment is rescheduled
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id, 
            'status_id' => 5, 
        ]);

        $response = $this->json('POST', "/api/v1/appointments/{$appointment->id}/confirm", []);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id, 
            'status_id' => 1, 
        ]);

        Notification::assertSentTo(
            $appointment->customer,
            AppointmentConfirmed::class,
            function ($notification, $channels) use ($appointment) {
                return $notification->appointment->id == $appointment->id;
            }
        );
    }

    /**
     * Test business account rejects a pending booking.
     */
    public function testRejectPendingCustomerBooking()
    {
        $properties = $this->createPropertiesWithPendingAppointments();

        $agent = $properties->first()->agent;

        Passport::actingAs($agent);

        $appointment = $agent->appointments()->pending()->first();

        $response = $this->json('POST', "/api/v1/appointments/{$appointment->id}/reject", []);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id, 
            'status_id' => 3, 
        ]);

        Notification::assertSentTo(
            $appointment->customer,
            AppointmentRejected::class,
            function ($notification, $channels) use ($appointment) {
                return $notification->appointment->id == $appointment->id;
            }
        );
    }

    /**
     * Test business account rejects a rescheduled booking.
     */
    public function testRejectRescheduledCustomerBooking()
    {
        $property = $this->createProperty();

        Passport::actingAs($property->agent);

        $appointment = factory(Appointment::class)->states('reschedule')->create([
            'user_id' => $this->createCustomer()->id, 
            'property_id' => $property->id, 
        ]);

        // check if the appointment is rescheduled
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id, 
            'status_id' => 5, 
        ]);

        $response = $this->json('POST', "/api/v1/appointments/{$appointment->id}/reject", []);
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id, 
            'status_id' => 3, 
        ]);

        Notification::assertSentTo(
            $appointment->customer,
            AppointmentRejected::class,
            function ($notification, $channels) use ($appointment) {
                return $notification->appointment->id == $appointment->id;
            }
        );
    }

    /**
     * Confirm one booking then reject others with the same booking.
     */
    public function testConfirmAppointmentAndRejectOtherWithTheSameSchedule()
    {
        $properties = $this->createPropertiesWithPendingAppointments();

        $property = $properties->first();
        $agent = $property->agent;

        Passport::actingAs($agent);

        $appointment = $agent->appointments()->pending()->first();

        factory(\App\Appointment::class)->create([
            'user_id' => $this->createCustomer(), 
            'property_id' => $appointment->property_id, 
            'date' => $appointment->date, 
            'start_time' => $appointment->start_time, 
            'end_time' => $appointment->end_time, 
        ]);

        $duplicateAppointments = \App\Appointment::where([
                [ 'property_id', $property->id ], 
                [ 'date', $appointment->date ], 
                [ 'start_time', $appointment->start_time ], 
                [ 'end_time', $appointment->end_time ], 
                [ 'id', '!=', $appointment->id ], 
            ])
            ->with('customer')
            ->get();

        $this->assertTrue($duplicateAppointments->count() >= 1);

        $response = $this->json('POST', "/api/v1/appointments/{$appointment->id}/confirm", []);

        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id, 
            'status_id' => 1, 
        ]);

        $duplicateAppointments->each(function ($appointment) {
            $this->assertDatabaseHas('appointments', [
                'id' => $appointment->id, 
                'status_id' => 3, 
            ]);
        });

        $rejectedUsers = $duplicateAppointments->map(function ($appointment) {
            return $appointment->customer;
        });

        Notification::assertSentTo(
            $rejectedUsers,
            AppointmentRejected::class,
            function ($notification, $channels) use ($appointment) {
                return $notification->appointment->id == $appointment->id;
            }
        );
    }

    /**
     * Create data for properties with pending appointments.
     * 
     * @return array
     */
    private function createPropertiesWithPendingAppointments()
    {
        $properties = $this->createProperties();

        $properties->each(function ($property) {
            $schedules = collect($property->schedule->setup)->filter->start_time;

            $schedules->each(function ($schedule) use ($property) {
                $customer = $this->createCustomer();

                factory(\App\Appointment::class)->create([
                    'user_id' => $customer->id, 
                    'property_id' => $property->id, 
                    'start_time' => $schedule['start_time'], 
                ]);
            });
        });

        return $properties;
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

    /**
     * List customer bookings with previous details.
     */
    public function testListWithRequestQueryScopeWithPreviousDetails()
    {
        $properties = $this->createPropertiesWithPendingAppointments();

        Passport::actingAs($properties->first()->agent);

        $response = $this->json('GET', '/api/v1/account/appointments?scope=with_previous_details,etc', []);

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
}
