<?php

namespace Tests\Feature\V1;

use Tests\Feature\V1\Setup\AppointmentFactory;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Setup\UserFactory;

class CustomerBookingListSortingTest extends AppointmentSort
{
    public function setUp()
    {
        parent::setUp();

        $this->uri = '/api/v1/account/bookings';

        $this->appointments = (new AppointmentFactory())->setCount(10)->create();

        $customer = $this->appointments->first()->customer;

        $this->appointments->each->update([ 'user_id' => $customer->id ]);

        Passport::actingAs($customer);
    }
}
