<?php
use Illuminate\Database\Migrations\Migration;

class ConfideSetupUsersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('credits');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('relations');
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('invoice_statuses');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('timezones');
        Schema::dropIfExists('frequencies');
        Schema::dropIfExists('date_formats');
        Schema::dropIfExists('datetime_formats');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('payment_types');

        Schema::create('countries', function ($table) {
            $table->increments('id');
            $table->string('capital', 255)->nullable();
            $table->string('citizenship', 255)->nullable();
            $table->string('country_code', 3)->default('');
            $table->string('currency', 255)->nullable();
            $table->string('currency_code', 255)->nullable();
            $table->string('currency_sub_unit', 255)->nullable();
            $table->string('full_name', 255)->nullable();
            $table->string('iso_3166_2', 2)->default('');
            $table->string('iso_3166_3', 3)->default('');
            $table->string('name', 255)->default('');
            $table->string('region_code', 3)->default('');
            $table->string('sub_region_code', 3)->default('');
            $table->boolean('eea')->default(0);
        });

        Schema::create('themes', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('payment_types', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('payment_terms', function ($t) {
            $t->increments('id');
            $t->integer('num_days');
            $t->string('name');
        });

        Schema::create('timezones', function ($t) {
            $t->increments('id');
            $t->string('name');
            $t->string('location');
        });

        Schema::create('date_formats', function ($t) {
            $t->increments('id');
            $t->string('format');
            $t->string('picker_format');
            $t->string('label');
        });

        Schema::create('datetime_formats', function ($t) {
            $t->increments('id');
            $t->string('format');
            $t->string('label');
        });

        Schema::create('currencies', function ($t) {
            $t->increments('id');

            $t->string('name');
            $t->string('symbol');
            $t->string('precision');
            $t->string('thousand_separator');
            $t->string('decimal_separator');
            $t->string('code');
        });

        Schema::create('sizes', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('industries', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('companies', function ($t) {
            $t->increments('id');

            $t->string('company_key')->unique();

            $t->string('name')->nullable();



            $t->string('ip');
            $t->timestamp('last_login')->nullable();

            $t->string('address1')->nullable();
            $t->string('address2')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postal_code')->nullable();
            $t->unsignedInteger('country_id')->nullable();
            $t->text('invoice_terms')->nullable();
            $t->text('email_footer')->nullable();



            $t->unsignedInteger('industry_id')->nullable();
            $t->unsignedInteger('size_id')->nullable();
            $t->unsignedInteger('timezone_id')->nullable();
            $t->unsignedInteger('date_format_id')->nullable();
            $t->unsignedInteger('datetime_format_id')->nullable();
            $t->unsignedInteger('currency_id')->nullable();



            $t->boolean('invoice_taxes')->default(true);
            $t->boolean('invoice_item_taxes')->default(false);

            $t->timestamps();
            $t->softDeletes();

            $t->foreign('timezone_id', 'fk_company_timezone')->references('id')->on('timezones');
            $t->foreign('date_format_id', 'fk_company_dateformat')->references('id')->on('date_formats');
            $t->foreign('datetime_format_id', 'fk_company_datetime')->references('id')->on('datetime_formats');
            $t->foreign('country_id', 'fk_company_country')->references('id')->on('countries');
            $t->foreign('currency_id', 'fk_company_currency')->references('id')->on('currencies');
            $t->foreign('industry_id', 'fk_company_industry')->references('id')->on('industries');
            $t->foreign('size_id', 'fk_company_companysize')->references('id')->on('sizes');

        });


        Schema::create('useraccounts', function ($t) {
            $t->increments('id');

            $t->unsignedInteger('company_id');

            $t->string('name')->nullable();
            $t->string('ip');
            $t->string('company_key')->unique();
            $t->timestamp('last_login')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->foreign('company_id', 'fk_useraccount_company')->references('id')->on('companies');
        });


        Schema::create('gateways', function ($t) {
            $t->increments('id');


            $t->string('name');
            $t->string('provider');
            $t->boolean('visible')->default(true);

            $t->timestamps();
        });

        Schema::create('users', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();


            $t->unsignedInteger('public_id')->nullable();


            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('phone')->nullable();
            $t->string('username')->unique();
            $t->string('email')->nullable();
            $t->string('password');
            $t->string('confirmation_code')->nullable();
            $t->boolean('registered')->default(false);
            $t->boolean('confirmed')->default(false);
            $t->integer('theme_id')->nullable();

            $t->boolean('notify_sent')->default(true);
            $t->boolean('notify_viewed')->default(false);
            $t->boolean('notify_paid')->default(true);

            $t->timestamps();
            $t->softDeletes();

            $t->foreign('company_id', 'fk_users_company')->references('id')->on('companies')->onDelete('cascade');

            $t->unique(array('company_id', 'public_id'));
        });

        Schema::create('employees', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();

            $t->unsignedInteger('public_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('phone')->nullable();
            $t->string('username')->unique();
            $t->string('email')->nullable();
            $t->string('password');
            $t->string('confirmation_code')->nullable();
            $t->boolean('registered')->default(false);
            $t->boolean('confirmed')->default(false);
            $t->integer('theme_id')->nullable();

            $t->boolean('notify_sent')->default(true);
            $t->boolean('notify_viewed')->default(false);
            $t->boolean('notify_paid')->default(true);

            $t->foreign('company_id', 'fk_employees_company')->references('id')->on('companies')->onDelete('cascade');

            $t->timestamps();
            $t->softDeletes();
            $t->unique(array('company_id', 'public_id'));
        });


        Schema::create('account_gateways', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('public_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('gateway_id');
            $t->text('config');
            $t->timestamps();
            $t->softDeletes();
            $t->foreign('company_id', 'fk_accgateway_company')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('gateway_id', 'fk_accgateway_gateway')->references('id')->on('gateways');
            $t->foreign('user_id', 'fk_accgateway_users')->references('id')->on('users')->onDelete('cascade');
            $t->unique(array('company_id', 'public_id'));
        });


        Schema::create('password_reminders', function ($t) {
            $t->string('email');


            $t->string('token');

            $t->timestamps();

        });

        Schema::create('relations', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('currency_id')->nullable();

            $t->unsignedInteger('public_id')->index();
            $t->string('name')->nullable();
            $t->string('shortname')->nullable();
            $t->string('address1')->nullable();
            $t->string('address2')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postal_code')->nullable();
            $t->unsignedInteger('country_id')->nullable();
            $t->string('work_phone')->nullable();
            $t->text('private_notes')->nullable();
            $t->timestamp('last_login')->nullable();
            $t->string('website')->nullable();
            $t->unsignedInteger('industry_id')->nullable();
            $t->unsignedInteger('size_id')->nullable();
            $t->boolean('is_deleted')->default(false);
            $t->integer('payment_terms')->nullable();


            $t->timestamps();
            $t->softDeletes();


            $t->foreign('company_id', 'fk_relations_company')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('user_id', 'fk_relations_user')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('country_id', 'fk_relations_country')->references('id')->on('countries');
            $t->foreign('industry_id', 'fk_relations_industry')->references('id')->on('industries');
            $t->foreign('size_id', 'fk_relations_companiesize')->references('id')->on('sizes');
            $t->foreign('currency_id', 'fk_relations_currency')->references('id')->on('currencies');

            $t->unique(array('company_id', 'public_id'));
        });

        Schema::create('contacts', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('relation_id')->index();

            $t->unsignedInteger('public_id')->nullable();

            $t->boolean('is_primary')->default(0);
            $t->boolean('send_invoice')->default(0);
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->timestamp('last_login')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->foreign('relation_id', 'fk_conacts_relation')->references('id')->on('relations')->onDelete('cascade');
            $t->foreign('user_id', 'fk_contacts_user')->references('id')->on('users')->onDelete('cascade');;

            $t->unique(array('company_id', 'public_id'));
        });

        Schema::create('invoice_statuses', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('frequencies', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('invoices', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('customer_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('public_id')->index();
            $t->unsignedInteger('invoice_status_id')->default(1);

            $t->string('invoice_number');
            $t->float('discount');
            $t->string('po_number');
            $t->date('invoice_date')->nullable();
            $t->date('due_date')->nullable();
            $t->text('terms');
            $t->text('public_notes');
            $t->boolean('is_deleted')->default(false);
            $t->boolean('is_recurring')->default(false);
            $t->unsignedInteger('frequency_id');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamp('last_sent_date')->nullable();
            $t->unsignedInteger('recurring_invoice_id')->index()->nullable();

            $t->string('tax_name1');
            $t->decimal('tax_rate1', 13, 3);

            $t->decimal('amount', 13, 2);
            $t->decimal('balance', 13, 2);

            $t->timestamps();
            $t->softDeletes();

            //$t->foreign('customer_id', 'fk_invoice_customer')->references('id')->on('customers')->onDelete('cascade');
            $t->foreign('company_id', 'fk_invoice_company')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('user_id', 'fk_invoice_user')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('invoice_status_id', 'fk_invoice_status')->references('id')->on('invoice_statuses');
            $t->foreign('recurring_invoice_id', 'fk_invoice_recurring')->references('id')->on('invoices')->onDelete('cascade');


            $t->unique(array('company_id', 'public_id'));
            $t->unique(array('company_id', 'invoice_number'));
        });


        Schema::create('invitations', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('user_id');

            $t->unsignedInteger('public_id')->index();

            $t->unsignedInteger('contact_id');
            $t->unsignedInteger('invoice_id')->index();
            $t->string('invitation_key')->index()->unique();


            $t->string('transaction_reference')->nullable();
            $t->timestamp('sent_date')->nullable();
            $t->timestamp('viewed_date')->nullable();

            $t->timestamps();
            $t->softDeletes();


            $t->foreign('user_id', 'fk_invitation_user')->references('id')->on('users')->onDelete('cascade');;
            $t->foreign('contact_id', 'fk_invitation_contact')->references('id')->on('contacts')->onDelete('cascade');
            $t->foreign('invoice_id', 'fk_invitation_invoice')->references('id')->on('invoices')->onDelete('cascade');


            $t->unique(array('company_id', 'public_id'));
        });

        Schema::create('tax_rates', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('user_id');

            $t->unsignedInteger('public_id');

            $t->string('name');
            $t->decimal('rate', 13, 3);


            $t->timestamps();
            $t->softDeletes();



            $t->foreign('company_id', 'fk_taxrate_company')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('user_id', 'fk_taxrate_user')->references('id')->on('users')->onDelete('cascade');;


            $t->unique(array('company_id', 'public_id'));
        });

        Schema::create('products', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('user_id');

            $t->unsignedInteger('public_id');



            $t->string('product_key');
            $t->text('notes');
            $t->decimal('cost', 13, 2);
            $t->decimal('qty', 13, 2)->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->foreign('company_id', 'fk_product_company')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('user_id', 'fk_product_user')->references('id')->on('users')->onDelete('cascade');;


            $t->unique(array('company_id', 'public_id'));
        });


        Schema::create('invoice_items', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('invoice_id')->index();

            $t->unsignedInteger('public_id');

            $t->unsignedInteger('product_id')->nullable();


            $t->string('product_key');
            $t->text('notes');
            $t->decimal('cost', 13, 2);
            $t->decimal('qty', 13, 2)->nullable();

            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 13, 3)->nullable();

            $t->timestamps();
            $t->softDeletes();


            $t->foreign('invoice_id', 'fk_invoiceitem_invoice')->references('id')->on('invoices')->onDelete('cascade');
            $t->foreign('product_id', 'fk_invoiceitem_product')->references('id')->on('products')->onDelete('cascade');
            $t->foreign('user_id', 'fk_invoiceitem_user')->references('id')->on('users')->onDelete('cascade');;


            $t->unique(array('company_id', 'public_id'));
        });

        Schema::create('payments', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('invoice_id')->index();
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('customer_id')->index();

            $t->unsignedInteger('public_id')->index();

            $t->unsignedInteger('contact_id')->nullable();
            $t->unsignedInteger('invitation_id')->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('account_gateway_id')->nullable();
            $t->unsignedInteger('payment_type_id')->nullable();

            $t->decimal('amount', 13, 2);
            $t->date('payment_date')->nullable();
            $t->string('transaction_reference')->nullable();
            $t->string('payer_id')->nullable();

            $t->boolean('is_deleted')->default(false);

            $t->timestamps();
            $t->softDeletes();


            $t->foreign('invoice_id', 'fk_payment_invoice')->references('id')->on('invoices')->onDelete('cascade');
            $t->foreign('company_id', 'fk_payment_company')->references('id')->on('companies')->onDelete('cascade');
            //$t->foreign('customer_id', 'fk_payment_customer')->references('id')->on('customers')->onDelete('cascade');
            $t->foreign('contact_id', 'fk_payment_contact')->references('id')->on('contacts')->onDelete('cascade');
            $t->foreign('account_gateway_id', 'fk_payment_gateway')->references('id')->on('account_gateways')->onDelete('cascade');
            $t->foreign('user_id', 'fk_payment_user')->references('id')->on('users')->onDelete('cascade');;
            $t->foreign('payment_type_id', 'fk_payment_paymtype')->references('id')->on('payment_types');


            $t->unique(array('company_id', 'public_id'));
        });

        Schema::create('credits', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('customer_id')->index();

            $t->unsignedInteger('public_id')->index();

            $t->unsignedInteger('user_id');

            $t->decimal('amount', 13, 2);
            $t->decimal('balance', 13, 2);
            $t->date('credit_date')->nullable();
            $t->string('credit_number')->nullable();
            $t->text('private_notes');

            $t->boolean('is_deleted')->default(false);
            $t->timestamps();
            $t->softDeletes();


            $t->foreign('company_id', 'fk_credit_company')->references('id')->on('companies')->onDelete('cascade');
            //$t->foreign('customer_id', 'fk_credit_customer')->references('id')->on('customers')->onDelete('cascade');
            $t->foreign('user_id', 'fk_credit_user')->references('id')->on('users')->onDelete('cascade');;


            $t->unique(array('company_id', 'public_id'));
        });

        Schema::create('activities', function ($t) {
            $t->increments('id');

            $t->unsignedInteger('company_id');
            $t->unsignedInteger('relation_id');
            $t->unsignedInteger('customer_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('contact_id')->nullable();
            $t->unsignedInteger('payment_id')->nullable();
            $t->unsignedInteger('invoice_id')->nullable();
            $t->unsignedInteger('credit_id')->nullable();
            $t->unsignedInteger('invitation_id')->nullable();

            $t->text('message')->nullable();
            $t->text('json_backup')->nullable();
            $t->integer('activity_type_id');
            $t->decimal('adjustment', 13, 2)->nullable();
            $t->decimal('balance', 13, 2)->nullable();

            $t->timestamps();

            $t->foreign('company_id', 'fk_activity_company')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('relation_id', 'fk_activity_relation')->references('id')->on('relations')->onDelete('cascade');
            //$t->foreign('customer_id', 'fk_activity_customer')->references('id')->on('customers')->onDelete('cascade');
            //$t->foreign('prospect_id', 'fk_activity_prospect')->references('id')->on('prospects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('credits');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('relations');
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('invoice_statuses');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('timezones');
        Schema::dropIfExists('frequencies');
        Schema::dropIfExists('date_formats');
        Schema::dropIfExists('datetime_formats');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('payment_types');
    }
}
