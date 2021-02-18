<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePropertyHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('property_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('property_id');
            $table->json('details');
            $table->unsignedInteger('property_status_id');
            $table->timestamps();

            $table->foreign('property_id')
                ->references('id')
                ->on('properties');

            $table->foreign('property_status_id')
                ->references('id')
                ->on('property_statuses');
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
        
        Schema::dropIfExists('property_histories');
    }
}
