<?php namespace App\Http\Middleware;

use Closure;
use Auth;
use Session;
use App\Models\Invitation;
use App\Models\Contact;

/**
 * Class Authenticate
 */
class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param string $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = 'user')
    {
        $authenticated = Auth::guard($guard)->check();

        if ($guard == 'relation') {
            if (!empty($request->invitation_key)) {
                $contact_key = session('contact_key');
                if ($contact_key) {
                    $contact = $this->getContact($contact_key);
                    $invitation = $this->getInvitation($request->invitation_key);

                    if (!$invitation) {
                        return response()->view('error', [
                            'error' => trans('texts.invoice_not_found'),
                            'hideHeader' => true,
                        ]);
                    }

                    if ($contact && $contact->id != $invitation->contact_id) {
                        // This is a different relation; reauthenticate
                        $authenticated = false;
                        Auth::guard($guard)->logout();
                    }
                    Session::put('contact_key', $invitation->contact->contact_key);
                }
            }

            if (!empty($request->contact_key)) {
                $contact_key = $request->contact_key;
                Session::put('contact_key', $contact_key);
            } else {
                $contact_key = session('contact_key');
            }

            if ($contact_key) {
                $contact = $this->getContact($contact_key);
            } elseif (!empty($request->invitation_key)) {
                $invitation = $this->getInvitation($request->invitation_key);
                $contact = $invitation->contact;
                Session::put('contact_key', $contact->contact_key);
            } else {
                return \Redirect::to('relation/sessionexpired');
            }
            $company = $contact->loginaccount;

            if (Auth::guard('user')->check() && Auth::user('user')->company_id === $company->id) {
                // This is an admin; let them pretend to be a relation
                $authenticated = true;
            }

            // Does this loginaccount require portal passwords?
            if ($company && (!$company->enable_portal_password || !$company->hasFeature(FEATURE_CUSTOMER_PORTAL_PASSWORD))) {
                $authenticated = true;
            }

            if (!$authenticated && $contact && !$contact->password) {
                $authenticated = true;
            }
        }

        if (!$authenticated) {
            if ($request->ajax()) {
                return response('Unauthorized.', 401);
            } else {
                return redirect()->guest($guard == 'relation' ? '/relation/login' : '/login');
            }
        }

        return $next($request);
    }

    /**
     * @param $key
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    protected function getInvitation($key)
    {
        $invitation = Invitation::withTrashed()->where('invitation_key', '=', $key)->first();
        if ($invitation && !$invitation->is_deleted) {
            return $invitation;
        } else {
            return null;
        }
    }

    /**
     * @param $key
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    protected function getContact($key)
    {
        $contact = Contact::withTrashed()->where('contact_key', '=', $key)->first();
        if ($contact && !$contact->is_deleted) {
            return $contact;
        } else {
            return null;
        }
    }
}
