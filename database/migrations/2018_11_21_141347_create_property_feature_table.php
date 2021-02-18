<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePropertyFeatureTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('property_feature', function (Blueprint $table) {
            $table->unsignedInteger('property_id');
            $table->unsignedInteger('feature_id');

            $table->foreign('property_id')
                ->references('id')
                ->on('properties');

            $table->foreign('feature_id')
                ->references('id')
                ->on('features');
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

        Schema::dropIfExists('property_features');
    }
}
