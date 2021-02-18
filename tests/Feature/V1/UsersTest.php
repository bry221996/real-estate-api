<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Feature\V1\Helper\TestHelperTrait;
use Tests\Feature\V1\Setup\UserFactory;
use App\User;

/**
 * Test cases when an admin is doing things on users endpoint.
 */
class UsersTest extends TestCase
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
     * Always remove super_admin.
     */
    public function testSuperAdminMustNotBeInTheList()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $response = $this->json('GET', '/api/v1/users', []);

        $response->assertStatus(200);

        $response->assertJsonMissing([
            'id' => 1, 
        ]);
    }

    /**
     * Test include user roles.
     */
    public function testIncludeRolesOnUsers()
    {
        $admin = $this->createAdmin();

        Passport::actingAs($admin);

        $response = $this->json('GET', '/api/v1/users?include=roles', []);

        $response->assertStatus(200);

        // no super admin
        $response->assertJsonMissing([
            'roles' => [
                'id' => 1, 
            ], 
        ]);

        $response->assertJsonStructure([
            'data', 
            'links', 
            'meta',
        ]);
    }

    /**
     * Test include relation.
     */
    public function testShowIncludeRelations()
    {
        $admin = $this->createAdmin();
        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($admin);

        $response = $this->json('GET', '/api/v1/users/' . $businessAccount->id . '?include=roles', []);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'roles' => [],
                ],
            ]);
    }

    /**
     * Get specific user.
     */
    public function testShowResource()
    {
        $admin = $this->createAdmin();
        $businessAccount = $this->createBusinessAccount();

        Passport::actingAs($admin);

        $response = $this->json('GET', '/api/v1/users/' . $businessAccount->id . '?include=roles', []);

        $response->assertStatus(200);
    }
}
