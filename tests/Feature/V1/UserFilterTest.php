<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Setup\UserFactory;

class UserFilterTest extends TestCase
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

        Passport::actingAs($this->createAdmin());
    }
/**
     * Test filtering of customers role.
     */
    public function testFilterCustomers()
    {
        // create sample cusytomers
        for ($i = 0; $i < 5; $i++) {
            $this->createCustomer();
        }

        $response = $this->json('GET', '/api/v1/users?include=roles&filter[roles]=customer', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                [
                    'roles' => [
                        [
                            'id' => 3,
                            'name' => 'customer',
                        ]
                    ],
                ]
            ],
        ]);

        $response->assertJsonMissing(['name' => 'admin']);
        $response->assertJsonMissing(['name' => 'agent']);
        $response->assertJsonMissing(['name' => 'owner']);
    }

    /**
     * Test filtering of agent role.
     */
    public function testFilterAgents()
    {
        $this->createBusinessAccount(5); // owner type
        $this->createBusinessAccount(5); // owner type
        $this->createBusinessAccount(5); // owner type
        $this->createBusinessAccount(); // agent type
        $this->createBusinessAccount(); // agent type

        $response = $this->json('GET', '/api/v1/users?include=roles&filter[roles]=agent', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                [
                    'roles' => [
                        [
                            'id' => 5, 
                            'name' => 'agent', 
                        ]
                    ], 
                ]
            ], 
        ]);

        $response->assertJsonMissing(['name' => 'admin']);
        $response->assertJsonMissing(['name' => 'owner']);
    }

    /**
     * Test filtering of agent and owner role.
     */
    public function testFilterAgentsAndOwners()
    {
        $this->createBusinessAccount(5); // owner type
        $this->createBusinessAccount(5); // owner type
        $this->createBusinessAccount(5); // owner type
        $this->createBusinessAccount(); // agent type
        $this->createBusinessAccount(); // agent type

        $response = $this->json('GET', '/api/v1/users?include=roles&filter[roles]=agent,owner', []);

        $response->assertStatus(200);

        $response->assertJsonFragment([ 'name' => 'owner' ]);
        $response->assertJsonFragment([ 'name' => 'agent' ]);

        $response->assertJsonMissing(['name' => 'admin']);
    }

    /**
     * Test filtering of owner role.
     */
    public function testFilterOwners()
    {
        $this->createBusinessAccount(5); // owner type
        $this->createBusinessAccount(5); // owner type
        $this->createBusinessAccount(5); // owner type
        $this->createBusinessAccount(); // agent type
        $this->createBusinessAccount(); // agent type

        $response = $this->json('GET', '/api/v1/users?include=roles&filter[roles]=owner', []);

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                [
                    'roles' => [
                        [
                            'id' => 4, 
                            'name' => 'owner', 
                        ]
                    ],
                ]
            ],
        ]);

        $response->assertJsonMissing(['name' => 'admin']);
        $response->assertJsonMissing(['name' => 'agent']);
    }

    /**
     * Test Filtering using first_name.
     */
    public function testFilterFirstName()
    {
        $user = (new UserFactory())
            ->setRoles('customer')
            ->create();

        $otherUsers = (new UserFactory())
            ->setRoles('customer')
            ->create();

        $this->json('GET', "/api/v1/users?filter[first_name]=$user->first_name")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    [ 'id' => $user->id ]
                ]
            ])
            ->assertJsonMissing(['id' => $otherUsers->id]);
    }

    /**
     * Test Filtering using last_name.
     */
    public function testFilterLastName()
    {
        $user = (new UserFactory())
            ->setRoles('customer')
            ->create();

        $otherUsers = (new UserFactory())
            ->setRoles('customer')
            ->create();

        $this->json('GET', "/api/v1/users?filter[last_name]=$user->last_name")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    [ 'id' => $user->id ]
                ]
            ])
            ->assertJsonMissing(['id' => $otherUsers->id]);
    }

    /**
     * Test Filtering using mobile.
     */
    public function testFilterMobile()
    {
        $user = (new UserFactory())
            ->setRoles('customer')
            ->create();

        $otherUsers = (new UserFactory())
            ->setRoles('customer')
            ->setCount(2)
            ->create();

        $this->json('GET', "/api/v1/users?filter[mobile]=$user->mobile")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    [ 'id' => $user->id ]
                ]
            ])
            ->assertJsonMissing(['id' => $otherUsers->random()->id]);
    }


    /**
     * Test Filtering using email.
     */
    public function testFilterEmail()
    {
        $user = (new UserFactory())
            ->setRoles('customer')
            ->create();

        $otherUsers = (new UserFactory())
            ->setRoles('customer')
            ->setCount(2)
            ->create();

        $this->json('GET', "/api/v1/users?filter[email]=$user->email")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    [ 'id' => $user->id ]
                ]
            ])
            ->assertJsonMissing(['id' => $otherUsers->random()->id]);
    }
}
