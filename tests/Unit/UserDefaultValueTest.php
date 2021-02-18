<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserDefaultValueTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function setUp()
    {
        parent::setUp();
        
        $this->beforeApplicationDestroyed(function () {
            $this->resetDatabaseTablesIncrements();
        });
    }

    /**
     * Test default photo on user create.
     */
    public function testHasDefaultPhotoOnCreate()
    {
        $user = \App\User::create([
            'mobile' => $this->faker->numerify('639#########'), 
        ]);

        $this->assertDatabaseHas('users', [
            'mobile' => $user->mobile,
            'photo' => 'https://s3-ap-southeast-1.amazonaws.com/lazatu/resources/images/default-profile-image.png',
        ]);
    }
}
