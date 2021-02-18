<?php

use Illuminate\Database\Seeder;

class PropertiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Feature::class, 30)->create();

        $businessAccount = factory(App\User::class)->create();       
        $businessAccount->roles()->attach(4, [
            'verified' => 1,
            'verified_at' => now()->toDateTimeString(),
        ]);

        $properties = factory(App\Property::class, 50)->create([
            'property_status_id' => 1,
            'created_by' => $businessAccount->id,
        ]);

        $properties->each(function ($property) {
            $randomFeatures = App\Feature::get()->random(5)->pluck('id');

            $property->save(factory(App\Property::class)->make());
            $property->features()->attach($randomFeatures);
        });
    }
}
