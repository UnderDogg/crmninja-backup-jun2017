<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBankRekeningen extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banks', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('remote_id');
            $table->integer('bank_library_id')->default(BANK_LIBRARY_OFX);
            $table->text('config');
        });

        Schema::create('bankrekeningen', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('public_id')->index();
            $table->unsignedInteger('bank_id');
            $table->unsignedInteger('user_id');
            $table->string('username');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id', 'fk_bankrekening_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id', 'fk_bankrekening_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bank_id', 'fk_bankrekening_bank')->references('id')->on('banks');


            $table->unique(['company_id', 'public_id']);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bankrekeningen');
        Schema::drop('banks');
    }

}
