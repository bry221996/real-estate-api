<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Carbon\Carbon;
use App\Appointment;

class AdminAccountAppointmentTest extends TestCase
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
     * Test listing of business account appointments.
     */
    public function testListAppointments()
    {
        $properties = $this->createPropertiesWithPendingAppointments();

        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $response = $this->json('GET', '/api/v1/appointments', []);
        
        $response->assertStatus(200);

        $this->checkResponseStructure($response);
    }

    /**
     * Test listing of business account appointments include property and property customer.
     */
    public function testAppointmentsIncludePropertyAndCustomerRelations()
    {
        $properties = $this->createPropertiesWithPendingAppointments();

        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $response = $this->json('GET', '/api/v1/appointments?include=property,property.agent,customer', []);
        
        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                [
                    'property' => [
                        'agent' => [], 
                    ],
                    'customer' => [],
                ]
            ], 
        ]);
    }

    /**
     * Test when admin request for list of business account appointments.
     */
    public function testListBusinessAccountAppointments()
    {
        // create init data
        $this->createPropertiesWithPendingAppointments();

        $properties = $this->createPropertiesWithPendingAppointments();

        $agent = $properties->random()->agent;

        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $response = $this->json('GET', "/api/v1/users/{$agent->id}/appointments", []);

        $response->assertStatus(200);

        $this->checkResponseStructure($response);
    }

    /**
     * Test includes of relationships for list of business account appointments.
     */
    public function testListBusinessAccountAppointmentsIncludeRelations()
    {
        // create init data
        $this->createPropertiesWithPendingAppointments();

        $properties = $this->createPropertiesWithPendingAppointments();

        $agent = $properties->random()->agent;

        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $response = $this->json(
            'GET', 
            "/api/v1/users/{$agent->id}/appointments?include=property,property.agent,customer", 
            []
        );

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                [
                    'property' => [
                        'agent' => [], 
                    ],
                    'customer' => [],
                ]
            ], 
        ]);
    }

    /**
     * Check response structure.
     *
     * @param \Illuminate\Http\JsonResponse $response
     */
    public function checkResponseStructure($response)
    {
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
}
