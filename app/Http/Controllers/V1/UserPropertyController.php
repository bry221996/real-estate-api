<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\DefaultCollection;
use App\User;
use App\Filters\PropertyFilter;

class UserPropertyController extends Controller
{
    /**
     * List all resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Filters\PropertyFilter $filters
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, PropertyFilter $filters)
    {
        // set status for scope query
        $propertyStatus = 'published';

        $user = auth()->user();

        $propertiesQuery = $user->properties()->with([
            'interestedUsers',
            'propertyType',
            'propertyStatus',
            'furnishedType',
            'offerType',
            'appointments',
        ]);

        if ($request->filled('include')) {
            $propertiesQuery->includes(explode(',', $request->include));
        }

        $propertiesQuery->filter($filters);

        if ($request->filled('scope')) {
            $scopes = explode(',', $request->scope);

            if (in_array('all_property_status', $scopes)) {
                $propertyStatus = 'all';
            }
        }

        if ($request->filled('sort')) {
            $sortKeys = explode(',', $request->sort);

            if (in_array('offer_type', $sortKeys)) {
                $propertiesQuery->orderByOfferType();
            }

            if (in_array('-offer_type', $sortKeys)) {
                $propertiesQuery->orderByOfferType('desc');
            }

            if (in_array('price', $sortKeys)) {
                $propertiesQuery->orderBy('price');
            }

            if (in_array('-price', $sortKeys)) {
                $propertiesQuery->orderBy('price', 'desc');
            }

            if (in_array('developer', $sortKeys)) {
                $propertiesQuery->orderBy('developer');
            }

            if (in_array('-developer', $sortKeys)) {
                $propertiesQuery->orderBy('developer', 'desc');
            }

            if (in_array('created_at', $sortKeys)) {
                $propertiesQuery->orderBy('created_at');
            }

            if (in_array('-created_at', $sortKeys)) {
                $propertiesQuery->orderBy('created_at', 'desc');
            }

            if (in_array('expired_at', $sortKeys)) {
                $propertiesQuery->orderBy('expired_at');
            }

            if (in_array('-expired_at', $sortKeys)) {
                $propertiesQuery->orderBy('expired_at', 'desc');
            }

            if (in_array('distance', $sortKeys) && $request->has(['latitude', 'longitude'])) {
                $propertiesQuery->addSelect(
                        \DB::raw('ST_Distance(POINT(latitude,longitude),POINT(?,?)) as distance')
                    )
                    ->addBinding([$request->latitude, $request->longitude], 'select');

                $propertiesQuery->orderBy('distance');
            }
        }

        $properties = $propertiesQuery
            ->when(! array_has($request->filter, 'property_status'), function ($query) use ($propertyStatus) {
                $query->propertyStatus($propertyStatus);
            })
            ->latest('properties.created_at')
            ->paginate($request->per_page ?? 10);

        // transform collections
        foreach ($properties->items() as $property) {
            $property->append([
                'is_interested',
                'property_type',
                'property_status',
                'furnished_type',
                'offer_type',
                'hits',
                'appointment_count',
                'fulfilled_appointment_count',
            ]);

            $property->makeHidden([
                'verified_at',
                'interestedUsers',
            ]);
        }

        return new DefaultCollection($properties);
    }
}