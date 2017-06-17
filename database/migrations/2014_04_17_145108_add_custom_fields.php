<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomFields extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function ($table) {
            $table->string('custom_label1')->nullable();
            $table->string('custom_value1')->nullable();

            $table->string('custom_label2')->nullable();
            $table->string('custom_value2')->nullable();

            $table->string('custom_relation_label1')->nullable();
            $table->string('custom_relation_label2')->nullable();
        });

        Schema::table('relations', function ($table) {
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
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
            $table->dropColumn('custom_label1');
            $table->dropColumn('custom_value1');

            $table->dropColumn('custom_label2');
            $table->dropColumn('custom_value2');

            $table->dropColumn('custom_relation_label1');
            $table->dropColumn('custom_relation_label2');
        });

        Schema::table('relations', function ($table) {
            $table->dropColumn('custom_value1');
            $table->dropColumn('custom_value2');
        });
    }

}
