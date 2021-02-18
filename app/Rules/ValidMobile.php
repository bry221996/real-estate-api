<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Role;
use App\User;

class ValidMobile implements Rule
{
    /**
     * The role_id of the user wants the mobile to be registered.
     *
     * @var int
     */
    private $role_id;

    /**
     * Valid roles that can be use.
     *
     * @var array
     */
    private $roles = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->role_id = request('role_id');

        $this->roles = Role::whereIn('name', [
                'customer',
                'owner',
                'agent',
            ])
            ->get([ 'id', 'name' ]);
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (! $this->roles->contains('id', $this->role_id)) {
            return false;
        }

        $user = User::where('mobile', $value)->with('roles')->first();

        return $user ? (! $user->roles->contains('id', $this->role_id)) : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Mobile can\'t be use.';
    }
}
