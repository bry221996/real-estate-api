<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Semaphore;
use App\Appointment;
use App\Observers\AppointmentObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Appointment::observe(AppointmentObserver::class);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(Semaphore::class, function () {
            return new Semaphore(config('services.semaphore.key'), config('services.semaphore.sender_name'));
        }); 

    }
}
