<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;

class StatisticsTest extends TestCase
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
     * Test get customers statistics summary.
     */
    public function testCheckCustomersSummary()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $expectedData = [
            'total' => 30, 
            'verified' => 20, 
            'unverified' => 10, 
            'registered_today' => 10, 
        ];

        // customer registered last month - verified
        $this->createCustomers($expectedData['verified'] - $expectedData['registered_today'], true, [
            'created_at' => now()->subMonth()->toDateTimeString(), 
            'updated_at' => now()->subMonth()->toDateTimeString(), 
        ]);

        // customer registered last month - unverified
        $this->createCustomers($expectedData['unverified'], false, [
            'created_at' => now()->subMonth()->toDateTimeString(), 
            'updated_at' => now()->subMonth()->toDateTimeString(), 
        ]);

        // customer registered today verified 
        $this->createCustomers($expectedData['registered_today']);

        $response = $this->json('GET', '/api/v1/statistics', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'customers' => $expectedData, 
            ]
        ]);
    }

    /**
     * Test get business accounts statistics summary.
     */
    public function testCheckBusinessAccountsSummary()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $expectedData = [
            'total' => 30, 
            'verified' => 20, 
            'unverified' => 10, 
            'registered_today' => 10, 
        ];

        // customer registered last month - verified
        $this->createBusinessAccounts($expectedData['verified'] - $expectedData['registered_today'], true, [
            'created_at' => now()->subMonth()->toDateTimeString(), 
            'updated_at' => now()->subMonth()->toDateTimeString(), 
        ]);

        // customer registered last month - unverified
        $this->createBusinessAccounts($expectedData['unverified'], false, [
            'created_at' => now()->subMonth()->toDateTimeString(), 
            'updated_at' => now()->subMonth()->toDateTimeString(), 
        ]);

        // customer registered today verified 
        $this->createBusinessAccounts($expectedData['registered_today']);

        $response = $this->json('GET', '/api/v1/statistics', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'business_accounts' => $expectedData, 
            ]
        ]);
    }

    /**
     * Test get properties statistics summary.
     */
    public function testCheckPropertiesSummary()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $expectedData = [
            'published' => 5,
            'pending' => 6,
            'rejected' => 7,
            'expired' => 8,
        ];

        $expectedData['total'] = collect($expectedData)->sum();

        // published properties
        $this->createProperties($expectedData['published']);

        // pending properties
        $this->createProperties($expectedData['pending'], [
            'property_status_id' => 2, 
        ]);

        // rejected properties
        $this->createProperties($expectedData['rejected'], [
            'property_status_id' => 3, 
        ]);

        // expired properties
        $this->createProperties($expectedData['expired'], [
            'property_status_id' => 1, 
            'expired_at' => now()->subDay()->toDateTimeString(), 
        ]);

        $response = $this->json('GET', '/api/v1/statistics', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'properties' => $expectedData, 
            ]
        ]);
    }

    /**
     * Test get appointments statistics summary.
     */
    public function testAppointmentsSummary()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $initialCounts = [
            'completed' => 5,
            'confirmed' => 6,
            'pending' => 7,
            'rejected' => 8,
        ];

        $expectedData = [
            'completed' => $initialCounts['completed'],
            'confirmed' => $initialCounts['confirmed'],
            'pending' => $initialCounts['pending'],
            'rejected' => $initialCounts['rejected'],
        ];

        $expectedData['total'] = collect($initialCounts)->sum();
        
        // completed appointments
        $this->createPropertiesAndAppointments($initialCounts['completed'], [
            'status_id' => 6, 
        ]);

        // confirmed appointments
        $this->createPropertiesAndAppointments($initialCounts['confirmed'], [
            'status_id' => 1, 
        ]);

        // pending appointments
        $this->createPropertiesAndAppointments($initialCounts['pending']);

        // rejected appointments
        $this->createPropertiesAndAppointments($initialCounts['rejected'], [
            'status_id' => 3, 
        ]);

        $response = $this->json('GET', '/api/v1/statistics', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'appointments' => $expectedData, 
            ]
        ]);
    }

    /**
     * Test get appointments['appointments_today'] statistics summary.
     */
    public function testAppointmentsTodaySummary()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $expectedData = [
            'appointments_today' => 9,
        ];

        // todays confirmed appointments
        $this->createPropertiesAndAppointments($expectedData['appointments_today'], [
            'date' => now()->format('Y-m-d'), 
            'status_id' => 1, 
        ]);

        $response = $this->json('GET', '/api/v1/statistics', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'appointments' => $expectedData, 
            ]
        ]);
    }

    /**
     * Test get appointments['appointments_this_week'] statistics summary.
     */
    public function testAppointmentsThisWeekSummary()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $expectedData = [
            'appointments_this_week' => 9,
        ];
        
        // week's confirmed appointments
        $this->createPropertiesAndAppointments($expectedData['appointments_this_week'], [
            'date' => now()->startOfWeek()->format('Y-m-d'), 
            'status_id' => 1, 
        ]);

        $response = $this->json('GET', '/api/v1/statistics', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'appointments' => $expectedData, 
            ]
        ]);
    }

    /**
     * Test get appointments['appointments_this_month'] statistics summary.
     */
    public function testAppointmentsThisMonthSummary()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $expectedData = [
            'appointments_this_month' => 9,
        ];
        
        // months's confirmed appointments
        $this->createPropertiesAndAppointments($expectedData['appointments_this_month'], [
            'date' => now()->startOfMonth()->format('Y-m-d'), 
            'status_id' => 1, 
        ]);

        $response = $this->json('GET', '/api/v1/statistics', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'appointments' => $expectedData, 
            ]
        ]);
    }

    /**
     * Create 1 appointment and 1 property,
     *
     * @param int $count
     * @param array $appointmentData
     * @return void
     */
    private function createPropertiesAndAppointments($count = 1, $appointmentData = [])
    {
        $properties = $this->createProperties($count);

        $properties->each(function ($property) use ($appointmentData) {
            $schedules = collect($property->schedule->setup)->filter->start_time;

            $schedule = $schedules->first();

            $customer = $this->createCustomer();

            factory(\App\Appointment::class)->create([
                'user_id' => $appointmentData['user_id'] ?? $customer->id, 
                'property_id' => $appointmentData['property_id'] ?? $property->id, 
                'date' => $appointmentData['date'] ?? now()->addDays(rand(1, 7))->format('Y-m-d'), 
                'start_time' => $appointmentData['start_time'] ?? $schedule['start_time'], 
                'status_id' => $appointmentData['status_id'] ?? 2,
            ]);
        });        

        return $properties;
    }

    /**
     * Get structure of statistics.
     *
     * @return array
     */
    private function getJonStructure()
    {
        return [
            'customers' => [
                'total',
                'verified',
                'unverified',
                'registered_today',
            ], 
            'business_accounts' => [
                'total', 
                'verified', 
                'unverified', 
                'registered_today', 
            ], 
            'properties' => [
                'published', 
                'pending', 
                'expired', 
            ], 
            'appointments' => [
                'completed', 
                'appointments_today', 
                'appointments_this_week', 
                'appointments_this_month', 
            ], 
        ];
    }
}
