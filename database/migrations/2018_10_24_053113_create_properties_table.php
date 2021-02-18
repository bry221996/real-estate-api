<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePropertiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('properties', function (Blueprint $table) {
            $table->increments('id');
            $table->string('listing_id');
            $table->unsignedInteger('property_type_id');
            $table->string('name');
            $table->string('description', 5000)->nullable();
            $table->unsignedDecimal('lot_size', 8, 2)->default(0)->comment('in square meters');
            $table->unsignedDecimal('floor_size', 8, 2)->default(0)->comment('in square meters');
            $table->integer('bathroom_count')->default(0);
            $table->integer('bedroom_count')->default(0);
            $table->integer('garage_count')->default(0);
            $table->string('address');
            $table->string('formatted_address')->nullable();
            $table->string('unit')->nullable();
            $table->string('building_name')->nullable();
            $table->string('street')->nullable();
            $table->string('barangay')->nullable();
            $table->string('city');
            $table->string('zip_code')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('developer')->nullable();
            $table->unsignedInteger('furnished_type_id');
            $table->unsignedInteger('offer_type_id');
            $table->double('price', 19, 2)->unsigned()->nullable();
            $table->double('price_per_sqm', 19, 2)->unsigned()->nullable();
            $table->boolean('occupied')->default(0);
            $table->dateTime('verified_at')->nullable();
            $table->unsignedInteger('verified_by')->nullable();
            $table->unsignedInteger('created_by');
            $table->dateTime('expired_at')->nullable();
            $table->string('comment')->nullable();
            $table->unsignedInteger('property_status_id')->default(2);
            $table->timestamps();

            $table->foreign('property_type_id')
                ->references('id')
                ->on('property_types');

            $table->foreign('furnished_type_id')
                ->references('id')
                ->on('furnished_types');

            $table->foreign('offer_type_id')
                ->references('id')
                ->on('property_offer_types');

            $table->foreign('verified_by')
                ->references('id')
                ->on('users');
            
            $table->foreign('created_by')
                ->references('id')
                ->on('users');
            
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

        Schema::dropIfExists('properties');
    }
}
