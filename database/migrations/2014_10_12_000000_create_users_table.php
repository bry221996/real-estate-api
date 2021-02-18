<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('photo')
                ->default('https://s3-ap-southeast-1.amazonaws.com/lazatu/resources/images/default-profile-image.png');
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('location')->nullable();
            $table->string('username')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('mobile')->unique()->index();
            $table->string('password')->nullable();
            $table->double('points')->default(0);
            $table->string('prc_registration_number')->unique()->nullable();
            $table->string('prc_id_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
