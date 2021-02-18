<?php

namespace App\Filters;

class UserFilter extends QueryFilter
{
    /**
     * Add filter users by its role.
     *
     * @param string $roles
     * @return void
     */
    public function roles(string $roles)
    {
        $this->builder->whereHas('roles', function ($query) use ($roles) {
            $terms = explode(',', $roles);

            if (in_array('all', $terms)) {
                return $query;
            }

            $query->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('roles.name', 'like', "%$term%");
                }
            });

            $query->where('roles.id', '!=', 1);
        });
    }

    /**
     * Add filter to users by its first_name.
     *
     * @param string $key
     * @return void
     */
    public function firstName(string $key)
    {
        $this->builder->where('first_name', 'like', "%$key%");
    }

    /**
     * Add filter to users by its last_name.
     *
     * @param string $key
     * @return void
     */
    public function lastName(string $key)
    {
        $this->builder->orWhere('last_name', 'like', "%$key%");
    }

    /**
     * Add filter to users by its mobile.
     *
     * @param string $mobile
     * @return void
     */
    public function mobile(string $mobile)
    {
        $this->builder->orWhere('mobile', 'like', "%$mobile%");
    }

    /**
     * Add filter to users by its email.
     *
     * @param string $email
     * @return void
     */
    public function email(string $email)
    {
        $this->builder->orWhere('email', 'like', "%$email%");
    }
}