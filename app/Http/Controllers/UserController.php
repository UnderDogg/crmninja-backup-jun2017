<?php namespace App\Http\Controllers;

use Auth;
use Input;
use View;
use Request;
use Redirect;
use Session;
use URL;
use Password;
use Utils;
use Validator;
use App\Models\User;
use App\Ninja\Repositories\CompanyRepository;
use App\Ninja\Mailers\ContactMailer;
use App\Ninja\Mailers\UserMailer;
use App\Services\UserService;

class UserController extends BaseController
{
    protected $companyRepo;
    protected $contactMailer;
    protected $userMailer;
    protected $userService;

    public function __construct(CompanyRepository $companyRepo, ContactMailer $contactMailer, UserMailer $userMailer, UserService $userService)
    {
        //parent::__construct();

        $this->companyRepo = $companyRepo;
        $this->contactMailer = $contactMailer;
        $this->userMailer = $userMailer;
        $this->userService = $userService;
    }

    public function index()
    {
        return Redirect::to('settings/' . COMPANY_USER_MANAGEMENT);
    }

    public function getDatatable()
    {
        return $this->userService->getDatatable(Auth::user()->company_id);
    }

    public function setTheme()
    {
        $user = User::find(Auth::user()->id);
        $user->theme_id = Input::get('theme_id');
        $user->save();

        return Redirect::to(Input::get('path'));
    }

    public function forcePDFJS()
    {
        $user = Auth::user();
        $user->force_pdfjs = true;
        $user->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('/dashboard');
    }

    public function edit($publicId)
    {
        $user = User::where('company_id', '=', Auth::user()->company_id)
                        ->where('public_id', '=', $publicId)->firstOrFail();

        $data = [
            'user' => $user,
            'method' => 'PUT',
            'url' => 'users/'.$publicId,
        ];

        return View::make('users.edit', $data);
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    public function store()
    {
        return $this->save();
    }

    /**
     * Displays the form for loginaccount creation
     *
     */
    public function create()
    {
        if ( ! Auth::user()->registered) {
            Session::flash('error', trans('texts.register_to_add_user'));
            return Redirect::to('settings/' . COMPANY_USER_MANAGEMENT);
        }

        if ( ! Auth::user()->confirmed) {
            Session::flash('error', trans('texts.confirmation_required'));
            return Redirect::to('settings/' . COMPANY_USER_MANAGEMENT);
        }

        if (Utils::isNinja() && ! Auth::user()->caddAddUsers()) {
            Session::flash('error', trans('texts.max_users_reached'));
            return Redirect::to('settings/' . COMPANY_USER_MANAGEMENT);
        }

        $data = [
          'user' => null,
          'method' => 'POST',
          'url' => 'users',
        ];

        return View::make('users.edit', $data);
    }

    public function bulk()
    {
        $action = Input::get('bulk_action');
        $id = Input::get('bulk_public_id');

        $user = User::where('company_id', '=', Auth::user()->company_id)
                    ->where('public_id', '=', $id)
                    ->withTrashed()
                    ->firstOrFail();

        if ($action === 'archive') {
            $user->delete();
        } else {
            if ( ! Auth::user()->caddAddUsers()) {
                return Redirect::to('settings/' . COMPANY_USER_MANAGEMENT)
                    ->with('error', trans('texts.max_users_reached'));
            }

            $user->restore();
        }

        Session::flash('message', trans("texts.{$action}d_user"));

        return Redirect::to('settings/' . COMPANY_USER_MANAGEMENT);
    }

    /**
     * Stores new loginaccount
     *
     */
    public function save($userPublicId = false)
    {
        if (Auth::user()->hasFeature(FEATURE_USERS)) {
            $rules = [
                'first_name' => 'required',
                'last_name' => 'required',
            ];

            if ($userPublicId) {
                $user = User::where('company_id', '=', Auth::user()->company_id)
                            ->where('public_id', '=', $userPublicId)->firstOrFail();

                $rules['email'] = 'required|email|unique:users,email,'.$user->id.',id';
            } else {
                $rules['email'] = 'required|email|unique:users';
            }

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to($userPublicId ? 'users/edit' : 'users/create')->withInput()->withErrors($validator);
            }

            if ($userPublicId) {
                $user->first_name = trim(Input::get('first_name'));
                $user->last_name = trim(Input::get('last_name'));
                $user->username = trim(Input::get('email'));
                $user->email = trim(Input::get('email'));
                if (Auth::user()->hasFeature(FEATURE_USER_PERMISSIONS)) {
                    $user->is_admin = boolval(Input::get('is_admin'));
                    $user->permissions = Input::get('permissions');
                }
            } else {
                $lastUser = User::withTrashed()->where('company_id', '=', Auth::user()->company_id)
                            ->orderBy('public_id', 'DESC')->first();

                $user = new User();
                $user->company_id = Auth::user()->company_id;
                $user->first_name = trim(Input::get('first_name'));
                $user->last_name = trim(Input::get('last_name'));
                $user->username = trim(Input::get('email'));
                $user->email = trim(Input::get('email'));
                $user->registered = true;
                $user->password = str_random(RANDOM_KEY_LENGTH);
                $user->confirmation_code = str_random(RANDOM_KEY_LENGTH);
                $user->public_id = $lastUser->public_id + 1;
                if (Auth::user()->hasFeature(FEATURE_USER_PERMISSIONS)) {
                    $user->is_admin = boolval(Input::get('is_admin'));
                    $user->permissions = Input::get('permissions');
                }
            }

            $user->save();

            if (!$user->confirmed) {
                $this->userMailer->sendConfirmation($user, Auth::user());
                $message = trans('texts.sent_invite');
            } else {
                $message = trans('texts.updated_user');
            }

            Session::flash('message', $message);
        }

        return Redirect::to('settings/' . COMPANY_USER_MANAGEMENT);
    }

