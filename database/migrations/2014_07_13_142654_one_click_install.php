<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class OneClickInstall extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('affiliates', function ($table) {
            $table->increments('id');

            $table->string('name');
            $table->string('affiliate_key')->unique();

            $table->text('payment_title');
            $table->text('payment_subtitle');

            $table->timestamps();
            $table->softDeletes();

        });

        Schema::create('licenses', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('affiliate_id');

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');

            $table->string('license_key')->unique();
            $table->boolean('is_claimed');
            $table->string('transaction_reference');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('affiliate_id', 'fk_licenses_affiliate')->references('id')->on('affiliates');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('affiliates');
    }

}
