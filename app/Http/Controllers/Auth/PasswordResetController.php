<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Notifications\TestNotification;
use App\Notifications\UcodeRequest;
use Notification;
use App\User;

class PasswordResetController extends Controller
{
    /**
     * Get or generate verification code for user.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function requestCode(Request $request)
    {
        $request->validate([
            'mobile' => 'required|regex:/^639\d{9}$/|exists:users,mobile',
        ], [
            'mobile.regex' => 'Invalid mobile number.', 
        ]);

        $user = User::where('mobile', $request->mobile)->whereHas('roles', function ($query) {
                $query->whereIn('user_role.role_id', [ 4, 5 ]);
            })
            ->firstOrFail();

        try {
            if (env('APP_ENV') != 'production') {
                Notification::send(
                    $user, 
                    new TestNotification("ucode for {$user->mobile}: {$user->verification_code}")
                );
            }

            Notification::send($user, new UcodeRequest($user->verification_code));
        } catch (\Exception $e) {
            logger()->error($e);
        }

        return response([
            'message' => 'Verification code sent.', 
        ]);
    }

    /**
     * Update user password.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $request->validate([
            'mobile' => 'required|regex:/^639\d{9}$/|exists:users,mobile',
            'verification_code' => 'required',
            'new_password' => 'required|min:6|confirmed'
        ], [
            'mobile.regex'  => 'Invalid mobile number.', 
            'mobile.exists' => 'Mobile Number not exists'
        ]);

        $user = User::where('mobile', $request->mobile)->whereHas('roles', function ($query) {
                $query->whereIn('user_role.role_id', [ 4, 5 ]);
            })
            ->firstOrFail();

        if ((int) $request->verification_code != (int) $user->verification_code) {
            return response([
                'message' => 'Invalid verification code.', 
            ], 422);
        }

        $user->update([
            'password' => bcrypt($request->new_password)
        ]);

        $user->forgetUcode();

        return response([
            'message' => 'Password updated.'
        ]);
    }
}
