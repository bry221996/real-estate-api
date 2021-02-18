<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Setup\PropertyFactory;
use App\Property;

class PropertyFilter extends TestCase
{
    use RefreshDatabase;

    public $uri;

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
     * Test filtering of properties where offer type is 'rent'.
     */
    public function testFilterOfferTypeIsForRent()
    {
        $this->filterBy('offer_type', 'rent');
    }

    /**
     * Test filtering of properties where offer type is 'sale'.
     */
    public function testFilterOfferTypeIsForSale()
    {
        $this->filterBy('offer_type', 'sale');
    }

    /**
     * Test filtering of properties where offer type is 'all'.
     */
    public function testFilterOfferTypeIsAll()
    {
        $response = $this->json('GET', "{$this->uri}?filter[offer_type]=all", []);

        $response->assertStatus(200);
        $response->assertJsonFragment([ 'offer_type' => 'for sale' ]);
        $response->assertJsonFragment([ 'offer_type' => 'for rent' ]);
    }

    /**
     * Test filtering of properties by created_by attribute.
     */
    public function testFilterCreatedBy()
    {
        $businessAccount = Property::inRandomOrder()->first()->agent;

        $expectedPropertiesCount = $businessAccount->properties->count();

        $response = $this->filterBy('created_by', $businessAccount->id);

        $response->assertJson([
            'meta' => [
                'total' => $expectedPropertiesCount, 
            ], 
        ]);
    }

    /**
     * Test filtering of properties where property_type is 'condominium'.
     */
    public function testFilterPropertyTypeIsCondominium()
    {
        $this->filterBy('property_type', 'condominium');
    }

    /**
     * Test filtering of properties where property_type is 'office'.
     */
    public function testFilterPropertyTypeIsOffice()
    {
        $this->filterBy('property_type', 'office');
    }

    /**
     * Test filtering of properties where property_type is 'lot'.
     */
    public function testFilterPropertyTypeIsLot()
    {
        $this->filterBy('property_type', 'lot');
    }

    /**
     * Test filtering of properties where property_type is 'all'.
     */
    public function testFilterPropertyTypeIsAll()
    {
        $response = $this->json('GET', "{$this->uri}?filter[property_type]=all&per_page=999", []);

        $response->assertStatus(200);

        $response->assertJsonFragment([ 'property_type' => 'condominium' ]);
        $response->assertJsonFragment([ 'property_type' => 'office' ]);
        $response->assertJsonFragment([ 'property_type' => 'house and lot' ]);
    }

    /**
     * Test filtering of properties price range.
     */
    public function testFilterPropertyPriceRange()
    {
        $this->filterRangeBy('price', 20000, 500000);
    }

    /**
     * Test filtering of properties lot size range.
     */
    public function testFilterPropertyLotSizeRange()
    {
        $this->filterRangeBy('lot_size', 100, 1000);
    }

    /**
     * Test filtering of properties bedroom count.
     */
    public function testFilterPropertyBedroomCount()
    {
        $this->filterMinimumCountBy('bedroom', 1);
    }

    /**
     * Test filtering of properties bathroom count.
     */
    public function testFilterPropertyBathroomCount()
    {
        $this->filterMinimumCountBy('bathroom', 1);
    }

    /**
     * Test filtering of properties garage count.
     */
    public function testFilterPropertyGarageCount()
    {
        $this->filterMinimumCountBy('garage', 1);
    }

    /**
     * Test filtering of properties by term.
     */
    public function testAddressFilter()
    {
        $this->filterBy('address');
    }
    
    /**
     * Filter properties by name.
     */
    public function testListWithNameFilter()
    {
        $this->filterBy('name');
    }

    /**
     * Filter properties by building name.
     */
    public function testListWithBuildingNameFilter()
    {
        $this->filterBy('building_name');
    }

    /**
     * Filter properties by listing_id.
     */
    public function testListWithListingIdFilter()
    {
        $this->filterBy('listing_id');
    }

    /**
     * Check properties search.
     *
     * @param string $field
     * @param string $searchTerm
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    public function filterBy(string $field, string $searchTerm = null)
    {
        $property = (new PropertyFactory())->create();

        $searchTerm = str_replace_last('/', '', $searchTerm ?? substr($property[$field], 0, 3));

        $response = $this->json(
            'GET', 
            "{$this->uri}?per_page=50&filter[$field]={$searchTerm}", 
            []
        );

        $response->assertStatus(200);

        $responseData = collect(data_get($response->json(), 'data'));

        $responseData->each(function ($property) use ($field, $searchTerm) {
            $this->assertTrue(
                str_contains(
                    strtolower($property[$field]), 
                    explode(',', trim(strtolower($searchTerm)))
                )
            );
        });

        return $response;
    }
    
    /**
     * Check properties search by range.
     *
     * @param string $field
     * @param int $minValue
     * @param int $maxValue
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    public function filterRangeBy(string $field, int $minValue = 0, int $maxValue = 0) 
    {
        $minFilter = $minValue >= 0 ? "filter[min_{$field}]={$minValue}" : '';
        $maxFilter = $maxValue >= 0 ? "filter[max_{$field}]={$maxValue}" : '';

        $response = $this->json(
            'GET', 
            "{$this->uri}?{$minFilter}&{$maxFilter}", 
            []
        );

        $response->assertStatus(200);

        $responseData = collect(data_get($response->json(), 'data'));

        $responseData->each(function ($property) use ($field, $minValue, $maxValue) {
            $this->assertTrue($property[ $field ] >= $minValue && $property[ $field ] <= $maxValue);
        });

        return $response;
    }

    /**
     * Check filtering by minimum count.
     *
     * @param string $field
     * @param int $minValue
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    public function filterMinimumCountBy(string $field, int $minValue = 0)
    {
        $response = $this->json(
            'GET', 
            "{$this->uri}?filter[min_{$field}]={$minValue}", 
            []
        );

        $response->assertStatus(200);

        $responseData = collect(data_get($response->json(), 'data'));

        $responseData->each(function ($property) use ($field, $minValue) {
            $this->assertTrue($property[ "{$field}_count" ] >= $minValue);
        });

        return $response;
    }
}
