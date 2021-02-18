<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyPhoto extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    
    protected $hidden = [ 'deleted_at' ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Update parent timestamp.
     *
     * @var array
     */
    protected $touches = [ 'property' ];

    /**
     * Property where the photo belongs to.
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
