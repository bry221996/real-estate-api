<?php

namespace Tests\Feature\V1\Setup;

use App\Property;
use App\Feature;
use App\PropertyPhoto;
use App\PropertyAttachment;

class PropertyFactory
{
    /**
     * Number of property instance that will be created.
     *
     * @var int
     */
    public $count = 1;

    public function create($data = [])
    {
        $properties = factory(Property::class, $this->count)->create($data);

        $properties->each(function ($property) {
            $randomFeatures = Feature::get()->random(5)->pluck('id');

            $property->features()->attach($randomFeatures);

            $photos = factory(PropertyPhoto::class, 5)
                ->make([ 'property_id' => $property->id ])
                ->toArray();

            $property->photos()->createMany($photos);

            $attachments = factory(PropertyAttachment::class, 3)
                ->make([ 'property_id' => $property->id ])
                ->toArray();

            $property->attachments()->createMany($attachments);

            $property->saveToHistory();
        });

        return $this->count > 1 ? $properties : $properties->first();
    }

    /**
     * Set how many instance will be created.
     *
     * @param int $count
     * @return self
     */
    public function setCount($count)
    {
        $this->count = $count;

        return $this;
    }
}
