<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTasks extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('customer_id')->nullable();

            $table->unsignedInteger('public_id')->index();

            $table->unsignedInteger('invoice_id')->nullable();

            $table->timestamp('start_time')->nullable();
            $table->integer('duration')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_deleted')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id', 'fk_tickets_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id', 'fk_tickets_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invoice_id', 'fk_tickets_invoice')->references('id')->on('invoices')->onDelete('cascade');
            //$table->foreign('customer_id', 'fk_tickets_customer')->references('id')->on('customers')->onDelete('cascade');


            $table->unique(array('company_id', 'public_id'));



        });

        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('timesheet_events');
        Schema::dropIfExists('timesheet_event_sources');
        Schema::dropIfExists('project_codes');
        Schema::dropIfExists('projects');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('tasks');
    }

}
