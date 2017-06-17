<?php namespace App\Ninja\Repositories;

use App\Models\Company;

class ReferralRepository
{
    public function getCounts($userId)
    {
        $companies = Company::where('referral_user_id', $userId)->get();

        $counts = [
            'free' => 0,
            'pro' => 0,
            'enterprise' => 0
        ];

        foreach ($companies as $company) {
            $counts['free']++;
            $plan = $company->getPlanDetails(false, false);

            if ($plan) {
                $counts['pro']++;
                if ($plan['plan'] == PLAN_ENTERPRISE) {
                    $counts['enterprise']++;
                }
            }
        }

        return $counts;
    }
}
