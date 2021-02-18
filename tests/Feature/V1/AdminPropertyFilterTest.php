<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Setup\PropertyFactory;
use Tests\Feature\V1\Setup\UserFactory;

class AdminPropertyFilterTest extends PropertyFilter
{
    /**
     * Business account owned properties.
     *
     * @var array
     */
    private $properties;

    public function setUp()
    {
        parent::setUp();

        $this->uri = '/api/v1/properties';

        $this->properties = (new PropertyFactory())->setCount(10)->create();

        $this->properties->each(function ($property) {
            $property->update([ 'property_type_id' => rand(1, 3) ]);

            $property->refresh();
        });

        Passport::actingAs((new UserFactory())->setRoles('admin')->create());
    }

    /**
     * Test filter by property status.
     */
    public function testStatusFilter()
    {
        $this->properties->each(function ($property) {
            $property->update([ 'property_status_id' => rand(1, 4) ]);

            $property->refresh();
        });

        $response = $this->filterBy('property_status', 'pending,published,rejected,closed,expired');

        $this->assertGreaterThan(1, data_get($response->json(), 'data'));
    }

    /**
     * Test filter by invalid property status.
     */
    public function testNoResultStatusFilter()
    {
        $this->filterBy('property_status', 'foo,bar,etc')
            ->assertJsonCount(0, 'data');
    }
}
