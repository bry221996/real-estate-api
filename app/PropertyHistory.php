<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyHistory extends Model
{
    protected $guarded = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Get what is changed in property details after update.
     *
     * @return array
     */
    public function getChangedDetails(self $previousHistory)
    {
        $previousDetails = collect($previousHistory->details);

        $difference = $previousDetails->map(function ($previousDetail, $key) {
            $currentDetail = $this->details[$key]; 
            
            if ($previousDetail != $currentDetail) {
                return [
                    'from' => $previousDetail, 
                    'to' => $currentDetail, 
                ];
            }

            if (is_array($previousDetail)) {
                $previousDetail = collect($previousDetail);
                $currentDetail = collect($currentDetail);

                $mergedIds = $previousDetail->pluck('id')->union($currentDetail->pluck('id'));

                if ($previousDetail->pluck('id')->toArray() != $mergedIds->toArray()) {
                    return [
                        'from' => $previousDetail->toArray(), 
                        'to' => $currentDetail->toArray(), 
                    ];
                }
            }
        });

        return $difference->filter()->toArray();
    }
}
