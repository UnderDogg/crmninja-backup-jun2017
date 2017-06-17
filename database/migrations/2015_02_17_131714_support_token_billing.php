<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SupportTokenBilling extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function ($table) {
            $table->smallInteger('token_billing_type_id')->default(TOKEN_BILLING_ALWAYS);
        });

        Schema::create('account_gateway_tokens', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('account_gateway_id');
            $table->unsignedInteger('relation_id');
            $table->string('token');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id', 'fk_gatewaytoken_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('contact_id', 'fk_gatewaytoken_contact')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('account_gateway_id', 'fk_gatewaytoken_gateway')->references('id')->on('account_gateways')->onDelete('cascade');
            $table->foreign('relation_id', 'fk_gatewaytoken_relation')->references('id')->on('relations')->onDelete('cascade');
        });

        DB::table('companies')->update(['token_billing_type_id' => TOKEN_BILLING_ALWAYS]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function ($table) {
            $table->dropColumn('token_billing_type_id');
        });

        Schema::drop('account_gateway_tokens');
    }

}