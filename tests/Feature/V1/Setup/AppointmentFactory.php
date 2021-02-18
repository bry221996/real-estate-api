<?php

namespace Tests\Feature\V1\Setup;

use App\Appointment;
use Tests\Feature\V1\Setup\PropertyFactory;
use Tests\Feature\V1\Setup\UserFactory;

class AppointmentFactory
{
    /**
     * Number of property instance that will be created.
     *
     * @var int
     */
    public $count = 1;

    /**
     * States to be applied on the factory.
     *
     * @var array
     */
    public $states = [];

    public function create($data = [])
    {
        $data['user_id'] = $data['user_id'] ?? (new UserFactory())->setRoles('customer')->create()->id;
        $data['property_id'] = $data['property_id'] ?? (new PropertyFactory())->create()->id;

        $appointments = factory(Appointment::class, $this->count)->states($this->states)->create($data);

        return $this->count > 1 ? $appointments : $appointments->first();
    }

    /**
     * Set how many instance will be created.
     *
     * @param int $count
     * @return self
     */
    public function setCount($count)
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Set states.
     *
     * @param array $states
     * @return self
     */
    public function setStates(array $states = [])
    {
        $this->states = $states;

        return $this;
    }
}
