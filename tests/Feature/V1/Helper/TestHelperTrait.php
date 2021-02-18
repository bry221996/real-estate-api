<?php

namespace Tests\Feature\V1\Helper;
use Illuminate\Support\Facades\Redis;
use App\User;
use App\Feature;
use App\Property;
use App\PropertyPhoto;
use App\PropertyAttachment;
use App\PropertyHistory;
use App\BusinessAccountSchedule;

trait TestHelperTrait
{
    /**
     * Create customer account.
     */
    public function createCustomer()
    {
        $user = factory(User::class)->create();
        
        $user->roles()->attach(3, [
            'verified' => 1,
            'verified_at' => now()->toDateTimeString(),
        ]);

        return $user;
    }

    /**
     * Create customers.
     * 
     * @param int $count
     * @param bool $verified
     * @param array $data
     */
    public function createCustomers($count = 1, $verified = true, $data = [])
    {
        $users = factory(User::class, $count)->create($data);

        $users->each(function ($user) use ($verified) {
            $user->roles()->attach(3, [
                'verified' => $verified ? 1 : 0,
                'verified_at' => $verified ? now()->toDateTimeString() : null,
            ]);
        });

        return $users;
    }

    /**
     * Create customer account.
     * 
     * @param int $roleId
     */
    public function createBusinessAccount($roleId = 4)
    {
        $roleId = $roleId == 4 ? 4 : 5;

        $user = factory(User::class)->create();       

        $user->roles()->attach([ $roleId, 3 ], [
            'verified' => 1,
            'verified_at' => now()->toDateTimeString(),
        ]);
        
        $schedule = factory(BusinessAccountSchedule::class)->create([ 'user_id' => $user->id ]);
    
        return $user;
    }

    /**
     * Create business accounts.
     * 
     * @param int $count
     * @param bool $verified
     * @param array $data
     */
    public function createBusinessAccounts($count = 1, $verified = true, $data = [])
    {
        $businessAccounts = factory(User::class, $count)->create($data);

        $businessAccounts->each(function ($businessAccount) use ($verified) {
            $businessAccount->roles()->attach(rand(4, 5), [
                'verified' => $verified ? 1 : 0,
                'verified_at' => $verified ? now()->toDateTimeString() : null,
            ]);

            $schedule = factory(BusinessAccountSchedule::class)->create([ 'user_id' => $businessAccount->id ]);
        });
        
        return $businessAccounts;
    }

    /**
     * Create admin account.
     */
    public function createAdmin()
    {
        $admin = factory(User::class)->create();

        $admin->roles()->attach(2, [
            'verified' => 1, 
            'verified_at' => now()->toDateTimeString(), 
        ]);

        return $admin;
    }

    /**
     * Create property.
     *
     * @param array $data
     * @return \App\Property
     */
    public function createProperty($data = [])
    {
        $property = factory(Property::class)->create($data);

        $randomFeatures = Feature::get()->random(5)->pluck('id');

        $property->features()->attach($randomFeatures);

        $photos = factory(PropertyPhoto::class, 5)
            ->make([ 'property_id' => $property->id ])
            ->toArray();

        $property->photos()->createMany($photos);

        $attachments = factory(PropertyAttachment::class, 3)
            ->make([ 'property_id' => $property->id ])
            ->toArray();

        $property->attachments()->createMany($attachments);

        $property->saveToHistory();

        return $property;
    }

    /**
     * Create properties
     * 
     * @param int $count
     * @param array $data
     * @return array
     */
    public function createProperties($count = 5, $data = [])
    {
        if (Feature::count() < 30) {
            factory(Feature::class, 30)->create();
        }

        $businessAccount = $this->createBusinessAccount();

        $data['created_by'] = $businessAccount->id;

        $properties = collect();

        foreach (range(1, $count) as $counter) {
            $properties->push($this->createProperty($data));
        }

        return $properties;
    }

    /**
     * Reset all mysql database tables increments.
     *
     * @return void
     */
    public function resetDatabaseTablesIncrements()
    {
        $tables = collect(\DB::select('SHOW TABLES'));

        $tables->each(function ($table) {
            $tableName = collect($table)->first();

            \DB::statement("ALTER TABLE $tableName AUTO_INCREMENT = 1");
        });
    }

    /**
     * Clear Redis Test Database.
     */
    public function clearRedis()
    {
        Redis::flushdb();
    }
}
