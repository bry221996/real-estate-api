<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Property;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\DefaultCollection;
use Storage;
use Validator;
use App\Filters\PropertyFilter;

class PropertyController extends Controller
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

        $propertiesQuery = Property::select('*')->with([
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

        if (
            $request->filled('scope') 
            && auth()->check() 
            && auth()->user()->roles->pluck('name')->diff([ 'customer' ])->isNotEmpty()
        ) {
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
            $property = $this->transformPropertyResource($property);

            $property->makeHidden([
                'verified_at',
                'interestedUsers',
            ]);
        }

        return new DefaultCollection($properties);
    }

    /**
     * Create new resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'description' => 'sometimes',
            'lot_size' => 'required_if:property_type_id,3|numeric',
            'floor_size' => 'required_if:property_type_id,1,2|numeric',
            'bathroom_count' => 'required|numeric',
            'bedroom_count' => 'required|numeric',
            'garage_count' => 'required|numeric',
            'address' => 'required',
            'formatted_address' => 'required',
            'unit' => 'sometimes',
            'building_name' => 'sometimes',
            'street' => 'required',
            'barangay' => 'sometimes',
            'city' => 'required',
            'zip_code' => 'sometimes',
            'latitude' => 'sometimes',
            'longitude' => 'sometimes',
            'developer' => 'required',
            'furnished_type_id' => 'required|exists:furnished_types,id',
            'offer_type_id' => 'required|exists:property_offer_types,id',
            'property_type_id' => 'required|exists:property_types,id',
            'price' => 'required|numeric',
            'price_per_sqm' => 'sometimes|numeric',
            'occupied' => 'required|boolean',
            'features' => 'sometimes|array|exists:features,id', 
        ]);

        $listingId = $this->generateListingId($data);

        $propertyData = array_diff_key($data, ['features' => '']);

        $propertyData += [
            'created_by' => auth()->id(),
            'listing_id' => $listingId,
            'property_status_id' => 2,
        ];

        $property = Property::create($propertyData);

        if ($request->filled('features')) {
            $property->features()->attach($data['features']);
        }

        return response([
            'message' => 'Property submitted for verification', 
            'meta' => [
                'property' => $property,
            ], 
        ]);
    }

    /**
     * Show resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Property $property)
    {
        $property->load([
            'interestedUsers',
            'propertyType',
            'propertyStatus',
            'furnishedType',
            'offerType',
        ]);

        if ($request->filled('include')) {
            $includes = explode(',', $request->include);

            $property->includes($includes);

            if (in_array('current_booking', $includes)) {
                $property->append('current_booking');
            }
        }

        $property = $this->transformPropertyResource($property);
        
        $property->makeHidden([
            'verified_at',
            'interestedUsers',
        ]);

        return new DefaultResource($property);
    }

    /**
     * Update resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Property $property)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'description' => 'sometimes',
            'bathroom_count' => 'required|numeric',
            'bedroom_count' => 'required|numeric',
            'garage_count' => 'required|numeric',
            'address' => 'required',
            'formatted_address' => 'required',
            'unit' => 'sometimes',
            'building_name' => 'sometimes',
            'street' => 'required',
            'barangay' => 'sometimes',
            'city' => 'required',
            'zip_code' => 'sometimes',
            'latitude' => 'sometimes',
            'longitude' => 'sometimes',
            'developer' => 'required',
            'furnished_type_id' => 'required|exists:furnished_types,id',
            'offer_type_id' => 'required|exists:property_offer_types,id',
            'price' => 'required|numeric',
            'price_per_sqm' => 'sometimes|numeric',
            'occupied' => 'required|boolean',
            'features' => 'sometimes|array|exists:features,id', 
        ]);

        $validator->sometimes('lot_size', 'required|numeric', function ($input) use ($property) {
            return $property->property_type_id == 3;
        });

        $validator->sometimes('floor_size', 'required|numeric', function ($input) use ($property) {
            return $property->property_type_id == 1 || $property->property_type_id == 2;
        });

        $validator->validate();

        $data = array_intersect_key($validator->valid(), $validator->getRules());

        $propertyUpdateData = array_diff_key($data, ['features' => '']);

        $propertyUpdateData['property_status_id'] = 2;

        $property->update($propertyUpdateData);

        if ($request->filled('features')) {
            $property->features()->sync($data['features']);

            // refresh relation
            $property->load('features');
        }

        $property->saveToHistory();

        return response([
            'message' => 'Property updated for verification', 
            'meta' => [
                'property' => $property,
            ], 
        ]);
    }

    /**
     * Upload photo of related property.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function updatePropertyPhotos(Request $request, Property $property)
    {
        $propertyPhotosCount = $property->photos->count();

        $data = $request->validate([
            'photos' => 'required|array|min:' . ($propertyPhotosCount >= 5 ? 1 : 5 - $propertyPhotosCount),
            'photos.*' => 'required|image|mimes:jpg,png,jpeg,png|max:10000', 
        ]);

        $toSaveData = [];

        foreach ($data['photos'] as $photo) {
            $path = $photo->store('/images/properties');

            $toSaveData[] = [
                'link' => Storage::url($path), 
            ];
        }

        // set property to pending
        $property->update([ 'property_status_id' => 2 ]); 

        $property->photos()->createMany($toSaveData);

        $property->saveToHistory();

        return response([
            'message' => 'Upload successful', 
        ]);
    }

    /**
     * Destroy resource related photos.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Property $property
     * @return \Illuminate\Http\Response 
     */
    public function removePropertyPhotos(Request $request, Property $property)
    {
        $data = $request->validate([
            'ids' => 'array|required',
            'ids.*' => 'exists:property_photos,id|numeric'
        ]);

        if ($property->photos()->whereIn('id', $data['ids'])->delete() == 0) {
            return response([
                'message' => 'No photos deleted.', 
            ]);
        }

        // set property to pending
        $property->update([ 'property_status_id' => 2 ]); 

        // refresh relation
        $property->load('photos');

        $property->saveToHistory();
        
        return response([
            'message' => 'Photos deleted.', 
        ]);
    }

    /**
     * Upload file attachments related to property.
     *
     * @param \App\Property $property
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response 
     */
    public function updatePropertyAttachments(Property $property, Request $request)
    {
        $data = $request->validate([
            'attachments' => 'required|array|min:1',
            'attachment.*' => 'required|file|max:2048', 
        ]);

        $attachmentsLink = [];

        foreach ($data['attachments'] as $attachment) {
            $path = $attachment->store('/images/attachments');

            $attachmentsLink[] = [
                'link' => Storage::url($path), 
            ];
        }

        // set property to pending
        $property->update([ 'property_status_id' => 2 ]);

        $property->attachments()->createMany($attachmentsLink);

        $property->saveToHistory();

        return response([
            'message' => 'Upload successful', 
        ]);
    }

    /**
     * Delete resource file attachments.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Property $property
     * @return \Illuminate\Http\Response 
     */
    public function removePropertyAttachments(Request $request, Property $property)
    {
        $data = $request->validate([
            'ids' => 'array|required',
            'ids.*' => 'exists:property_attachments,id|numeric'
        ]);

        if ($property->attachments()->whereIn('id', $data['ids'])->delete() == 0) {
            return response([
                'message' => 'No attachments deleted.', 
            ]);
        }

        // set property to pending
        $property->update([ 'property_status_id' => 2 ]); 

        // refresh relation
        $property->load('attachments');

        $property->saveToHistory();

        return response([
            'message' => 'Attachments deleted.', 
        ]);
    }

    /**
     * Transform property key and value.
     *
     * @param \App\Property $property
     * @return \App\Property
     */
    private function transformPropertyResource(Property $property)
    {
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

        return $property;
    }

    /**
     * Generate a unique listing_id.
     *
     * @param array $data
     * @return string
     */
    private function generateListingId($data = [])
    {
        $type = '';
        $date = now()->format('ymd');
        $businessAccountId = str_pad(auth()->id(), 5, '0', STR_PAD_LEFT);
        $id = str_pad((int) Property::max('id') + 1, 3, '0', STR_PAD_LEFT);

        if ($data['property_type_id'] == 1) {
            $type = 'C';
        }

        if ($data['property_type_id'] == 2) {
            $type = 'O';
        }

        if ($data['property_type_id'] == 3) {
            $type = 'L';
        }

        return $type . $date . '_' . $businessAccountId . '_' . $id;
    }
}
