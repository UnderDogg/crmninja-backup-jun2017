<?php

use Illuminate\Database\Migrations\Migration;

class AddCompanyDomain extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function ($table) {
            $table->string('iframe_url')->nullable();
            $table->boolean('military_time')->default(false);
            $table->unsignedInteger('referral_user_id')->nullable();
        });

        Schema::table('relations', function ($table) {
            $table->unsignedInteger('language_id')->nullable();
            $table->foreign('language_id', 'fk_relations_language')->references('id')->on('languages');
        });

        /*Schema::table('customers', function ($table) {
            $table->unsignedInteger('language_id')->nullable();
            $table->foreign('language_id', 'fk_customers_language')->references('id')->on('languages');
        });*/

        Schema::table('invoices', function ($table) {
            $table->boolean('auto_bill')->default(false);
        });

        Schema::table('users', function ($table) {
            $table->string('referral_code')->nullable();
        });

        DB::statement('ALTER TABLE invoices MODIFY COLUMN last_sent_date DATE');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function ($table) {
            $table->dropColumn('iframe_url');
            $table->dropColumn('military_time');
            $table->dropColumn('referral_user_id');
        });

        Schema::table('relations', function ($table) {
            //$table->dropForeign('relations_language_id_foreign');
            $table->dropColumn('language_id');
        });

        Schema::table('invoices', function ($table) {
            $table->dropColumn('auto_bill');
        });

        Schema::table('users', function ($table) {
            $table->dropColumn('referral_code');
        });

        DB::statement('ALTER TABLE invoices MODIFY COLUMN last_sent_date TIMESTAMP');
    }
}
