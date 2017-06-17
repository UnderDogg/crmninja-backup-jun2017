<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInvoiceNumberSettings extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function ($table) {
            $table->string('invoice_number_prefix')->nullable();
            $table->integer('invoice_number_counter')->default(1)->nullable();

            $table->string('quote_number_prefix')->nullable();
            $table->integer('quote_number_counter')->default(1)->nullable();

            $table->boolean('share_counter')->default(true);
        });

        // set initial counter value for companies with invoices
        $companies = DB::table('companies')->lists('id');

        foreach ($companies as $companyId) {

            $invoiceNumbers = DB::table('invoices')->where('company_id', $companyId)->lists('invoice_number');
            $max = 0;

            foreach ($invoiceNumbers as $invoiceNumber) {
                $number = intval(preg_replace('/[^0-9]/', '', $invoiceNumber));
                $max = max($max, $number);
            }

            DB::table('companies')->where('id', $companyId)->update(['invoice_number_counter' => ++$max]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function ($table) {
            $table->dropColumn('invoice_number_prefix');
            $table->dropColumn('invoice_number_counter');

            $table->dropColumn('quote_number_prefix');
            $table->dropColumn('quote_number_counter');

            $table->dropColumn('share_counter');
        });

    }

}
