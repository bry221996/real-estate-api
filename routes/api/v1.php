<?php

use Illuminate\Http\Request;

// for test status of routes...
Route::get('/test', function () {
    return [
        'status' => 'ok', 
        'service' => 'running', 
        'date_time_now' => now()->toDayDateTimeString(), 
    ];
});

Route::middleware('auth.maybe')->group(function () {
    Route::get('/properties', 'PropertyController@index');
    Route::get('/properties/{property}', 'PropertyController@show');
    Route::get('/features', 'FeatureController@index'); 
    Route::get('/developers', 'DeveloperController@index'); 
});

Route::middleware(['auth:api'])->group(function () {
    Route::get('/roles', 'RoleController@index');

    Route::get('/account', 'UserController@getCurrentAuthenticatedUser');
    Route::put('/account', 'UserController@updateCurrentAccount');

    Route::post('/account/photo', 'UserController@updateCurrentAccountPhoto');

    Route::middleware('role:owner,agent')->group(function () {
        Route::get('/account/properties', 'UserPropertyController@index');

        Route::post('/account/prc_id', 'UserController@uploadCurrentAccountPrcId');
        Route::delete('/account/prc_id', 'UserController@removeCurrentAccountPrcId');
    
        Route::get('/account/schedules', 'BusinessAccountScheduleController@index');
        Route::post('/account/schedules', 'BusinessAccountScheduleController@store');
        Route::put('/account/schedules', 'BusinessAccountScheduleController@update');
    
        Route::get('/account/appointments', 'UserAppointmentController@index');
    });

    Route::middleware('role:customer')->group(function () {
        Route::get('/account/interests', 'UserInterestController@index');
        Route::get('/account/bookings', 'CustomerBookingController@index');
    
        Route::post('/properties/{property}/interest', 'UserInterestController@addToInterests');
        Route::delete('/properties/{property}/interest', 'UserInterestController@removeFromInterests');
        Route::post('/properties/{property}/bookings', 'CustomerBookingController@store');
        Route::get('/properties/{property}/booking_status', 'CustomerCurrentBookingController@index');
        Route::get('/properties/{property}/bookings/current', 'CustomerCurrentBookingController@index');
        Route::delete('/properties/{property}/bookings/current', 'CustomerCurrentBookingController@cancel');
        Route::put('/properties/{property}/bookings/current', 'CustomerCurrentBookingController@update');
        Route::post('/properties/{property}/hits', 'PropertyHitsController@store');
    });
});

Route::middleware(['auth:api', 'role:owner,agent,super_admin'])->group(function () {
    Route::post('/properties', 'PropertyController@store');

    Route::middleware(['property.owned'])->group(function () {
        Route::post('/properties/{property}/photos', 'PropertyController@updatePropertyPhotos');
        Route::delete('/properties/{property}/photos', 'PropertyController@removePropertyPhotos');
        Route::post('/properties/{property}/attachments', 'PropertyController@updatePropertyAttachments');
        Route::delete('/properties/{property}/attachments', 'PropertyController@removePropertyAttachments');
        Route::put('/properties/{property}/republish', 'PropertyStatusController@republish');
        Route::put('/properties/{property}/extend', 'PropertyStatusController@extend');
        Route::put('/properties/{property}/sold', 'PropertyStatusController@soldOrOccupied');
        Route::put('/properties/{property}', 'PropertyController@update');
    });

    Route::middleware(['appointment.owned'])->group(function () {
        Route::post('/appointments/{appointment}/confirm', 'AppointmentController@confirm');
        Route::post('/appointments/{appointment}/reject', 'AppointmentController@reject');
    });

    Route::post('/features', 'FeatureController@store');
});

Route::middleware(['auth:api', 'role:super_admin,admin'])->group(function () {
    Route::get('/users', 'UserController@index');
    Route::post('/users', 'UserController@store');

    Route::get('/users/{user}', 'UserController@show');
    Route::post('/users/{user}/verify', 'UserController@verifyUserRole');

    Route::get('/users/{user}/appointments', 'UserAppointmentController@index');

    Route::get('/users/{user}/schedules', 'BusinessAccountScheduleController@index');
    
    Route::put('/properties/{property}/verify', 'PropertyStatusController@verify');
    Route::put('/properties/{property}/reject', 'PropertyStatusController@reject');
    Route::put('/properties/{property}/unpublish', 'PropertyStatusController@unpublish');
    Route::get('/properties/{property}/changes', 'PropertyHistoryController@getChanges');

    Route::get('/appointments', 'AppointmentController@index');

    Route::get('/statistics', 'StatisticController@getSummary');
});
