<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Property;
use Auth;

class PropertyHitsController extends Controller
{
    /**
     * Add hit count to property.
     *
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function store(Property $property)
    {
        Redis::sadd($property->listing_id . '_hits', Auth::id());

        return response([
            'message' => 'Property hit\'s saved.'
        ]);
    }
}
