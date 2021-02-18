<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Setup\UserFactory;
use Laravel\Passport\Passport;

class BusinessAccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    private $businessAccount;

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

        $this->admin = (new UserFactory)->setRoles('admin')->create();

        Passport::actingAs($this->admin);

        $this->businessAccount = (new UserFactory)->setRoles('owner')->create();

        $this->businessAccount->roles()->updateExistingPivot(4, [
            'verified' => 0, 
            'verified_at' => null, 
        ]);
    }
    
    /**
     * Test admin can verifies business account.
     */
    public function testAdminVerifiesBusinessAccount()
    {
        $this->checkIfBusinessAccountIsUnverified();

        $roleId = $this->businessAccount->roles()->first()->id;

        $this->businessAccount->update([
            'prc_registration_number' => '12312311', 
        ]);

        $response = $this->json('POST', '/api/v1/users/' . $this->businessAccount->id . '/verify', [
            'role_id' => $roleId, 
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_role', [
            'user_id' => $this->businessAccount->id, 
            'role_id' => $roleId, 
            'verified' => 1, 
            'verified_at' => now()->toDateTimeString(), 
        ]);

        $this->assertTrue($this->businessAccount->business_account_is_verified);
    }

    /**
     * Test admin fail to verify business account because it's missing the prc id.
     */
    public function testFailAdminVerifiesBusinessAccountWithoutPrcId()
    {
        $this->checkIfBusinessAccountIsUnverified();

        $response = $this->json('POST', '/api/v1/users/' . $this->businessAccount->id . '/verify', [
            'role_id' => 4, 
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('user_role', [
            'user_id' => $this->businessAccount->id, 
            'role_id' => 4, 
            'verified' => 0, 
            'verified_at' => null, 
        ]);

        $this->assertFalse($this->businessAccount->business_account_is_verified);
    }

    /**
     * Checking of the setup business account. 
     */
    private function checkIfBusinessAccountIsUnverified()
    {
        // check if the business account has only 1 role.
        $this->assertCount(1, $this->businessAccount->roles);

        // check the business account is unverified
        $this->assertDatabaseHas('user_role', [
            'user_id' => $this->businessAccount->id, 
            'role_id' => $this->businessAccount->roles()->first()->id, 
            'verified' => 0, 
            'verified_at' => null, 
        ]);
    }
}
