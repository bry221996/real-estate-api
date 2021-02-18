<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\DefaultCollection;
use App\Property;

class PropertyHistoryController extends Controller
{
    /**
     * Get all property change histories.
     *
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function getChanges(Property $property)
    {
        $histories = $property->histories()->latest('id')->get();

        $changes = collect();

        if ($histories->count() <= 1) {
            return new DefaultCollection($changes);
        }

        for ($i = 0; $i < $histories->count() - 1; $i++) { 
            // convert to list func
            $currentHistory = $histories->get($i);
            $previousHistory = $histories->get($i + 1);

            $change = collect($currentHistory->getChangedDetails($previousHistory));

            if (! $change->has('updated_at')) {
                $change->prepend([
                    'from' => $currentHistory->updated_at->toDateTimeString(), 
                    'to' => $currentHistory->updated_at->toDateTimeString(), 
                ], 'updated_at'); 
            }

            $changes->push($change);
        }

        return new DefaultCollection($changes);
    }
}
