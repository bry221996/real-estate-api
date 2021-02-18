<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\DefaultCollection;
use App\Filters\UserFilter;
use App\User;
use Cache;
use Storage;
use Validator;

class UserController extends Controller
{
    /**
     * Get list of resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, UserFilter $filters)
    {
        $usersQuery = User::exemptSuperAdmin()->filter($filters);

        if ($request->filled('include')) {
            $usersQuery->includes(explode(',', $request->include));
        }

        $users = $usersQuery->paginate($request->per_page ?? 10);

        return new DefaultCollection($users);
    }

    /**
     * Show resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User $user
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, User $user)
    {
        if ($request->filled('include')) {
            $user->includes(explode(',', $request->include));
        }

        return new DefaultResource($user);
    }

    /**
     * Create new resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'role_id' => 'required|in:3,4,5|numeric',
            'mobile' => 'required|unique:users,mobile|regex:/^639\d{9}$/',
            'first_name' => 'sometimes', 
            'last_name' => 'sometimes', 
            'gender' => 'sometimes|in:male,female', 
            'marital_status' => 'sometimes', 
            'email' => 'sometimes|unique:users,email', 
            'location' => 'sometimes', 
        ], [
            'mobile.regex' => 'Invalid mobile number.', 
        ]);

        $user = User::create($data);

        Cache::tags('verification_code')
            ->remember($user->mobile . '_code', 10, function () {
                return mt_rand(1000, 9999);
            });

        // send code via 3rd party service

        return response([
            'message' => 'Registration successful. A 4 digit code is sent to you for account verification.', 
        ]);
    }

    /**
     * Get current authenticated user.
     *
     * @param I\lluminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getCurrentAuthenticatedUser(Request $request)
    {
        $userQuery = User::where('id', auth()->id());

        if ($request->has('include')) {
            $requestIncludedFields = explode(',', $request->include);

            $validIncludeKeys = collect([
                'roles',
            ]);

            $includeKeys = $validIncludeKeys->intersect($requestIncludedFields);

            $userQuery->with($includeKeys->toArray());
        }

        $user = $userQuery->first();

        $user->append('has_business_account');

        return new DefaultResource($user);
    }

    /**
     * Verify user role.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User $user
     * @return \Illuminate\Http\Response
     */
    public function verifyUserRole(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|in:' . $user->roles->pluck('id')->implode(','), 
        ]);

        $accountIsComplete = $user->customer_profile_complete && (! empty($user->prc_registration_number));

        abort_if(! $accountIsComplete, 422, 'Please complete account profile.');
        
        $user->roles()->updateExistingPivot($request->role_id, [
            'verified' => 1, 
            'verified_at' => now()->toDateTimeString(), 
        ]);
        
        return response([
            'message' => 'Verification successful.',
        ]);
    }

    /**
     * Update current auhtenticated account details.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function updateCurrentAccount(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'first_name' => 'required', 
            'last_name' => 'required', 
            'gender' => 'sometimes', 
            'marital_status' => 'sometimes', 
            'location' => 'sometimes', 
            'email' => [
                'required',
                'email',
                Rule::unique('users','email')->ignore($user->id),
            ], 
            'mobile' => [
                'sometimes',
                'regex:/^639\d{9}$/',
                Rule::unique('users','mobile')->ignore($user->id),
            ], 
            'prc_registration_number' => [
                'sometimes',
                'nullable',
                Rule::unique('users','prc_registration_number')->ignore($user->id),
            ],  
        ]);

        $user->update($data);

        return response([
            'message' => 'Account details updated.', 
            'meta' => [
                'user' => $user->load('roles'),
            ], 
        ]);
    }

    /**
     * Update current auhtenticated account photo.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function updateCurrentAccountPhoto(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'photo' => 'required|image|mimes:jpg,png,jpeg,png|max:10000', 
        ]);

        $path = $data['photo']->store('images/users');

        // transform photo to url link
        $data['photo'] = Storage::url($path);

        $user->update($data);

        return response([
            'message' => 'Photo uploaded.', 
            'meta' => [
                'user' => $user->load('roles'),
            ], 
        ]);
    }

    /**
     * Update current auhtenticated account prc photo.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function uploadCurrentAccountPrcId(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'photo' => 'required|file|mimes:jpg,png,jpeg,png,pdf,docx,doc|max:2048', 
        ]);

        $filePath = explode('/', $user->prc_id_link);
        $filename = end($filePath);

        Storage::delete("images/users/$filename");

        $path = $request->photo->store('images/users');

        $user->update([ 'prc_id_link' => Storage::url($path) ]);

        return response([
            'message' => 'PRC photo updated.', 
            'meta' => [
                'user' => $user->load('roles'),
            ], 
        ]);
    }

    /**
     * Remove current auhtenticated account prc photo.
     *
     * @return \Illuminate\Http\Response
     */
    public function removeCurrentAccountPrcId()
    {
        $user = auth()->user();

        if (! empty($user->prc_id_link)) {
            $filePath = explode('/', $user->prc_id_link);
            $filename = end($filePath);

            Storage::delete("images/users/$filename");

            $user->update([ 'prc_id_link' => null ]);
        }

        return response([
            'message' => 'PRC photo removed.', 
            'meta' => [
                'user' => $user->load('roles'),
            ], 
        ]);
    }

    /**
     * Get properties under current authenticated user.
     *
     * @param \Illuminate\Http\Request $request 
     * @return \Illuminate\Http\Response
     */
    public function getProperties(Request $request)
    {
        $properties = auth()
            ->user()
            ->properties()
            ->with([
                'interestedUsers',
                'propertyType',
                'propertyStatus',
                'furnishedType',
                'offerType',
            ])
            ->latest()
            ->paginate($request->per_page ?? 10);

        foreach ($properties->items() as $property) {
            $property->append([
                'is_interested',
                'property_type',
                'property_status',
                'furnished_type',
                'offer_type',
            ]);
        }

        return new DefaultCollection($properties);
    }
}
