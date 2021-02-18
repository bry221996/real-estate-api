<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Resources\DefaultCollection;
use App\Http\Controllers\Controller;
use App\Property;

class UserInterestController extends Controller
{
    /**
     * Display list of resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $propertyInterestsQuery = auth()->user()->interests();

        $propertyInterestsQuery->with([
            'interestedUsers',
            'propertyType',
            'propertyStatus',
            'furnishedType',
            'offerType',
        ]);

        if ($request->filled('include')) {
            $propertyInterestsQuery->includes(explode(',', $request->include));
        }

        $interestedProperties = $propertyInterestsQuery->paginate($request->per_page ?? 10);

        // transform collections
        foreach ($interestedProperties->items() as $property) {
            $property->append([
                'is_interested',
                'property_type',
                'property_status',
                'furnished_type',
                'offer_type',
            ]);
    
            $property->makeHidden([
                'verified_at',
                'created_by',
                'interestedUsers',
            ]);
        }

        return new DefaultCollection($interestedProperties);
    }

    /**
     * Add to use interests.
     *
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function addToInterests(Property $property)
    {
        $user = auth()->user();

        $alreadyExists = $user->interests()->where('properties.id', $property->id)->count() >= 1;

        if (! $alreadyExists) {
            $user->interests()->attach($property->id);
        }

        return response([
            'message' => 'Added to interests.', 
        ]);
    }

    /**
     * Add to use Interests.
     *
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function removeFromInterests(Property $property)
    {
        $user = auth()->user();
        $interest = $user->interests()->detach($property->id);

        return response([
            'message' => 'Removed from interests.', 
        ]);
    }
}
