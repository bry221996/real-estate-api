<?php

namespace Tests\Feature\V1;

use Laravel\Passport\Passport;
use Tests\Feature\V1\Setup\PropertyFactory;
use Tests\Feature\V1\Setup\UserFactory;

class BusinessAccountPropertyFilterTest extends PropertyFilter
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

        $this->uri = '/api/v1/account/properties';

        $agent = (new UserFactory())->setRoles('customer', 'agent')->create();

        $this->properties = (new PropertyFactory())->setCount(10)->create([ 'created_by' => $agent->id ]);

        $this->properties->each(function ($property) {
            $property->update([ 'property_type_id' => rand(1, 3) ]);

            $property->refresh();
        });

        Passport::actingAs($agent);
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
