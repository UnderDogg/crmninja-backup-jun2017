<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddZapierSupport extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriptions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable();

            $table->unsignedInteger('event_id')->nullable();
            $table->string('target_url');

            $table->timestamps();
            $table->softDeletes();


            $table->foreign('company_id', 'fk_subscriptions_company')->references('id')->on('companies')->onDelete('cascade');


        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }

}
