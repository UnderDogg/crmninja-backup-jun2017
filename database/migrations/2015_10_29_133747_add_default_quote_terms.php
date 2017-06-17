<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDefaultQuoteTerms extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function ($table) {
            $table->text('quote_terms')->nullable();
        });

        $companies = DB::table('companies')
            ->orderBy('id')
            ->get(['id', 'invoice_terms']);

        foreach ($companies as $company) {
            DB::table('companies')
                ->where('id', $company->id)
                ->update(['quote_terms' => $company->invoice_terms]);
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
            $table->dropColumn('quote_terms');
        });
    }

}
