<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use App\Property;
use \League\Geotools\Geotools;
use \League\Geotools\Coordinate\Coordinate;

class PropertiesSortingTest extends TestCase
{
    use RefreshDatabase, TestHelperTrait;

    public function setUp()
    {
        parent::setUp();
    
        \Artisan::call('db:seed', [
            '--class' => 'DefaultsSeeder'
        ]);
        
        \Artisan::call('db:seed', [
            '--class' => 'TestSeeder'
        ]);

        $this->beforeApplicationDestroyed(function () {
            $this->resetDatabaseTablesIncrements();
        });
    }

    /**
     * Check sorting of properties by offer type in ascending order.
     */
    public function testSortPropertiesByOfferTypeAsc()
    {
        $this->checkPropertiesSortingBy('offer_type');
    }

    /**
     * Check sorting of properties by offer type in descending order.
     */
    public function testSortPropertiesByOfferTypeDesc()
    {
        $this->checkPropertiesSortingBy('offer_type', 'desc');
    }

    /**
     * Check sorting of properties by price in ascending order.
     */
    public function testSortPropertiesByPriceAsc()
    {
        $this->checkPropertiesSortingBy('price');
    }

    /**
     * Check sorting of properties by price in descending order.
     */
    public function testSortPropertiesByPriceDesc()
    {
        $this->checkPropertiesSortingBy('price', 'desc');
    }

    /**
     * Check sorting of properties by price in ascending order.
     */
    public function testSortPropertiesByDeveloperAsc()
    {
        $this->checkPropertiesSortingBy('developer');
    }

    /**
     * Check sorting of properties by price in decending order.
     */
    public function testSortPropertiesByDeveloperDesc()
    {
        $this->checkPropertiesSortingBy('developer', 'desc');
    }

    /**
     * Check sorting of properties by price in ascending order.
     */
    public function testSortPropertiesByCreatedAtAsc()
    {
        $this->checkPropertiesSortingBy('created_at');
    }

    /**
     * Check sorting of properties by price in decending order.
     */
    public function testSortPropertiesByCreatedAtDesc()
    {
        $this->checkPropertiesSortingBy('created_at', 'desc');
    }

    /**
     * Test sorting of properties by distance.
     * get the nearest.
     */
    public function testSortPropertiesByDistanceAsc()
    {
        $properties = $this->createProperties();

        $currentCoordinate = collect([
            'latitude' => 14.556401, 
            'longitude' => 121.050268, 
        ]);

        $coordinates = collect([
                [ 'latitude' => 14.554760, 'longitude' => 121.050664 ],
                [ 'latitude' => 14.553473, 'longitude' => 121.046588 ],
                [ 'latitude' => 14.551604, 'longitude' => 121.048175 ],
                [ 'latitude' => 14.550087, 'longitude' => 121.045600 ],
                [ 'latitude' => 14.546702, 'longitude' => 121.045815 ],
            ])
            ->shuffle();

        // modify data
        $properties->each(function ($property) use ($coordinates, $currentCoordinate) {
            $coordinate = $coordinates->pop();

            $property->update([
                'latitude' => $coordinate['latitude'],
                'longitude' => $coordinate['longitude'],
            ]);

            $property->syncChanges();

            $geotools = new Geotools();
            $pointA = new Coordinate(array_values($coordinate));
            $pointB = new Coordinate($currentCoordinate->values()->all());

            $property->distance = $geotools->distance()->setFrom($pointA)->setTo($pointB)->flat();
        });

        $sortedProperties = $properties->sortBy('distance')->pluck('id')->all();

        $response = $this->json(
            'GET', 
            '/api/v1/properties'
            . '?sort=distance' 
            . '&latitude=' . $currentCoordinate->get('latitude') 
            . '&longitude=' . $currentCoordinate->get('longitude'), 
            []
        );

        $responseDataSortKeys = collect($response->json()['data'])
            ->pluck('id')
            ->all();

        $this->assertEquals($sortedProperties, $responseDataSortKeys);
    }

    /**
     * Check sorting of properties by price in ascending order.
     */
    public function testSortPropertiesByExpiredAtAsc()
    {
        $this->checkPropertiesSortingBy('expired_at');
    }

    /**
     * Check sorting of properties by price in descending order.
     */
    public function testSortPropertiesByExpiredAtDesc()
    {
        $this->checkPropertiesSortingBy('expired_at', 'desc');
    }

    /**
     * Check properties sorting by key
     *
     * @param string $sortKey
     * @return void
     */
    private function checkPropertiesSortingBy($sortKey, $order = 'asc')
    {
        $properties = $this->createProperties();

        $coordinates = collect([
                [ 'latitude' => 14.554760, 'longitude' => 121.050664 ],
                [ 'latitude' => 14.553473, 'longitude' => 121.046588 ],
                [ 'latitude' => 14.551604, 'longitude' => 121.048175 ],
                [ 'latitude' => 14.550087, 'longitude' => 121.045600 ],
                [ 'latitude' => 14.546702, 'longitude' => 121.045815 ],
            ])
            ->shuffle();

        // modify data
        $properties->each(function ($property) use ($coordinates) {
            $coordinate = $coordinates->pop();

            $property->append('offer_type');

            $date = now()->subDays(mt_rand(5, 15))->toDateTimeString();
            
            $property->update([
                'created_at' => $date,
                'latitude' => $coordinate['latitude'],
                'longitude' => $coordinate['longitude'],
            ]);

            $property->syncChanges();
        });

        $properties = collect($properties->toArray());

        if ($order == 'asc') {
            $sortedProperties = $properties
                ->sortBy($sortKey);
        } else {
            $sortedProperties = $properties
                ->sortByDesc($sortKey);
        }

        $sortedProperties = $sortedProperties
            ->pluck($sortKey)
            ->all();

        $sortTerm = ($order == 'asc' ? '' : '-') . $sortKey;

        $response = $this->json('GET', "/api/v1/properties?sort={$sortTerm}", []);

        $responseDataSortKeys = collect($response->json()['data'])
            ->pluck($sortKey)
            ->all();

        $this->assertEquals($sortedProperties, $responseDataSortKeys);
    }
}
