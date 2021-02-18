<?php

use Illuminate\Http\Request;

Route::get('/test', function () {
    return [
        'status' => 'ok', 
        'service' => 'running', 
        'date_time_now' => now()->toDayDateTimeString(), 
    ];
});

Route::post('/register', 'Auth\AuthController@store');
Route::post('/request_code', 'Auth\AuthController@requestCode');
Route::post('/verify_account', 'Auth\AuthController@verifyAccount');

Route::post('/password_reset/request_code', 'Auth\PasswordResetController@requestCode');
Route::put('/password_reset', 'Auth\PasswordResetController@update');

Route::delete('/logout', 'Auth\AuthController@logout')->middleware('auth:api');