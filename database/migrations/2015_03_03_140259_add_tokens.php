<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTokens extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('useraccount_tokens', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('user_id');

            $table->string('name')->nullable();
            $table->string('token')->unique();


            $table->unsignedInteger('public_id')->nullable();
            $table->unique(['company_id', 'public_id']);


            $table->foreign('company_id', 'fk_token_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id', 'fk_token_user')->references('id')->on('users')->onDelete('cascade');


            $table->timestamps();
            $table->softDeletes();

        });

        Schema::table('activities', function ($table) {
            $table->unsignedInteger('token_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('useraccount_tokens');

        Schema::table('activities', function ($table) {
            $table->dropColumn('token_id');
        });
    }

}
