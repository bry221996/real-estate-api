<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppointmentHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('appointment_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('appointment_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('property_id');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('status_id');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->foreign('appointment_id')
                ->references('id')
                ->on('appointments');

            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->foreign('property_id')
                ->references('id')
                ->on('properties');

            $table->foreign('status_id')
                ->references('id')
                ->on('appointment_statuses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        
        Schema::dropIfExists('appointment_histories');
    }
}