    public function sendConfirmation($userPublicId)
    {
        $user = User::where('company_id', '=', Auth::user()->company_id)
                    ->where('public_id', '=', $userPublicId)->firstOrFail();

        $this->userMailer->sendConfirmation($user, Auth::user());
        Session::flash('message', trans('texts.sent_invite'));

        return Redirect::to('settings/' . COMPANY_USER_MANAGEMENT);
    }


    /**
     * Attempt to confirm loginaccount with code
     *
     * @param string $code
     */
    public function confirm($code)
    {
        $user = User::where('confirmation_code', '=', $code)->get()->first();

        if ($user) {
            $notice_msg = trans('texts.security.confirmation');

            $user->confirmed = true;
            $user->confirmation_code = '';
            $user->save();

            if ($user->public_id) {
                Auth::logout();
                $token = Password::getRepository()->create($user);
                return Redirect::to("/password/reset/{$token}");
            } else {
                if (Auth::check()) {
                    if (Session::has(REQUESTED_PRO_PLAN)) {
                        Session::forget(REQUESTED_PRO_PLAN);
                        $url = '/settings/company_management?upgrade=true';
                    } else {
                        $url = '/dashboard';
                    }
                } else {
                    $url = '/login';
                }
                return Redirect::to($url)->with('message', $notice_msg);
            }
        } else {
            $error_msg = trans('texts.security.wrong_confirmation');

            return Redirect::to('/login')->with('error', $error_msg);
        }
    }

    /**
     * Log the user out of the application.
     *
     */
    /*
    public function logout()
    {
        if (Auth::check()) {
            if (!Auth::user()->registered) {
                $company = Auth::user()->loginaccount;
                $this->companyRepo->unlinkCompany($company);
                if ($company->corporation->companies->count() == 1) {
                    $company->corporation->forceDelete();
                }
                $company->forceDelete();
            }
        }

        Auth::logout();
        Session::flush();

        return Redirect::to('/')->with('clearGuestKey', true);
    }
    */

    public function changePassword()
    {
        // check the current password is correct
        if (!Auth::validate([
            'email' => Auth::user()->email,
            'password' => Input::get('current_password')
        ])) {
            return trans('texts.password_error_incorrect');
        }

        // validate the new password
        $password = Input::get('new_password');
        $confirm = Input::get('confirm_password');

        if (strlen($password) < 6 || $password != $confirm) {
            return trans('texts.password_error_invalid');
        }

        // save the new password
        $user = Auth::user();
        $user->password = bcrypt($password);
        $user->save();

        return RESULT_SUCCESS;
    }

    public function switchCompany($newUserId)
    {
        $oldUserId = Auth::user()->id;
        $referer = Request::header('referer');
        $company = $this->companyRepo->findUserCompanies($newUserId, $oldUserId);

        if ($company) {
            if ($company->hasUserId($newUserId) && $company->hasUserId($oldUserId)) {
                Auth::loginUsingId($newUserId);
                Auth::user()->loginaccount->loadLocalizationSettings();

                // regenerate token to prevent open pages
                // from saving under the wrong loginaccount
                Session::put('_token', str_random(40));
            }
        }

        return Redirect::to($referer);
    }

    public function unlinkCompany($userCompanyId, $userId)
    {
        $this->companyRepo->unlinkUser($userCompanyId, $userId);
        $referer = Request::header('referer');

        $users = $this->companyRepo->loadCompanies(Auth::user()->id);
        Session::put(SESSION_USER_COMPANIES, $users);

        Session::flash('message', trans('texts.unlinked_company'));
        return Redirect::to('/dashboard');
    }

    public function manageCompanies()
    {
        return View::make('users.company_management');
    }

}
