<?php

namespace Tests\Feature\V1;

use Tests\Feature\V1\Setup\AppointmentFactory;
use Laravel\Passport\Passport;

class BusinessAccountAppointmentListSortingTest extends AppointmentSort
{
    public function setUp()
    {
        parent::setUp();

        $this->uri = '/api/v1/account/appointments';

        $this->appointments = (new AppointmentFactory())->setCount(10)->create();

        $businessAccount = $this->appointments->first()->property->agent;

        \App\Property::where('created_by', '!=', $businessAccount->id)
            ->update([ 'created_by' => $businessAccount->id ]);

        Passport::actingAs($businessAccount);
    }
}
