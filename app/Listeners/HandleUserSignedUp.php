<?php namespace App\Listeners;

use Utils;
use Auth;
use App\Events\UserSignedUp;
use App\Ninja\Repositories\CompanyRepository;
use App\Ninja\Mailers\UserMailer;

/**
 * Class HandleUserSignedUp
 */
class HandleUserSignedUp
{
    /**
     * @var CompanyRepository
     */
    protected $companyRepo;

    /**
     * @var UserMailer
     */
    protected $userMailer;

    /**
     * Create the event handler.
     *
     * @param CompanyRepository $companyRepo
     * @param UserMailer $userMailer
     */
    public function __construct(CompanyRepository $companyRepo, UserMailer $userMailer)
    {
        $this->companyRepo = $companyRepo;
        $this->userMailer = $userMailer;
    }

    /**
     * Handle the event.
     *
     * @param  UserSignedUp $event
     *
     * @return void
     */
    public function handle(UserSignedUp $event)
    {
        $user = Auth::user();

        if (Utils::isNinjaProd()) {
            $this->userMailer->sendConfirmation($user);
        } elseif (Utils::isNinjaDev()) {
            // do nothing
        } else {
            $this->companyRepo->registerNinjaUser($user);
        }

        session([SESSION_COUNTER => -1]);
    }
}
