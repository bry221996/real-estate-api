<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Notification;
use Auth;
use App\Notifications\TestNotification;
use App\Notifications\UcodeRequest;
use App\Rules\ValidMobile;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Create new user.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'role_id' => 'required|in:3,4,5|numeric',
        ]);

        $response = response([
            'message' => 'Invalid data.', 
        ]);

        if ($request->role_id == 3) {
            $response = $this->createCustomer($request);
        }

        if (in_array($request->role_id, [4, 5])) {
            $response = $this->createBusinessAccount($request);
        }

        return $response;
    }

    /**
     * Get or generate verification code for customer.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function requestCode(Request $request)
    {
        $request->validate([
            'mobile' => 'required|regex:/^639\d{9}$/',
        ], [
            'mobile.regex' => 'Invalid mobile number.', 
        ]);

        $user = new User([ 'mobile' => $request->mobile ]);

        // send code logic here.
        try {
            if (env('APP_ENV') != 'production') {
                Notification::send(
                    $user, 
                    new TestNotification("ucode for {$request->mobile}: {$user->verification_code}")
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
     * Verify account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function verifyAccount(Request $request)
    {
        $request->validate([
            'mobile' => 'required|regex:/^639\d{9}$/',
            'verification_code' => 'required|min:1000|max:9999|numeric',
        ], [
            'verification_code.min' => 'The sms code must be 4 digits.', 
            'verification_code.max' => 'The sms code must be 4 digits.', 
            'mobile.regex' => 'Invalid mobile number.', 
        ]);
        
        $user = User::where('mobile', $request->mobile)->first() ?? new User([ 'mobile' => $request->mobile ]);

        $code = $user->verification_code;

        if ((int) $request->verification_code != (int) $code) {
            return response([
                'message' => 'Invalid verification code.', 
            ], 422);
        }

        // create the customer account if no user is found.
        if (! $user->exists) {
            $request->merge(['role_id' => 3]);

            $this->createCustomer($request);
        }

        // update user verified status
        User::where('mobile', $request->mobile)->first()
            ->roles()
            ->updateExistingPivot(3, [
                'verified' => 1,
                'verified_at' => now()->toDateTimeString(),     
            ]);

        return response([
            'message' => 'Verification successful.', 
        ]);
    }

    /**
     * Delete user access token.
     * 
     * @return \Illuminate\Http\Response
     */
    public function logout()
    {
        if (! Auth::check()) {
            return response([
                'message' => 'No user found.', 
            ], 422);
        }

        Auth::user()->token()->delete();

        return response([
            'message' => 'Logout success.', 
        ]);
    }

    /**
     * Create Customer account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    private function createCustomer(Request $request)
    {
        $data = $request->validate([
            'mobile' => 'required|unique:users,mobile|regex:/^639\d{9}$/',
        ], [
            'mobile.regex' => 'Invalid mobile number.', 
        ]);

        $user = User::create($data);
        $user->roles()->attach($request->role_id);

        return response([
            'message' => 'Registration successful.', 
        ]);
    }

    /**
     * Create business account.
     * A customer can create a business account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    private function createBusinessAccount(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required', 
            'last_name' => 'required', 
            'mobile' => [
                'required',
                'regex:/^639\d{9}$/',
                new ValidMobile()
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore(request('mobile'), 'mobile')
            ], 
            'username' => 'required|unique:users', 
            'gender' => 'sometimes|in:male,female', 
            'marital_status' => 'sometimes', 
            'location' => 'sometimes', 
            'password' => 'required|min:6|confirmed', 
        ], [
            'mobile.regex' => 'Invalid mobile number.', 
        ]);

        $data['password'] = bcrypt($data['password']);

        $user = User::firstOrCreate(
            [ 'mobile' => $data['mobile'] ],
            $data
        );

        if ($user->wasRecentlyCreated) {
            $user->roles()->attach([$request->role_id, 3]);
        } else {
            $user->update($data);

            $user->roles()->attach($request->role_id);
        }

        return response([
            'message' => 'Business account registration successful.', 
        ]);
    }
}
