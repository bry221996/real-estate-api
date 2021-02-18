<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Property;
use App\PropertyHistory;
use Carbon\Carbon;

class PropertyStatusController extends Controller
{
    /**
     * Verify pending property.
     *
     * @param \App\Property $property
     * @return \Illuminate\Httpe\Response
     */
    public function verify(Property $property)
    {
       validator($property->toArray(), [
                'property_status_id' => 'in:2', 
            ], [
                'in' => 'The property status should be pending.', 
            ])
            ->validate();

        $property->update([
            'property_status_id' => 1, 
            'expired_at' => now()->endOfDay()->addDays(Property::$expiredAfterDays)->toDateTimeString(),
        ]);

        $property->update([
            'verified_at' => now()->toDateTimeString(),
            'verified_by' => auth()->id(), 
        ]);

        $property->histories()->create([
            'details' => $property->fresh()->loadMissing('attachments')->toArray(), 
            'property_status_id' => 1, 
        ]);

        return response([
            'message' => 'Property Verified.', 
        ]);
    }

    /**
     * Reject pending property.
     *
     * @param \Illuminate\Httpe\Request $request
     * @param \App\Property $property
     * @return \Illuminate\Httpe\Response
     */
    public function reject(Request $request, Property $property)
    {
        validator($property->toArray(), [
                'property_status_id' => 'in:2', 
            ], [
                'in' => 'The property status should be pending.', 
            ])
            ->validate();

        $data = $request->validate([
            'comment' => 'required|min:5', 
        ]);

        $property->update([
            'property_status_id' => 3, 
        ]);

        $property->update([
            'comment' => $data['comment'], 
        ]);

        $property->histories()->create([
            'details' => $property->fresh()->loadMissing('attachments')->toArray(), 
            'property_status_id' => 3, 
        ]);

        return response([
            'message' => 'Property Rejected.', 
        ]);
    }

    /**
     * Sold the published property.
     *
     * @param \App\Property $property
     * @return \Illuminate\Httpe\Response
     */
    public function soldOrOccupied(Property $property)
    {
        validator($property->toArray(), [
                    'property_status_id' => 'in:1', 
                ], [
                    'in' => 'The property status should be verified.', 
                ])
                ->validate();

            $property->update([
                'property_status_id' => 4, 
            ]);

        $property->histories()->create([
            'details' => $property->fresh()->loadMissing('attachments')->toArray(), 
            'property_status_id' => 4, 
        ]);

        return response([
            'message' => 'Property Sold.', 
        ]);
    }

    /**
     * Set property status to pending.
     *
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function unpublish(Property $property)
    {
        validator($property->toArray(), [
                'property_status_id' => 'in:1', 
            ], [
                'in' => 'The property status should be verified.', 
            ])
            ->validate();

        $property->update([
            'property_status_id' => 2, 
        ]);

        $property->histories()->create([
            'details' => $property->fresh()->loadMissing('attachments')->toArray(), 
            'property_status_id' => 2, 
        ]);

        return response([
            'message' => 'Property set to pending.', 
        ]);
    }

    /**
     * Renew expiration of expired published property.
     *
     * @param \App\Property $property
     * @return \Illuminate\Httpe\Response
     */
    public function republish(Property $property)
    {
       validator($property->toArray(), [
                'property_status_id' => 'in:1', 
                'expired_at' => 'before_or_equal:now', 
            ], [
                'in' => 'The property status should be verified.', 
                'expired_at' => 'The property should be expired.', 
            ])
            ->validate();

        $property->update([
            'expired_at' => now()->addDays(Property::$expiredAfterDays)->toDateTimeString(),
        ]);

        $property->histories()->create([
            'details' => $property->fresh()->loadMissing('attachments')->toArray(), 
            'property_status_id' => 1, 
        ]);

        return response([
            'message' => 'Property expiration updated.', 
        ]);
    }

    /**
     * Extend Property Expiration Date.
     * @param \App\Property $property
     * @return \Illuminate\Httpe\Response
     */
    public function extend(Property $property)
    {
        validator($property->toArray(), [
                'property_status_id' => 'in:1', 
                'expired_at' => 'after:now', 
            ], [
                'in' => 'The property status should be verified.',
                'expired_at.after' => 'The property should not be expired.', 
            ])
            ->validate();

        $newExpirationDate = Carbon::parse($property->expired_at)
            ->addDays(Property::$expiredAfterDays);

        $property->update([
            'expired_at' => $newExpirationDate->toDateTimeString(),
        ]);

        return response([
            'message' => 'Property expiration updated.', 
        ]);
    }
}
