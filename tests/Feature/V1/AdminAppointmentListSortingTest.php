<?php

namespace Tests\Feature\V1;

use Tests\Feature\V1\Setup\AppointmentFactory;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Setup\UserFactory;

class AdminAppointmentListSortingTest extends AppointmentSort
{
    public function setUp()
    {
        parent::setUp();

        $this->uri = '/api/v1/appointments';

        $this->appointments = (new AppointmentFactory())->setCount(10)->create();

        Passport::actingAs((new UserFactory())->setRoles('admin')->create());
    }
}
