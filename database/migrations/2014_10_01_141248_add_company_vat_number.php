<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCompanyVatNumber extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function ($table) {
            $table->string('vat_number')->nullable();
        });

        Schema::table('relations', function ($table) {
            $table->string('vat_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function ($table) {
            $table->dropColumn('vat_number');
        });

        Schema::table('relations', function ($table) {
            $table->dropColumn('vat_number');
        });
    }

}
