<?php namespace App\Listeners;


use Auth;
use Session;
use App\Events\UserSettingsChanged;
use App\Ninja\Repositories\CompanyRepository;
use App\Ninja\Repositories\UserAccountRepository;
use App\Ninja\Mailers\UserMailer;

/**
 * Class HandleUserSettingsChanged
 */
class HandleUserSettingsChanged
{

    /**
     * Create the event handler.
     *
     * @param CompanyRepository $companyRepo
     * @param UserMailer $userMailer
     */
    public function __construct(CompanyRepository $companyRepo, UserAccountRepository $userAccountRepo, UserMailer $userMailer)
    {
        $this->companyRepo = $companyRepo;
        $this->userAccountRepo = $userAccountRepo;
        $this->userMailer = $userMailer;
    }

    /**
     * Handle the event.
     *
     * @param  UserSettingsChanged $event
     *
     * @return void
     */
    public function handle(UserSettingsChanged $event)
    {
        if (!Auth::check()) {
            return;
        }

        $company = Auth::user()->loginaccount;
        $company->loadLocalizationSettings();

        $companies_for_user = $this->userAccountRepo->loadCompanies(Auth::user()->id);
        Session::put(SESSION_USER_COMPANIES, $companies_for_user);

        if ($event->user && $event->user->isEmailBeingChanged()) {
            $this->userMailer->sendConfirmation($event->user);
            Session::flash('warning', trans('texts.verify_email'));
        }
    }
}
