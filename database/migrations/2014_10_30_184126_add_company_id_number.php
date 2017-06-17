<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCompanyIdNumber extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function ($table) {
            $table->string('id_number')->nullable();
        });

        Schema::table('relations', function ($table) {
            $table->string('id_number')->nullable();
        });

        /*Schema::table('customers', function ($table) {
            $table->string('id_number')->nullable();
        });*/

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function ($table) {
            $table->dropColumn('id_number');
        });
        Schema::table('relations', function ($table) {
            $table->dropColumn('id_number');
        });

        /*Schema::table('customers', function ($table) {
            $table->dropColumn('id_number');
        });*/
    }

}
