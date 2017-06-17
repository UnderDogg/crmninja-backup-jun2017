<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('relation_id');
            $table->integer('public_id')->default(0);
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('name')->nullable();
            $table->string('shortname')->nullable();
            $table->string('address1');
            $table->string('address2');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->unsignedInteger('country_id')->nullable();
            $table->unsignedInteger('language_id')->nullable();
            $table->string('work_phone');

            $table->text('private_notes');
            $table->string('website');
            $table->tinyInteger('is_deleted')->default(0);

            $table->string('vat_number')->nullable();
            $table->string('id_number')->nullable();



            $table->decimal('balance', 13, 2)->nullable();
            $table->decimal('paid_to_date', 13, 2)->nullable();


            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id', 'fk_customers_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id', 'fk_customers_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('country_id', 'fk_customers_country')->references('id')->on('countries');
            $table->foreign('currency_id', 'fk_customers_currency')->references('id')->on('currencies');

            $table->foreign('language_id', 'fk_customers_language')->references('id')->on('languages');

        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('public_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('customer_id')->index();

            $table->boolean('is_primary')->default(0);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            //$table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');


            $table->unique(array('company_id', 'public_id'));

            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('customer_contacts');
        Schema::drop('customers');
    }
}
