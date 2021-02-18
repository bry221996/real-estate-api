<?php

use Faker\Generator as Faker;

$factory->define(App\PropertyPhoto::class, function (Faker $faker) {
    return [
        'property_id' => function () {
            return factory(App\Property::class)->create()->id;
        }, 
        'link' => 'https://placeimg.com/640/480/any', 
    ];
});
