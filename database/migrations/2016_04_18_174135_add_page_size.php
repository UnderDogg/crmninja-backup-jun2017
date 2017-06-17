<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPageSize extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function ($table) {
            $table->string('page_size')->default('A4');
            $table->boolean('live_preview')->default(true);
            $table->smallInteger('invoice_number_padding')->default(4);
        });

        Schema::table('fonts', function ($table) {
            $table->dropColumn('is_early_access');
        });

        Schema::create('expense_categories', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id')->index();

            $table->string('name')->nullable();

            $table->foreign('company_id', 'fk_expensecats_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id', 'fk_expensecats_user')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(array('company_id', 'public_id'));

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('expenses', function ($table) {
            $table->unsignedInteger('expense_category_id')->nullable()->index();
        });

        Schema::table('expenses', function ($table) {
            $table->foreign('expense_category_id', 'fk_expenses_category')->references('id')->on('expense_categories')->onDelete('cascade');
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
            $table->dropColumn('page_size');
            $table->dropColumn('live_preview');
            $table->dropColumn('invoice_number_padding');
        });

        Schema::table('fonts', function ($table) {
            $table->boolean('is_early_access');
        });

        Schema::table('expenses', function ($table) {
            //$table->dropForeign('expenses_expense_category_id_foreign');
            $table->dropColumn('expense_category_id');
        });

        Schema::dropIfExists('expense_categories');
    }
}
