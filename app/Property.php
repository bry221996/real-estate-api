<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use App\Filters\FilterableTrait;

class Property extends Model
{
    use FilterableTrait;

    protected $guarded = [];

    protected $with = [
        'features',
        'photos'
    ];

    protected $appends = [ 'display_address' ];

    /**
     * Number of days before the property is expired after verifying/publishing.
     *
     * @var int
     */
    public static $expiredAfterDays = 15;

    /**
     * Get all related photos.
     */
    public function photos()
    {
        return $this->hasMany(PropertyPhoto::class, 'property_id');
    }

    /**
     * Get all related attachments.
     */
    public function attachments()
    {
        return $this->hasMany(PropertyAttachment::class, 'property_id');
    }

    /**
     * Get interest users to this property.
     */
    public function interestedUsers()
    {
        return $this->belongsToMany(User::class, 'interested_user', 'property_id', 'user_id');
    }

    /**
     * Check if the current auth user is intered in this property.
     *
     * @return bool
     */
    public function getIsInterestedAttribute()
    {
        if (auth()->check()) {
            $this->loadMissing('interestedUsers')->makeHidden('interestedUsers');

            return $this->interestedUsers->where('id', auth()->id())->count() > 0;
        }

        return false;
    }

    /**
     * Get property status.
     */
    public function propertyStatus()
    {
        return $this->belongsTo(PropertyStatus::class);
    }

    /**
     * Get property status.
     *
     * @return string
     */
    public function getPropertyStatusAttribute()
    {
        $this->loadMissing('propertyStatus')->makeHidden('propertyStatus');

        return strtolower($this->relations['propertyStatus']->name);
    }

    /**
     * Get property type.
     */
    public function propertyType()
    {
        return $this->belongsTo(PropertyType::class);
    }

    /**
     * Get property type.
     *
     * @return string
     */
    public function getPropertyTypeAttribute()
    {
        $this->loadMissing('propertyType')->makeHidden('propertyType');

        return strtolower($this->relations['propertyType']->name);
    }

    /**
     * Get related features.
     */
    public function features()
    {
        return $this->belongsToMany(Feature::class, 'property_feature', 'property_id', 'feature_id');
    }

    /**
     * Get author(business account) week schedule.
     */
    public function schedule()
    {
        return $this->hasOne(BusinessAccountSchedule::class, 'user_id', 'created_by');
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
        $includes = collect([
                'attachments',
                'agent',
                'schedule',
            ])
            ->intersect($relationships)
            ->each(function ($key) { return camel_case($key); })
            ->toArray();

        if (empty($includes)) {
            return $query;
        }

        $this->loadMissing($includes);
        
        $query->with($includes);

        $this->makeVisible($includes);

        return $query;
    }

    /**
     * Get who created the  property.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get who created the  property.
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get furnished type.
     */
    public function furnishedType()
    {
        return $this->belongsTo(FurnishedType::class);
    }

    /**
     * Get offer type.
     */
    public function offerType()
    {
        return $this->belongsTo(PropertyOfferType::class);
    }

    /**
     * Get furnished type.
     *
     * @return string
     */
    public function getFurnishedTypeAttribute()
    {
        $this->loadMissing('furnishedType')->makeHidden('furnishedType');

        return strtolower($this->relations['furnishedType']->name);
    }

    /**
     * Get offer type.
     *
     * @return string
     */
    public function getOfferTypeAttribute()
    {
        $this->loadMissing('offerType')->makeHidden('offerType');

        return strtolower($this->relations['offerType']->name);
    }

    /**
     * Property history.
     */
    public function histories()
    {
        return $this->hasMany(PropertyHistory::class, 'property_id');
    }

    /**
     * Scope query to get not epired property.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotExpired($query)
    {
        return $query->where('properties.expired_at', '>', now()->toDateTimeString());
    }

    /**
     * Scope query to get all property status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAllPropertyStatus($query)
    {
        return $query->whereIn('properties.property_status_id', [ 1, 2, 3, 4 ]);
    }

    /**
     * Get property by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePropertyStatus($query, $status = null)
    {
        if ($status == 'all') {
            return $query;
        }

        if ($status == 'published') {
            return $query->where('properties.property_status_id', 1)
                ->where('properties.expired_at', '>', now()->toDateTimeString());
        }

        if ($status == 'pending') {
            return $query->where('properties.property_status_id', 2);
        }

        if ($status == 'rejected') {
            return $query->where('properties.property_status_id', 3);
        }

        if ($status == 'closed') {
            return $query->where('properties.property_status_id', 4);
        }

        if ($status == 'expired') {
            return $query->where('properties.expired_at', '<', now()->toDateTimeString());
        }

        return $query;
    }

    /**
     * Scope query to order by offer type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $order
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByOfferType($query, $order = 'asc')
    {
        return $query->leftJoin('property_offer_types', 'properties.offer_type_id', '=', 'property_offer_types.id')
            ->orderBy('property_offer_types.name', $order);
    }

    /**
     * Save current property details to history.
     */
    public function saveToHistory()
    {
        // update timestamp
        $this->touch();

        $this->histories()->create([
            'details' => $this->fresh()->loadMissing('attachments')->toArray(), 
            'property_status_id' => $this->property_status_id,
        ]);
    }

    /**
     * Get Hits Count.
     */
    public function getHitsAttribute()
    {
        return Redis::scard($this->listing_id . '_hits');
    }

    /**
     * Property Appointments.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Property Appointment Count.
     */
    public function getAppointmentCountAttribute()
    {
        return $this->appointments->count();
    }

    /**
     * Property Fulffiled Appointment Count.
     */
    public function getFulfilledAppointmentCountAttribute()
    {
        return $this->appointments->where('status_id', 6)->count();
    }
    
    /**
     * Copy of formatted_address without the unit number.
     *
     * @param string $value
     * @return string
     */
    public function getDisplayAddressAttribute()
    {
        return str_replace_first("{$this->unit} ", '', $this->formatted_address);
    }

    /**
     * Scope query for published properties.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('property_status_id', 1)
            ->where('expired_at', '>', now()->toDateTimeString());
    }

    /**
     * Scope query for pending properties.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('property_status_id', 2);
    }

    /**
     * Scope query for rejected properties.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRejected($query)
    {
        return $query->where('property_status_id', 3);
    }

    /**
     * Scope query for closed/sold properties.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeClosed($query)
    {
        return $query->where('property_status_id', 4);
    }

    /**
     * Scope query for expired properties.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('property_status_id', 1)
            ->where('expired_at', '<', now()->toDateTimeString());  
    }

    /**
     * Get the current authenticated user latest booking to this property.
     *
     * @return \App\Appoinment
     */
    public function getCurrentBookingAttribute()
    {
        return $this->appointments()
            ->latest()
            ->where('user_id', auth()->id())
            ->first();
    }
}
