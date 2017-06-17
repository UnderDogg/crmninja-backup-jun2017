<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Corporation;
use App\Models\Company;

class EnterprisePlan extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*$timeout = ini_get('max_execution_time');
        if ($timeout == 0) {
            $timeout = 600;
        }
        $timeout = max($timeout - 10, $timeout * .9);
        $startTime = time();*/

        if (!Schema::hasTable('corporations')) {
            Schema::create('corporations', function ($table) {
                $table->increments('id');

                $table->enum('plan', array('pro', 'enterprise', 'white_label'))->nullable();
                $table->enum('plan_term', array('month', 'year'))->nullable();
                $table->date('plan_started')->nullable();
                $table->date('plan_paid')->nullable();
                $table->date('plan_expires')->nullable();

                $table->unsignedInteger('payment_id')->nullable();
                $table->foreign('payment_id', 'fk_corporation_payment')->references('id')->on('payments');

                $table->date('trial_started')->nullable();
                $table->enum('trial_plan', array('pro', 'enterprise'))->nullable();

                $table->enum('pending_plan', array('pro', 'enterprise', 'free'))->nullable();
                $table->enum('pending_term', array('month', 'year'))->nullable();

                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('companies', 'corporation_id')) {
            Schema::table('companies', function ($table) {
                $table->unsignedInteger('corporation_id')->after('id')->nullable();
            });
            Schema::table('companies', function ($table) {
                $table->foreign('corporation_id')->references('id')->on('corporations')->onDelete('cascade');
            });
        }

        /*$single_company_ids = \DB::table('users')
            ->leftJoin('login_accounts', function ($join) {
                $join->on('user_companies.user_id1', '=', 'users.id');
                $join->orOn('user_companies.user_id2', '=', 'users.id');
                $join->orOn('user_companies.user_id3', '=', 'users.id');
                $join->orOn('user_companies.user_id4', '=', 'users.id');
                $join->orOn('user_companies.user_id5', '=', 'users.id');
            })
            ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
            ->whereNull('user_companies.id')
            ->whereNull('companies.corporation_id')
            ->where(function ($query) {
                $query->whereNull('users.public_id');
                $query->orWhere('users.public_id', '=', 0);
            })
            ->lists('users.company_id');*/

        /*if (count($single_company_ids)) {
            foreach (Company::find($single_company_ids) as $company) {
                $this->upCompanies($company);
                $this->checkTimeout($timeout, $startTime);
            }
        }*/

        /*$group_companies = \DB::select(
            'SELECT u1.company_id as company1, u2.company_id as company2, u3.company_id as company3, u4.company_id as company4, u5.company_id as company5 FROM `user_companies`
            LEFT JOIN users u1 ON (u1.public_id IS NULL OR u1.public_id = 0) AND user_companies.user_id1 = u1.id
            LEFT JOIN users u2 ON (u2.public_id IS NULL OR u2.public_id = 0) AND user_companies.user_id2 = u2.id
            LEFT JOIN users u3 ON (u3.public_id IS NULL OR u3.public_id = 0) AND user_companies.user_id3 = u3.id
            LEFT JOIN users u4 ON (u4.public_id IS NULL OR u4.public_id = 0) AND user_companies.user_id4 = u4.id
            LEFT JOIN users u5 ON (u5.public_id IS NULL OR u5.public_id = 0) AND user_companies.user_id5 = u5.id
            LEFT JOIN companies a1 ON a1.id = u1.company_id
            LEFT JOIN companies a2 ON a2.id = u2.company_id
            LEFT JOIN companies a3 ON a3.id = u3.company_id
            LEFT JOIN companies a4 ON a4.id = u4.company_id
            LEFT JOIN companies a5 ON a5.id = u5.company_id
            WHERE (a1.id IS NOT NULL AND a1.corporation_id IS NULL)
            OR (a2.id IS NOT NULL AND a2.corporation_id IS NULL)
            OR (a3.id IS NOT NULL AND a3.corporation_id IS NULL)
            OR (a4.id IS NOT NULL AND a4.corporation_id IS NULL)
            OR (a5.id IS NOT NULL AND a5.corporation_id IS NULL)');*/

        /*if (count($group_companies)) {
            foreach ($group_companies as $group_company) {
                $this->upCompanies(null, Company::find(get_object_vars($group_company)));
                $this->checkTimeout($timeout, $startTime);
            }
        }*/

        /*if (Schema::hasColumn('companies', 'pro_plan_paid')) {
            Schema::table('companies', function ($table) {
                $table->dropColumn('pro_plan_paid');
                $table->dropColumn('pro_plan_trial');
            });
        }*/
    }

    private function upCompanies($primaryCompany, $otherCompanies = array())
    {
/*        if (!$primaryCompany) {
            $primaryCompany = $otherCompanies->first();
        }

        if (empty($primaryCompany)) {
            return;
        }

        $corporation = Corporation::create();
        if ($primaryCompany->pro_plan_paid && $primaryCompany->pro_plan_paid != '0000-00-00') {
            $corporation->plan = 'pro';
            $corporation->plan_term = 'year';
            $corporation->plan_started = $primaryCompany->pro_plan_paid;
            $corporation->plan_paid = $primaryCompany->pro_plan_paid;

            $expires = DateTime::createFromFormat('Y-m-d', $primaryCompany->pro_plan_paid);
            $expires->modify('+1 year');
            $expires = $expires->format('Y-m-d');

            // check for self host white label licenses
            if (!Utils::isNinjaProd()) {
                if ($corporation->plan_paid) {
                    $corporation->plan = 'white_label';
                    // old ones were unlimited, new ones are yearly
                    if ($corporation->plan_paid == NINJA_DATE) {
                        $corporation->plan_term = null;
                    } else {
                        $corporation->plan_term = PLAN_TERM_YEARLY;
                        $corporation->plan_expires = $expires;
                    }
                }
            } elseif ($corporation->plan_paid != NINJA_DATE) {
                $corporation->plan_expires = $expires;
            }
        }

        if ($primaryCompany->pro_plan_trial && $primaryCompany->pro_plan_trial != '0000-00-00') {
            $corporation->trial_started = $primaryCompany->pro_plan_trial;
            $corporation->trial_plan = 'pro';
        }

        $corporation->save();

        $primaryCompany->corporation_id = $corporation->id;
        $primaryCompany->save();

        if (!empty($otherCompanies)) {
            foreach ($otherCompanies as $company) {
                if ($company && $company->id != $primaryCompany->id) {
                    $company->corporation_id = $corporation->id;
                    $company->save();
                }
            }
        }*/
    }

    protected function checkTimeout($timeout, $startTime)
    {
        if (time() - $startTime >= $timeout) {
            exit('Migration reached time limit; please run again to continue');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
/*        $timeout = ini_get('max_execution_time');
        if ($timeout == 0) {
            $timeout = 600;
        }
        $timeout = max($timeout - 10, $timeout * .9);
        $startTime = time();

        if (!Schema::hasColumn('companies', 'pro_plan_paid')) {
            Schema::table('companies', function ($table) {
                $table->date('pro_plan_paid')->nullable();
                $table->date('pro_plan_trial')->nullable();
            });
        }

        $corporation_ids = \DB::table('corporations')
            ->leftJoin('companies', 'companies.corporation_id', '=', 'corporations.id')
            ->whereNull('companies.pro_plan_paid')
            ->whereNull('companies.pro_plan_trial')
            ->where(function ($query) {
                $query->whereNotNull('corporations.plan_paid');
                $query->orWhereNotNull('corporations.trial_started');
            })
            ->lists('corporations.id');

        $corporation_ids = array_unique($corporation_ids);

        if (count($corporation_ids)) {
            foreach (Corporation::find($corporation_ids) as $corporation) {
                foreach ($corporation->companies as $company) {
                    $company->pro_plan_paid = $corporation->plan_paid;
                    $company->pro_plan_trial = $corporation->trial_started;
                    $company->save();
                }
                $this->checkTimeout($timeout, $startTime);
            }
        }

        if (Schema::hasColumn('companies', 'corporation_id')) {
            Schema::table('companies', function ($table) {
                //$table->dropForeign('companies_corporation_id_foreign');
                $table->dropColumn('corporation_id');
            });
        }*/

        Schema::dropIfExists('corporations');
    }
}
