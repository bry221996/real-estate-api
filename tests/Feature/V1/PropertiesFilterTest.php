<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Setup\PropertyFactory;
use Tests\Feature\V1\Setup\UserFactory;

class PropertiesFilterTest extends PropertyFilter
{
    public function setUp()
    {
        parent::setUp();

        $this->uri = '/api/v1/properties';

        (new PropertyFactory())->setCount(3)->create([ 'property_type_id' => 1 ]);
        (new PropertyFactory())->setCount(3)->create([ 'property_type_id' => 2 ]);
        (new PropertyFactory())->setCount(3)->create([ 'property_type_id' => 3 ]);
    }

    /**
     * All property scopes must not be used by customer account type.
     */
    public function testFailScopeAllPropertyStatusShouldNotUsedByCustomers()
    {
        Passport::actingAs((new UserFactory())->setRoles('customer')->create());

        $publishedProperty = (new PropertyFactory())->create([ 'property_status_id' => 1 ]);
        $pendingProperty = (new PropertyFactory())->create([ 'property_status_id' => 2 ]);

        $response = $this->json('GET', "{$this->uri}?scope=all_property_status", []);

        $response->assertStatus(200)
            ->assertJsonMissing([ 'listing_id' => $pendingProperty->listing_id ]);
    }

    /**
     * All property scopes must not be used for guest user.
     */
    public function testFailScopeAllPropertyStatusShouldNotUsedByGuests()
    {
        $publishedProperty = (new PropertyFactory())->create([ 'property_status_id' => 1 ]);
        $pendingProperty = (new PropertyFactory())->create([ 'property_status_id' => 2 ]);

        $response = $this->json('GET', "{$this->uri}?scope=all_property_status", []);

        $response->assertStatus(200)
            ->assertJsonMissing([ 'listing_id' => $pendingProperty->listing_id ]);
    }

    /**
     * Guest should not be able to filter by property status. 
     */
    public function testFailFilterByPropertyStatusShouldNotBeUsedByGuest()
    {
        $publishedProperty = (new PropertyFactory())->create([ 'property_status_id' => 1 ]);
        $pendingProperty = (new PropertyFactory())->create([ 'property_status_id' => 2 ]);

        $response = $this->json('GET', "{$this->uri}?filter[property_status]=pending,published", []);

        $response->assertStatus(200)
            ->assertJsonMissing([ 'listing_id' => $pendingProperty->listing_id ]);
    }

    /**
     * Guest should not be able to filter by property customers. 
     */
    public function testFailFilterByPropertyStatusShouldNotBeUsedByCustomer()
    {
        Passport::actingAs((new UserFactory())->setRoles('customer')->create());
        
        $publishedProperty = (new PropertyFactory())->create([ 'property_status_id' => 1 ]);
        $pendingProperty = (new PropertyFactory())->create([ 'property_status_id' => 2 ]);

        $response = $this->json('GET', "{$this->uri}?filter[property_status]=pending,published", []);

        $response->assertStatus(200)
            ->assertJsonMissing([ 'listing_id' => $pendingProperty->listing_id ]);
    }
}
