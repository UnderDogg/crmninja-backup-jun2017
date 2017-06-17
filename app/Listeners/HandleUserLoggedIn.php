<?php namespace App\Listeners;

use Auth;
use Carbon;
use Session;
use App\Events\UserLoggedIn;
use App\Events\UserSignedUp;
use App\Models\Company;
use App\Ninja\Repositories\CompanyRepository;

/**
 * Class HandleUserLoggedIn
 */
class HandleUserLoggedIn
{

    /**
     * @var CompanyRepository
     */
    protected $companyRepo;

    /**
     * Create the event handler.
     *
     * @param CompanyRepository $companyRepo
     */
    public function __construct(CompanyRepository $companyRepo)
    {
        $this->companyRepo = $companyRepo;
    }

    /**
     * Handle the event.
     *
     * @param  UserLoggedIn $event
     *
     * @return void
     */
    public function handle(UserLoggedIn $event)
    {

        $company = Auth::user()->loginaccount;

        if (empty($company->last_login)) {
            event(new UserSignedUp());
        }

        $company->last_login = Carbon::now()->toDateTimeString();
        $company->save();

        $users = $this->companyRepo->loadCompanies(Auth::user()->id);
        Session::put(SESSION_USER_COMPANIES, $users);

        $company->loadLocalizationSettings();

        // if they're using Stripe make sure they're using Stripe.js 
        $accountGateway = $company->getGatewayConfig(GATEWAY_STRIPE);
        if ($accountGateway && !$accountGateway->getPublishableStripeKey()) {
            Session::flash('warning', trans('texts.missing_publishable_key'));
        } elseif ($company->isLogoTooLarge()) {
            Session::flash('warning', trans('texts.logo_too_large', ['size' => $company->getLogoSize() . 'KB']));
        }
    }
}
