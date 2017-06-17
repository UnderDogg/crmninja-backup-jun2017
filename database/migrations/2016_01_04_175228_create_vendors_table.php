<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('relation_id');
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('name')->nullable();
            $table->string('address1');
            $table->string('address2');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->unsignedInteger('country_id')->nullable();
            $table->string('work_phone');
            $table->text('private_notes');
            $table->string('website');
            $table->tinyInteger('is_deleted')->default(0);
            $table->integer('public_id')->default(0);
            $table->string('vat_number')->nullable();
            $table->string('id_number')->nullable();


            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id', 'fk_vendors_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('relation_id', 'fk_vendors_relation')->references('id')->on('relations')->onDelete('cascade');
            $table->foreign('user_id', 'fk_vendors_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('country_id', 'fk_vendors_country')->references('id')->on('countries');
            $table->foreign('currency_id', 'fk_vendors_currency')->references('id')->on('currencies');
        });

        Schema::create('vendor_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('vendor_id')->index();

            $table->boolean('is_primary')->default(0);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->unsignedInteger('public_id')->nullable();
            $table->unique(array('company_id', 'public_id'));

            $table->timestamps();
            $table->softDeletes();


        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('relation_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->decimal('amount', 13, 2);
            $table->decimal('foreign_amount', 13, 2);
            $table->decimal('exchange_rate', 13, 4);
            $table->date('expense_date')->nullable();
            $table->text('private_notes');
            $table->text('public_notes');
            $table->unsignedInteger('invoice_currency_id')->nullable(false);
            $table->boolean('should_be_invoiced')->default(true);

            $table->timestamps();
            $table->softDeletes();


            // Relations
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->unsignedInteger('public_id')->index();
            $table->unique(array('company_id', 'public_id'));
        });

        Schema::table('payment_terms', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('public_id')->index();

            $table->timestamps();
            $table->softDeletes();

            //$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            //$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            //$table->unique(array('company_id', 'public_id'));
        });

        // Update public id
        $paymentTerms = DB::table('payment_terms')
            ->where('public_id', '=', 0)
            ->select('id', 'public_id')
            ->get();
        $i = 1;
        foreach ($paymentTerms as $pTerm) {
            $data = ['public_id' => $i++];
            DB::table('payment_terms')->where('id', $pTerm->id)->update($data);
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('has_expenses')->default(false);
        });

        Schema::table('payment_terms', function (Blueprint $table) {
            $table->unique(array('company_id', 'public_id'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('expenses');
        Schema::drop('vendor_contacts');
        Schema::drop('vendors');
    }
}
