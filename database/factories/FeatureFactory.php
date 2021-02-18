<?php

use Faker\Generator as Faker;

$factory->define(App\Feature::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->sentence(3), 
    ];
});
