<?php

use Faker\Generator as Faker;

$factory->define(App\Property::class, function (Faker $faker) {
    return [
        'property_type_id' => function () {
            return \App\PropertyType::pluck('id')->random();
        }, 
        'created_by' => function () {
            $roleId = rand(4, 5);

            $user = factory(App\User::class)->create();       
    
            $user->roles()->attach([ $roleId, 3 ], [
                'verified' => 1,
                'verified_at' => now()->toDateTimeString(),
            ]);
            
            factory(App\BusinessAccountSchedule::class)->create([ 'user_id' => $user->id ]);
        
            return $user->getKey();
        }, 
        'listing_id' => function (array $property) {
            $type = '';
            $date = now()->format('ymd');
            $businessAccountId = str_pad($property['created_by'], 5, '0', STR_PAD_LEFT);
            $id = str_pad((int) App\Property::max('id') + 1, 3, '0', STR_PAD_LEFT);

            if ($property['property_type_id'] == 1) {
                $type = 'C';
            }
    
            if ($property['property_type_id'] == 2) {
                $type = 'O';
            }
    
            if ($property['property_type_id'] == 3) {
                $type = 'L';
            }

            return $type . $date . '_' . $businessAccountId . '_' . $id ?? 'asdsad';
        }, 
        'name' => $faker->sentence(5),
        'description' => $faker->sentence,
        'lot_size' => $faker->numberBetween(50, 200),
        'floor_size' => $faker->numberBetween(50, 200),
        'bathroom_count' => $faker->numberBetween(0, 5),
        'bedroom_count' => $faker->numberBetween(0, 5),
        'garage_count' => $faker->numberBetween(0, 5),
        'address' => $faker->address,
        'formatted_address' => $faker->address,
        'unit' => $faker->buildingNumber,
        'building_name' => $faker->secondaryAddress,
        'street' => $faker->streetName,
        'barangay' => $faker->streetSuffix,
        'city' => $faker->city,
        'zip_code' => $faker->postcode,
        'latitude' => $faker->latitude,
        'longitude' => $faker->longitude,
        'developer' => $faker->company,
        'furnished_type_id' => function () {
            return \App\FurnishedType::pluck('id')->random();
        },
        'offer_type_id' => function () {
            return \App\PropertyOfferType::pluck('id')->random();
        },
        'price' => $faker->randomFloat(2, 10000, 999999),
        'price_per_sqm' => $faker->randomFloat(2, 10000, 999999),
        'occupied' => rand(0, 1),
        'property_status_id' => 1, 
        'property_type_id' => 1, 
        'created_at' => now()->subHour()->toDateTimeString(), 
        'updated_at' => now()->subHour()->toDateTimeString(), 
        'expired_at' => now()->subDays(rand(1, 15))->subHours(rand(1, 12))->addMonth()->toDateTimeString(), 
    ];
});
