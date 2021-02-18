<?php

use Illuminate\Database\Seeder;
use App\Role;

/**
 * Default data for database initialization.
 */
class DefaultsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->createRoles();
        $this->createSuperAdmin();
        $this->createOauthClient();
        $this->createPropertyStatus();
        $this->createAppointmentTypes();
        $this->createBusinessAccountScheduleType();
        $this->createAppointmentStatuses();
    }

    private function createRoles()
    {
        Role::insert([
            [
                'name' => 'super_admin', 
                'description' => 'root', 
            ],
            [
                'name' => 'admin', 
                'description' => 'Lazatu Operation', 
            ],
            [
                'name' => 'customer', 
                'description' => null, 
            ],
            [
                'name' => 'owner', 
                'description' => 'Lazatu Business Account', 
            ],
            [
                'name' => 'agent', 
                'description' => 'Lazatu Business Account', 
            ],
        ]);
    }

    private function createSuperAdmin()
    {
        $user = \App\User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin', 
            'gender' => 'male', 
            'email' => 'admin@system.org', 
            'mobile' => '7873723646', 
            'password' => bcrypt('jCW^)C8@'), 
        ]);

        $user->roles()->attach(1, [
            'verified' => 1, 
            'verified_at' => now()->toDateTimeString(), 
        ]);
    }

    public function createOauthClient()
    {
        \DB::table('oauth_clients')->insert([
            'name'                   => 'Lazatu Password Grant Client',
            'secret'                 => 'CEQeW6nt6i73uNEsNzQD6RwqjGL351yDHtJyeryY',
            'redirect'               => 'http://localhost',
            'personal_access_client' => 0,
            'password_client'        => 1,
            'revoked'                => 0,
        ]);
    }

    private function createPropertyStatus()
    {
        App\PropertyStatus::insert([
            [ 'name' => 'Published' ],
            [ 'name' => 'Pending' ],
            [ 'name' => 'Rejected' ],
            [ 'name' => 'Closed' ],
        ]);
    }

    private function createAppointmentTypes()
    {
        App\AppointmentType::insert([
            [ 'name' => 'Site visit' ],
            [ 'name' => 'Inquiry' ],
            [ 'name' => 'Follow up' ],
        ]);
    }

    public function createBusinessAccountScheduleType()
    {
        App\BusinessAccountScheduleType::insert([
            [
                'name' => 'Regular business hours', 
                'description' => 'Moday to Friday starting 09:00 to 18:00', 
            ],
            [
                'name' => 'Wekends', 
                'description' => 'Saturday and Sunday starting 09:00 to 18:00', 
            ],
            [
                'name' => 'After office hours', 
                'description' => 'Monday to Friday starting 18:00 to 00:00', 
            ],
            [
                'name' => 'After office hours', 
                'description' => 'Monday to Sunday starting 00:00 to 00:00', 
            ],
        ]);
    }

    public function createAppointmentStatuses()
    {
        App\AppointmentStatus::insert([
            [ 'name' => 'Confirmed' ],
            [ 'name' => 'Pending' ],
            [ 'name' => 'Rejected' ],
            [ 'name' => 'Cancelled' ],
            [ 'name' => 'Reschedule' ],
            [ 'name' => 'Completed' ],
            [ 'name' => 'Expired' ],
        ]);
    }
}
