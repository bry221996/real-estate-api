<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Laravel\Passport\Passport;

class BusinessAccountAppointmentFilterTest extends TestCase
{
    use RefreshDatabase, TestHelperTrait;

    private $businessAccount;

    // private $properties;

    private $appointments;

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

        $this->businessAccount = $this->createBusinessAccount();
        $this->appointments = collect([]);

        factory(\App\Appointment::class, 10)->create([
            'property_id' => $this->createProperty([ 'created_by' => $this->businessAccount->id ])->id,
            'user_id' => $this->createCustomer()->id, 
        ]);

        Passport::actingAs($this->businessAccount);
    }

    /**
     * Filter list by status id.
     */
    public function testFilterByStatusId()
    {
        collect(range(1, 7))->each(function ($value) {
            $this->checkFilterByField('status_id', $value);
        });
    }

    /**
     * Filter list by status id without expired appointments.
     */
    public function testFilterByStatusIdWithoutExpired()
    {
        $this->checkFilterByField('status_id', implode(',', range(1, 6))); // status_id up to completed
    }

        /**
     * Filter list by status name.
     */
    public function testFilterByStatusName()
    {
        $statuses = [
            'Confirmed',
            'Pending',
            'Rejected',
            'Cancelled',
            'Reschedule',
            'Completed',
            'Expired',
        ];

        collect($statuses)->each(function ($value) {
            $this->checkFilterByField('status', $value);
        });
    }

    /**
     * Filter list by status name without expired.
     */
    public function testFilterByStatusNameWithoutExpired()
    {
        $statuses = [
            'Confirmed',
            'Pending',
            'Rejected',
            'Cancelled',
            'Reschedule',
            'Completed',
        ];

        $this->checkFilterByField('status', implode(',', $statuses));
    }

    /**
     * Test date_word = 'recent' filter.
     * Should only include last 7 days of appointments. 
     */
    public function testRecentFilter()
    {
        factory(\App\Appointment::class, 5)->create([
                'user_id' => auth()->id(), 
                'property_id' => $this->createProperty([ 'created_by' => $this->businessAccount->id ])->id, 
                'status_id' => function () {
                    return rand(1, 7);
                }, 
            ])
            ->each(function ($appointment) {
                $this->appointments->push($appointment);
            });


        // update date appointments to test
        $this->appointments->each(function ($appointment, $key) {
            $appointment->update([ 'date' => now()->subDays($key)->toDateString() ]);
        });

        $response = $this->json('GET', '/api/v1/account/appointments?filter[date_word]=recent', []);

        $response->assertStatus(200);

        $responseData = collect(data_get($response->json(), 'data'));
        
        $this->assertTrue($responseData->where('date', now()->subWeek()->toDateString())->isEmpty());
    }

    /**
     * Test date_word = 'today' filter.
     * Should only include today's appointments. 
     */
    public function testTodayFilter()
    {
        factory(\App\Appointment::class, 5)->create([
                'user_id' => auth()->id(), 
                'property_id' => $this->createProperty([ 'created_by' => $this->businessAccount->id ])->id, 
                'status_id' => function () {
                    return rand(1, 7);
                }, 
            ])
            ->each(function ($appointment) {
                $this->appointments->push($appointment);
            });


        // update date appointments to test
        $this->appointments->each(function ($appointment, $key) {
            $appointment->update([ 'date' => now()->subDays($key)->toDateString() ]);
        });

        $response = $this->json('GET', '/api/v1/account/appointments?filter[date_word]=today', []);

        $response->assertStatus(200);

        $responseData = collect(data_get($response->json(), 'data'));

        $this->assertTrue($responseData->pluck('date')->unique()->first() == now()->toDateString());
    }

    /**
     * Test filter by appointment property address.
     */
    public function testPropertyAddressFilter()
    {
        $property = auth()->user()->properties()->inRandomOrder()->first();

        $searchTerm = str_replace_last('/', '', $searchTerm ?? substr($property['address'], 0, 3));

        $response = $this->json('GET', "/api/v1/account/appointments?filter[address]={$searchTerm}", []);

        $response->assertStatus(200);

        $responseData = collect(data_get($response->json(), 'data'));

        $responseData->each(function ($appointment) use ($searchTerm) {
            $property = \App\Property::find($appointment['property_id']);

            $this->assertTrue(str_contains(strtolower($property['address']), trim(strtolower($searchTerm))));
        });
    }

    /**
     * Filter by given field.
     *
     * @param string $field
     * @param string $searchTerm
     * @return void
     */
    private function checkFilterByField(string $field, string $searchTerm = null)
    {
        $unOwnedProperty = $this->createProperty();

        $response = $this->json('GET', "/api/v1/account/appointments?filter[$field]={$searchTerm}", []);

        $response->assertStatus(200);

        $responseData = collect(data_get($response->json(), 'data'));

        $responseData->each(function ($appointment) use ($field, $searchTerm, $unOwnedProperty) {
            $this->assertTrue(
                str_contains(
                    strtolower($appointment[$field]), 
                    explode(',', trim(strtolower($searchTerm)))
                )
            );
        });

        $this->assertTrue(! $responseData->contains('property_id', $unOwnedProperty->id));
    }
}
