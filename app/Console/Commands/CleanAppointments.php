<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Appointment;
use DB;

class CleanAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:clean-all-previous-appointment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean all previous appointments until present time';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            DB::beginTransaction();

            Appointment::confirmed()
                ->isPast()
                ->update([ 'status_id' => 6 ]);

            Appointment::where(function ($query) {
                    $query->orWhere(function ($query) {
                        $query->pending();
                    });

                    $query->orWhere(function ($query) {
                        $query->reschedule();
                    });
                })
                ->isPast()
                ->update([ 'status_id' => 7 ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            logger()->error('Clean appointments task failed.');
        }
    }
}
