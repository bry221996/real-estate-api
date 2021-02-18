<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V1\Helper\TestHelperTrait;

class PropertyModelStructureTest extends TestCase
{
    use RefreshDatabase;

    public function setUp()
    {
        parent::setUp();
    
        \Artisan::call('db:seed', [
            '--class' => 'DefaultsSeeder'
        ]);

        \Artisan::call('db:seed', [
            '--class' => 'TestSeeder'
        ]);
    }
    
    /**
     * Test attribute is equal to formatted_address without the unit value
     * when the property type is condominium or office.
     */
    public function testDisplayAddressAttribute()
    {
        // when unit has the 'unit' word
        $unit = 'unit 123';
        $address = '439 Karley Loaf Suite 897';

        $property = factory(\App\Property::class)->make([
            'formatted_address' => "{$unit} {$address}", 
            'unit' => $unit, 
        ]);

        $this->assertEquals(strtolower($property->display_address), strtolower($address));

        // when unit is only a number
        $unit = '1111222';
        $address = '439 Karley Loaf Suite 897';

        $property = factory(\App\Property::class)->make([
            'formatted_address' => "{$unit} {$address}", 
            'unit' => $unit, 
        ]);

        $this->assertEquals(strtolower($property->display_address), strtolower($address));
    }
}
