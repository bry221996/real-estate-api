<?php

use Faker\Generator as Faker;

$factory->define(App\PropertyHistory::class, function (Faker $faker) {
    return [
        'property_id' => function () {
            return factory(App\Property::class)->create()->id;
        }, 
        'details' => function (array $history) {
            return App\Property::find($history['property_id'])
                ->loadMissing('attachments')
                ->toArray();
        }, 
        'property_status_id' =>function (array $history) {
            return App\Property::find($history['property_id'])->property_status_id;
        }, 
    ];
});
