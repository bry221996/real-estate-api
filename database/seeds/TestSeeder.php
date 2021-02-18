<?php

use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->createFurnishedTypes();
        $this->createPropertyTypes();
        $this->createPropertyOfferTypes();
        $this->createFeatures();
        $this->createDevelopers();
    }

    private function createFurnishedTypes()
    {
        App\FurnishedType::insert([
            [ 'name' => 'Bare' ],
            [ 'name' => 'Unfurnished' ],
            [ 'name' => 'Semi-Furnished' ],
            [ 'name' => 'Fully Furnished' ],
        ]);
    }

    private function createPropertyTypes()
    {
        App\PropertyType::insert([
            [ 'name' => 'Condominium' ],
            [ 'name' => 'Office' ],
            [ 'name' => 'House and Lot' ],
        ]);
    }

    private function createPropertyOfferTypes()
    {
        App\PropertyOfferType::insert([
            [ 'name' => 'For Sale' ],
            [ 'name' => 'For Rent' ],
        ]);
    }

    private function createFeatures()
    {
        App\Feature::insert([
            [ 'name' => 'WiFi' ],
            [ 'name' => 'Airconditioning' ],
            [ 'name' => 'Television' ],
            [ 'name' => 'Balconies' ],
            [ 'name' => 'Computers' ],
            [ 'name' => 'Laundry Service' ],
            [ 'name' => 'Swimming Pool' ],
            [ 'name' => 'Playground' ],
            [ 'name' => 'Gymnasium' ],
            [ 'name' => 'Fire Place' ],
            [ 'name' => 'Residential Lounge' ],
        ]);
    }

    private function createDevelopers()
    {
        App\Developer::insert([
            [ 'name' => 'AyalaLand' ],
            [ 'name' => 'SM Prime Holdings' ],
            [ 'name' => 'Filinvest Land' ],
            [ 'name' => 'Rockwel Land' ],
            [ 'name' => 'Robinsons Land Corp.' ],
            [ 'name' => 'Shang Properties' ],
            [ 'name' => 'DMCI Homes' ],
            [ 'name' => 'Megaworld Corporation' ],
            [ 'name' => 'Federal Land' ],
            [ 'name' => 'Century Properites' ],
        ]);
    }
}
