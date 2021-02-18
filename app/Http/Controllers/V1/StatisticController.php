<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultResource;
use App\User;
use App\Property;
use App\Appointment;

class StatisticController extends Controller
{
    /**
     * Get summary of users(business account and customer), Properties and appointments.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSummary()
    {
        $data = [
            'customers' => [
                'total' => $this->getUserCountWithRoleAndStatus([ 3 ]), 
                'verified' => $this->getUserCountWithRoleAndStatus([ 3 ], true), 
                'unverified' => $this->getUserCountWithRoleAndStatus([ 3 ], false), 
                'registered_today' => $this->getUserCountThatRegisteredToday([ 3 ]), 
            ], 
            'business_accounts' => [
                'total' => $this->getUserCountWithRoleAndStatus([ 4, 5 ]), 
                'verified' => $this->getUserCountWithRoleAndStatus([ 4, 5 ], true), 
                'unverified' => $this->getUserCountWithRoleAndStatus([ 4, 5 ], false), 
                'registered_today' => $this->getUserCountThatRegisteredToday([ 4, 5 ]), 
            ], 
            'properties' => [
                'total' => Property::setEagerLoads([])->propertyStatus('all')->count(), 
                'published' => Property::setEagerLoads([])->propertyStatus('published')->count(), 
                'pending' => Property::setEagerLoads([])->propertyStatus('pending')->count(), 
                'rejected' => Property::setEagerLoads([])->propertyStatus('rejected')->count(), 
                'expired' => Property::setEagerLoads([])->propertyStatus('expired')->count(), 
            ], 
            'appointments' => [
                'total' => Appointment::count(), 
                'completed' => Appointment::completed()->count(), 
                'confirmed' => Appointment::confirmed()->count(),
                'pending' => Appointment::pending()->count(),
                'rejected' => Appointment::rejected()->count(),
                'appointments_today' => Appointment::startDateTime(now()->startOfDay())
                    ->endDateTime(now()->endOfDay())
                    ->count(), 
                'appointments_this_week' => Appointment::startDateTime(now()->startOfWeek())
                    ->endDateTime(now()->endOfWeek())
                    ->count(), 
                'appointments_this_month' => Appointment::startDateTime(now()->startOfMonth())
                    ->endDateTime(now()->endOfMonth())
                    ->count(), 
            ], 
        ];

        return new DefaultResource(collect($data));
    }

    /**
     * Get the user count with the specified status.
     *
     * @param array $roleIds = []
     * @param bool $verified
     * @return int
     */
    private function getUserCountWithRoleAndStatus($roleIds = [], $verified = null)
    {
        return User::exemptAdmin()->exemptSuperAdmin()->with('roles')
            ->whereHas('roles', function ($query) use ($roleIds, $verified) {
                $query->whereIn('user_role.role_id', $roleIds);

                if ($verified !== null) {
                    $query->where('user_role.verified', $verified);
                }
            })
            ->count();
    }

    /**
     * Get user's that registered today.
     *
     * @param array $roleIds = []
     * @return int
     */
    private function getUserCountThatRegisteredToday($roleIds = [])
    {
        return User::exemptAdmin()->exemptSuperAdmin()->with('roles')
            ->whereHas('roles', function ($query) use ($roleIds) {
                $query->whereIn('user_role.role_id', $roleIds);
            })
            ->whereDate('users.created_at', now()->toDateString())
            ->count();
    }
}

