<?php

use Faker\Generator as Faker;

$factory->define(App\PropertyAttachment::class, function (Faker $faker) {
    return [
        'property_id' => function () {
            return factory(App\Property::class)->create()->id;
        }, 
        'link' => 'http://www.pdf995.com/samples/pdf.pdf', 
    ];
});
