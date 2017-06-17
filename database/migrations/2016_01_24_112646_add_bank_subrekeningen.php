<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBankSubRekeningen extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bank_subrekeningen', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('public_id')->index();
            $table->unsignedInteger('bank_company_id');

            $table->string('company_name');
            $table->string('company_number');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id', 'fk_banksubrekening_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id', 'fk_banksubrekening_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bank_company_id', 'fk_banksubrekening_bankrekening')->references('id')->on('bankrekeningen')->onDelete('cascade');

            $table->unique(['company_id', 'public_id']);
        });

        Schema::table('expenses', function ($table) {
            $table->string('transaction_id')->nullable();
            $table->unsignedInteger('bank_id')->nullable();
        });

        /*Schema::table('customers', function ($table) {
            $table->string('transaction_name')->nullable();
        });*/

        Schema::table('vendors', function ($table) {
            $table->string('transaction_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bank_subrekeningen');

        Schema::table('expenses', function ($table) {
            $table->dropColumn('transaction_id');
            $table->dropColumn('bank_id');
        });

        Schema::table('vendors', function ($table) {
            $table->dropColumn('transaction_name');
        });
    }

}
