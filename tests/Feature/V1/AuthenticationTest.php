<?php

namespace Tests\Feature\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;
use App\User;

class AuthenticationTest extends TestCase
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
     * Request token using customer account is ucode.
     * Password is ucode.
     */
    public function testCustomerLogin()
    {
        $customer = $this->createCustomer();
        $code = 1234;

        // mock ucode
        \Cache::shouldReceive('tags->remember')->once()->andReturn($code);
        \Cache::shouldReceive('tags->forget')->once();

        $response = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => 1,
            'client_secret' => 'CEQeW6nt6i73uNEsNzQD6RwqjGL351yDHtJyeryY',
            'username' => $customer->mobile,
            'password' => $code,
            'scope' => '',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'token_type',
                'expires_in',
                'access_token',
                'refresh_token',
            ]);
    }

    /**
     * Request token using customer account username and password.
     */
    public function testBusinessAccountLoginUsingUsernameAndPassword()
    {
        $username = 'businessaccount1';
        $password = 'testBA';

        $businessAccount = factory(User::class)->create([
            'username' => $username, 
            'password' => bcrypt($password), 
        ]);       

        $businessAccount->roles()->attach(4);

        $this->accountLogin($username, $password);
    }

    /**
     * Request token using customer account email and password.
     */
    public function testBusinessAccountLoginUsingEmailAndPassword()
    {
        $email = 'businessaccount1@sample.com';
        $password = 'testBA';

        $businessAccount = factory(User::class)->create([
            'email' => $email, 
            'password' => bcrypt($password), 
        ]);       

        $businessAccount->roles()->attach(4);

        $this->accountLogin($email, $password);
    }

    /**
     * Request token using customer account mobile and verfication code.
     */
    public function testBusinessAccountLoginUsingMobileAndVerificationCode()
    {
        $mobile = '639123123123';
        $code = 1234;

        $businessAccount = factory(User::class)->create([
            'mobile' => $mobile, 
        ]);

        $businessAccount->roles()->attach(4);

        // mock verification code
        \Cache::shouldReceive('tags->remember')->once()->andReturn($code);
        \Cache::shouldReceive('tags->forget')->once();

        $this->accountLogin($mobile, $code);
    }

    /**
     * Test user with business account and cutomer role.
     */
    public function testMultiRoleUserLogin()
    {
        $user = $this->createBusinessAccount();

        $user->roles()->attach(3, [
            'verified' => 1, 
            'verified_at' => now()->toDateTimeString(), 
        ]);

        $this->accountLogin($user->email, 'testuser');
    }

    /**
     * Business account login.
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    private function accountLogin($username, $password)
    {
        $response = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => 1,
            'client_secret' => 'CEQeW6nt6i73uNEsNzQD6RwqjGL351yDHtJyeryY',
            'username' => $username,
            'password' => $password,
            'scope' => '',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'token_type',
                'expires_in',
                'access_token',
                'refresh_token',
            ]);
    }

    /**
     * Test requesting of verification code.
     */
    public function testRequestCode()
    {
        $user = $this->createCustomer();

        $expectedUcode = 1234;

        // mock verification code
        \Cache::shouldReceive('tags->remember')->andReturn($expectedUcode);

        $response = $this->json('POST', '/api/request_code', [
            'mobile' => $user->mobile, 
        ]);

        $this->assertEquals($user->verification_code, $expectedUcode);
        
        $response->assertStatus(200);
    }

    /**
     * Test account verification.
     */
    public function testVerifyAccount()
    {
        $user = $this->createCustomer();

        $expectedUcode = 1234;

        // mock verification code
        \Cache::shouldReceive('tags->remember')->andReturn($expectedUcode);

        $response = $this->json('POST', '/api/verify_account', [
            'mobile' => $user->mobile, 
            'verification_code' => $expectedUcode, 
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_role', [
            'user_id' => $user->id, 
            'role_id' => 3, 
            'verified' => 1, 
            'verified_at' => now()->toDateTimeString(), 
        ]);
    }
}
