<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;

class PasswordResetTest extends TestCase
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

        \Notification::fake();

        $this->beforeApplicationDestroyed(function () {
            $this->resetDatabaseTablesIncrements();
        });
    }

    /**
     * Test Ucode for password reset.
     */
    public function testRequestCode()
    {
        $user = $this->createBusinessAccount();

        $expectedUcode = 1234;

        // mock verification code
        \Cache::shouldReceive('tags->remember')->andReturn($expectedUcode);

        $response = $this->json('POST', '/api/password_reset/request_code', [
            'mobile' => $user->mobile, 
        ]);

        $this->assertEquals($user->verification_code, $expectedUcode);
        
        $response->assertStatus(200);
    }

    /**
     * Test update password.
     */
    public function testUpdatePassword()
    {
        $user = $this->createBusinessAccount();

        $expectedUcode = mt_rand(5000, 9999);
        $newPassword = 'mynewpassword';

        // check caching process
        \Cache::shouldReceive('tags->remember')->once()->andReturn($expectedUcode);
        \Cache::shouldReceive('tags->forget');

        $response = $this->json('PUT', '/api/password_reset', [
            'verification_code' => $expectedUcode, 
            'mobile' => $user->mobile, 
            'new_password' => $newPassword, 
            'new_password_confirmation' => $newPassword, 
        ]);

        $response->assertStatus(200);

        $this->assertTrue(\Hash::check($newPassword, $user->fresh()->password));
    }
}
