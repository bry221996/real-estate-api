<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AppointmentSort extends TestCase
{
    use RefreshDatabase;

    /**
     * Endpoint to get appointment list.
     *
     * @var string
     */
    public $uri;

    /**
     * Appointments that user belongs.
     *
     * @var array
     */
    public $appointments;

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
     * Test sort appointment by date ascending.
     */
    public function testSortDateAsc()
    {
        $this->sortBy('date', 'asc');
    }

    /**
     * Test sort appointment by date descending.
     */
    public function testSortDateDesc()
    {
        $this->sortBy('date', 'desc');
    }

    /**
     * Test sort appointment by start_time ascending.
     */
    public function testSortStartTimeAsc()
    {
        $this->sortBy('start_time', 'asc');
    }

    /**
     * Test sort appointment by start_time descending.
     */
    public function testSortStartTimeDesc()
    {
        $this->sortBy('start_time', 'desc');
    }

    /**
     * Check sorting order.
     *
     * @param string $field
     * @param string $sortOrder
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    public function sortBy(string $field, string $sortOrder = 'asc')
    {
        $sortedAppointments = $sortOrder == 'asc' 
            ? $this->appointments->sortBy($field)
            : $this->appointments->sortByDesc($field);

        $sortedAppointmentsField = $sortedAppointments
            ->pluck($field)
            ->all();

        $sortTerm = ($sortOrder == 'asc' ? '' : '-') . $field;

        $response = $this->json('GET', "{$this->uri}?sort={$sortTerm}&per_page=99", [])
            ->assertStatus(200);

        $responseDataSortedField = collect(data_get($response->json(), 'data'))
            ->pluck($field)
            ->all();

        $this->assertEquals($sortedAppointmentsField, $responseDataSortedField);
    }
}
