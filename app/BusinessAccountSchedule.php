<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BusinessAccountSchedule extends Model
{
    protected $guarded = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'setup' => 'array',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
