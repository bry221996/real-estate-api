<?php

namespace Tests\Feature\V1\Setup;

use App\User;
use App\Property;
use App\Appointment;
use App\BusinessAccountSchedule;

class UserFactory
{
    /**
     * Valid roles for the user.
     *
     * @var array
     */
    protected $validRoles = [
        [
            'name' => 'admin', 
            'role_id' => 2,
        ],
        [
            'name' => 'customer', 
            'role_id' => 3,
        ],
        [
            'name' => 'owner', 
            'role_id' => 4,
        ],
        [
            'name' => 'agent', 
            'role_id' => 5,
        ],
    ];

    /**
     * Roles that the user will have.
     *
     * @var array
     */
    private $roles = [];

    /**
     * Number of property instance that will be created.
     *
     * @var int
     */
    private $count = 1;

    /**
     * Booking that will be attach to the user.
     * For customer role only.
     *
     * @var array
     */
    private $bookings = [];

    /**
     * Create the user.
     *
     * @param array $data
     * @return \App\User
     */
    public function create($data = [])
    {
        $users = factory(User::class, $this->count)->create($data);

        $userRoles = collect($this->roles);

        $users->each(function ($user) use ($userRoles) {
            $user->roles()->attach($userRoles->pluck('role_id')->toArray(), [
                'verified' => 1,
                'verified_at' => now()->toDateTimeString(),
            ]);
    
            if ($userRoles->pluck('name')->diff([ 'customer', 'admin' ])->isNotEmpty()) {
                factory(BusinessAccountSchedule::class)->create([ 'user_id' => $user->id ]);
            }

            $user->bookings()->createMany($this->bookings);
        });

        return $this->count > 1 ? $users : $users->first();
    }

    /**
     * Set user roles.
     *
     * @param mixed $roles
     * @return self
     */
    public function setRoles(...$roles)
    {
        $this->roles = collect($this->validRoles)->filter(function ($role) use ($roles) {
            return collect($roles)->contains($role['name']);
        });

        return $this;
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
     * Add bookings to specified property. 
     *
     * @param \App\Property $property
     * @param int $count
     * @param array $data
     * @param array $states
     * @return self
     */
    public function setBookingsToProperty(Property $property, int $count = 1, array $data = [], array $states = [])
    {
        $bookings = factory(Appointment::class, $count)
            ->states($states)
            ->make([ 'property_id' => $property->id ] + $data);

        $bookings->each(function ($booking) {
            array_push($this->bookings, $booking->getAttributes());
        });

        return $this;
    }
}
