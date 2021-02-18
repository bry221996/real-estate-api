<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use App\Filters\FilterableTrait;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens, FilterableTrait;

    protected $guarded = [];

    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'full_name',
        'customer_profile_complete',
        'business_account_is_verified',
    ];

    /**
     * Override passport username.
     *
     * @param string $username
     * @return \App\User
     */
    public function findForPassport($username)
    {
        return $this->where('mobile', $username)
            ->orWhere('email', $username)
            ->orWhere('username', $username)
            ->first();
    }

    /**
     * Override passport password.
     *
     * @param string $password
     * @return bool
     */
    public function validateForPassportPasswordGrant($password)
    {
        // accessor
        $code = $this->verification_code;

        if ($code !== null) {
            if ((int) $code === (int) $password) {
                $this->forgetUcode();

                return true;
            }
        }

        return \Hash::check($password, $this->password);
    }

    /**
     * Get user roles.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id')->withPivot(['verified']);
    }

    /**
     * Get list of property interests.
     */
    public function Interests()
    {
        return $this->belongsToMany(Property::class, 'interested_user', 'user_id', 'property_id');
    }

    /**
     * Set customer_profile_complete attribute.
     */
    public function getCustomerProfileCompleteAttribute()
    {
        return (! empty($this->first_name)) 
            && (! empty($this->last_name))
            && (! empty($this->email));
    }

    /**
     * Set business_account_profile_complete attribute.
     */
    public function getBusinessAccountProfileCompleteAttribute()
    {
        return $this->getCustomerProfileCompleteAttribute()
            && empty($this->prc_registration_number)
            && empty($this->prc_id_link);
    }

    /**
     * Route notifications for the Slack test channel.
     *
     * @return string
     */
    public function routeNotificationForSlack()
    {
        return env('TEST_SLACK_WEBHOOK_URL');
    }

    /**
     * Attach requested relationships.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $relationship
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIncludes($query, $relationships = [])
    {
        // get valid relationship only
        $includes = array_intersect([
            'roles',
            'schedule',
        ], $relationships);

        if (empty($includes)) {
            return $query;
        }
        
        $this->loadMissing($includes);

        $query->with($includes);

        return $query;
    }

    /**
     * Scope a query to exempt super_admin.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExemptSuperAdmin($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('user_role.role_id', '!=', 1);
        });
    }

    /**
     * Scope a query to exempt admin.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExemptAdmin($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('user_role.role_id', '!=', 2);
        });
    }

    /**
     * Scope a query to get user with admin role.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsAdmin($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('user_role.role_id', 2);
        });
    }

    /**
     * Get full name (first_name + last_name).
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Set business_account_verified attribute.
     *
     * @return bool
     */
    public function getBusinessAccountIsVerifiedAttribute()
    {
        $verifiedBusinessAccounts = $this
            ->roles()
            ->wherePivotIn('role_id', [ 4, 5 ])
            ->wherePivot('verified', 1)
            ->count();

        return $verifiedBusinessAccounts >= 1;
    }

    /**
     * Get if the user has business account.
     *
     * @return bool
     */
    public function getHasBusinessAccountAttribute()
    {
        if (! $this->relationLoaded('roles')) {
            $this->loadMissing('roles')->makeHidden('roles');
        }
        
        return $this->roles->whereIn('id', [ 4, 5 ])->isNotEmpty();
    }

    /**
     * Get all properties created.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'created_by', 'id');
    }

    /**
     * Business account weekly schedules.
     */
    public function schedule()
    {
        return $this->hasOne(BusinessAccountSchedule::class, 'user_id');
    }

    /**
     * Get all appointments from registered properties.
     */
    public function appointments()
    {
        return $this->hasManyThrough(Appointment::class, Property::class, 'created_by', 'property_id');
    }

    /**
     * Customer booked appointments.
     */
    public function bookings()
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    /**
     * Route notifications for the Semaphore channel.
     *
     * @return string
     */
    public function routeNotificationForSemaphore()
    {
        return str_replace_first('63', '0', $this->mobile);
    }

    /**
     * Generate verification code for the use.
     *
     * @return int
     */
    public function getVerificationCodeAttribute()
    {
        return cache()->tags('verification_codes')
            ->remember($this->mobile . '_verification_code', 10, function () {
                return mt_rand(1000, 9999);
            });
    }

    /**
     * Forget the current Ucode of the user.
     *
     * @return void
     */
    public function forgetUcode()
    {
       cache()->tags('verification_codes')->forget($this->mobile . '_code');
    }
}
