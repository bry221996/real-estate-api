<?php

use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(App\User::class, function (Faker $faker) {
    $faker->addProvider(new \Faker\Provider\en_PH\Address($faker));

    return [
        'first_name' => $faker->firstName,
        'last_name' => $faker->lastName,
        'gender' => $faker->randomElement(['male', 'female']),
        'location' => $faker->address,
        'mobile' => $faker->numerify('639#########'),
        'email' => $faker->unique()->email,
        'username' => $faker->unique()->userName,
        'password' => '$2y$10$vtRYCNDVIAWx//tvTH/AFeHCV99O3XWtG8tiy3wWQ6EA7yT76FpZu', //testuser
      ];
});
