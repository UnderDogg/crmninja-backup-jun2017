<?php namespace App\Ninja\Repositories;

use Auth;
use Request;
use Session;
use Utils;
use URL;
use stdClass;
use Validator;
use Schema;
use App\Models\AccountGateway;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Relation;
use App\Models\Credit;
use App\Models\Language;
use App\Models\Contact;
use App\Models\LoginAccount;
use App\Models\Corporation;
use App\Models\User;
use App\Models\UserAccountToken;

class LoginAccountRepository
{
    public function create($firstName = '', $lastName = '', $email = '', $password = '')
    {
        $corporation = new Corporation();
        $corporation->save();

        $loginaccount = new LoginAccount();
        $loginaccount->ip = Request::getClientIp();
        $loginaccount->company_key = str_random(RANDOM_KEY_LENGTH);
        $loginaccount->corporation_id = $corporation->id;

        // Track referal code
        if ($referralCode = Session::get(SESSION_REFERRAL_CODE)) {
            if ($user = User::whereReferralCode($referralCode)->first()) {
                $loginaccount->referral_user_id = $user->id;
            }
        }

        if ($locale = Session::get(SESSION_LOCALE)) {
            if ($language = Language::whereLocale($locale)->first()) {
                $loginaccount->language_id = $language->id;
            }
        }

        $loginaccount->save();

        $user = new User();
        if (!$firstName && !$lastName && !$email && !$password) {
            $user->password = str_random(RANDOM_KEY_LENGTH);
            $user->username = str_random(RANDOM_KEY_LENGTH);
        } else {
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->email = $user->username = $email;
            if (!$password) {
                $password = str_random(RANDOM_KEY_LENGTH);
            }
            $user->password = bcrypt($password);
        }

        $user->confirmed = !Utils::isNinja();
        $user->registered = !Utils::isNinja() || $email;

        if (!$user->confirmed) {
            $user->confirmation_code = str_random(RANDOM_KEY_LENGTH);
        }

        $loginaccount->users()->save($user);

        return $loginaccount;
    }

    public function getSearchData($user)
    {
        $data = $this->getLoginAccountSearchData($user);

        $data['navigation'] = $user->is_admin ? $this->getNavigationSearchData() : [];

        return $data;
    }

    private function getLoginAccountSearchData($user)
    {
        $loginaccount = $user->loginaccount;

        $data = [
            'relations' => [],
            'contacts' => [],
            'invoices' => [],
            'quotes' => [],
        ];

        // include custom relation fields in search
        if ($loginaccount->custom_relation_label1) {
            $data[$loginaccount->custom_relation_label1] = [];
        }
        if ($loginaccount->custom_relation_label2) {
            $data[$loginaccount->custom_relation_label2] = [];
        }

        if ($user->hasPermission('view_all')) {
            $relations = Relation::scope()
                        ->with('contacts', 'invoices')
                        ->get();
        } else {
            $relations = Relation::scope()
                        ->where('user_id', '=', $user->id)
                        ->with(['contacts', 'invoices' => function($query) use ($user) {
                            $query->where('user_id', '=', $user->id);
                        }])->get();
        }

        foreach ($relations as $relation) {
            if ($relation->name) {
                $data['relations'][] = [
                    'value' => $relation->name,
                    'tokens' => $relation->name,
                    'url' => $relation->present()->url,
                ];
            }

            if ($relation->custom_value1) {
                $data[$loginaccount->custom_relation_label1][] = [
                    'value' => "{$relation->custom_value1}: " . $relation->getDisplayName(),
                    'tokens' => $relation->custom_value1,
                    'url' => $relation->present()->url,
                ];
            }
            if ($relation->custom_value2) {
                $data[$loginaccount->custom_relation_label2][] = [
                    'value' => "{$relation->custom_value2}: " . $relation->getDisplayName(),
                    'tokens' => $relation->custom_value2,
                    'url' => $relation->present()->url,
                ];
            }

            foreach ($relation->contacts as $contact) {
                if ($contact->getFullName()) {
                    $data['contacts'][] = [
                        'value' => $contact->getDisplayName(),
                        'tokens' => $contact->getDisplayName(),
                        'url' => $relation->present()->url,
                    ];
                }
                if ($contact->email) {
                    $data['contacts'][] = [
                        'value' => $contact->email,
                        'tokens' => $contact->email,
                        'url' => $relation->present()->url,
                    ];
                }
            }

            foreach ($relation->invoices as $invoice) {
                $entityType = $invoice->getEntityType();
                $data["{$entityType}s"][] = [
                    'value' => $invoice->getDisplayName() . ': ' . $relation->getDisplayName(),
                    'tokens' => $invoice->getDisplayName() . ': ' . $relation->getDisplayName(),
                    'url' => $invoice->present()->url,
                ];
            }
        }

        return $data;
    }

    private function getNavigationSearchData()
    {
        $entityTypes = [
            ENTITY_INVOICE,
            ENTITY_RELATION,
            ENTITY_QUOTE,
            ENTITY_TASK,
            ENTITY_EXPENSE,
            ENTITY_EXPENSE_CATEGORY,
            ENTITY_VENDOR,
            ENTITY_RECURRING_INVOICE,
            ENTITY_PAYMENT,
            ENTITY_CREDIT
        ];

        foreach ($entityTypes as $entityType) {
            $features[] = [
                "new_{$entityType}",
                Utils::pluralizeEntityType($entityType) . '/create'
            ];
            $features[] = [
                'list_' . Utils::pluralizeEntityType($entityType),
                Utils::pluralizeEntityType($entityType)
            ];
        }

        $features = array_merge($features, [
            ['dashboard', '/dashboard'],
            ['customize_design', '/settings/customize_design'],
            ['new_tax_rate', '/tax_rates/create'],
            ['new_product', '/products/create'],
            ['new_user', '/users/create'],
            ['custom_fields', '/settings/invoice_settings'],
            ['invoice_number', '/settings/invoice_settings'],
            ['buy_now_buttons', '/settings/customer_portal#buyNow']
        ]);

        $settings = array_merge(LoginAccount::$basicSettings, LoginAccount::$advancedSettings);

        if ( ! Utils::isNinjaProd()) {
            $settings[] = COMPANY_SYSTEM_SETTINGS;
        }

        foreach ($settings as $setting) {
            $features[] = [
                $setting,
                "/settings/{$setting}",
            ];
        }

        foreach ($features as $feature) {
            $data[] = [
                'value' => trans('texts.' . $feature[0]),
                'tokens' => trans('texts.' . $feature[0]),
                'url' => URL::to($feature[1])
            ];
        }

        return $data;
    }

    public function enablePlan($plan, $credit = 0)
    {
        $loginaccount = Auth::user()->loginaccount;
        $relation = $this->getNinjaRelation($loginaccount);
        $invitation = $this->createNinjaInvoice($relation, $loginaccount, $plan, $credit);

        return $invitation;
    }

    public function createNinjaCredit($relation, $amount)
    {
        $loginaccount = $this->getNinjaLoginAccount();

        $lastCredit = Credit::withTrashed()->whereLoginAccountId($loginaccount->id)->orderBy('public_id', 'DESC')->first();
        $publicId = $lastCredit ? ($lastCredit->public_id + 1) : 1;

        $credit = new Credit();
        $credit->public_id = $publicId;
        $credit->useraccount_id = $loginaccount->id;
        $credit->user_id = $loginaccount->users()->first()->id;
        $credit->relation_id = $relation->id;
        $credit->amount = $amount;
        $credit->save();

        return $credit;
    }

    public function createNinjaInvoice($relation, $relationLoginAccount, $plan, $credit = 0)
    {
        $term = $plan['term'];
        $plan_cost = $plan['price'];
        $num_users = $plan['num_users'];
        $plan = $plan['plan'];

        if ($credit < 0) {
            $credit = 0;
        }

        $loginaccount = $this->getNinjaLoginAccount();
        $lastInvoice = Invoice::withTrashed()->whereLoginAccountId($loginaccount->id)->orderBy('public_id', 'DESC')->first();
        $publicId = $lastInvoice ? ($lastInvoice->public_id + 1) : 1;
        $invoice = new Invoice();
        $invoice->useraccount_id = $loginaccount->id;
        $invoice->user_id = $loginaccount->users()->first()->id;
        $invoice->public_id = $publicId;
        $invoice->relation_id = $relation->id;
        $invoice->invoice_number = $loginaccount->getNextInvoiceNumber($invoice);
        $invoice->invoice_date = $relationLoginAccount->getRenewalDate();
        $invoice->amount = $invoice->balance = $plan_cost - $credit;
        $invoice->save();

        if ($credit) {
            $credit_item = InvoiceItem::createNew($invoice);
            $credit_item->qty = 1;
            $credit_item->cost = -$credit;
            $credit_item->notes = trans('texts.plan_credit_description');
            $credit_item->product_key = trans('texts.plan_credit_product');
            $invoice->invoice_items()->save($credit_item);
        }

        $item = InvoiceItem::createNew($invoice);
        $item->qty = 1;
        $item->cost = $plan_cost;
        $item->notes = trans("texts.{$plan}_plan_{$term}_description");

        if ($plan == PLAN_ENTERPRISE) {
            $min = Utils::getMinNumUsers($num_users);
            $item->notes .= "\n\n###" . trans('texts.min_to_max_users', ['min' => $min, 'max' => $num_users]);
        }

        // Don't change this without updating the regex in PaymentService->createPayment()
        $item->product_key = 'Plan - '.ucfirst($plan).' ('.ucfirst($term).')';
        $invoice->invoice_items()->save($item);

        $invitation = new Invitation();
        $invitation->useraccount_id = $loginaccount->id;
        $invitation->user_id = $loginaccount->users()->first()->id;
        $invitation->public_id = $publicId;
        $invitation->invoice_id = $invoice->id;
        $invitation->contact_id = $relation->contacts()->first()->id;
        $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
        $invitation->save();

        return $invitation;
    }

    public function getNinjaLoginAccount()
    {
        $loginaccount = LoginAccount::whereLoginAccountKey(NINJA_COMPANY_KEY)->first();

        if ($loginaccount) {
            return $loginaccount;
        } else {
            $loginaccount = new LoginAccount();
            $loginaccount->name = 'Invoice Ninja';
            $loginaccount->work_email = 'contact@invoiceninja.com';
            $loginaccount->work_phone = '(800) 763-1948';
            $loginaccount->company_key = NINJA_COMPANY_KEY;
            $loginaccount->save();

            $random = str_random(RANDOM_KEY_LENGTH);
            $user = new User();
            $user->registered = true;
            $user->confirmed = true;
            $user->email = 'contact@invoiceninja.com';
            $user->password = $random;
            $user->username = $random;
            $user->first_name = 'Invoice';
            $user->last_name = 'Ninja';
            $user->notify_sent = true;
            $user->notify_paid = true;
            $loginaccount->users()->save($user);

            if ($config = env(NINJA_GATEWAY_CONFIG)) {
                $accountGateway = new AccountGateway();
                $accountGateway->user_id = $user->id;
                $accountGateway->gateway_id = NINJA_GATEWAY_ID;
                $accountGateway->public_id = 1;
                $accountGateway->setConfig(json_decode($config));
                $loginaccount->account_gateways()->save($accountGateway);
            }
        }

        return $loginaccount;
    }

    public function getNinjaRelation($loginaccount)
    {
        $loginaccount->load('users');
        $ninjaLoginAccount = $this->getNinjaLoginAccount();
        $ninjaUser = $ninjaLoginAccount->getPrimaryUser();
        $relation = Relation::whereLoginAccountId($ninjaLoginAccount->id)
                    ->wherePublicId($loginaccount->id)
                    ->first();
        $relationExists = $relation ? true : false;

        if (!$relation) {
            $relation = new Relation();
            $relation->public_id = $loginaccount->id;
            $relation->useraccount_id = $ninjaLoginAccount->id;
            $relation->user_id = $ninjaUser->id;
            $relation->currency_id = 1;
        }

        foreach (['name', 'address1', 'address2', 'city', 'state', 'postal_code', 'country_id', 'work_phone', 'language_id', 'vat_number'] as $field) {
            $relation->$field = $loginaccount->$field;
        }

        $relation->save();

        if ($relationExists) {
            $contact = $relation->getPrimaryContact();
        } else {
            $contact = new Contact();
            $contact->user_id = $ninjaUser->id;
            $contact->useraccount_id = $ninjaLoginAccount->id;
            $contact->public_id = $loginaccount->id;
            $contact->is_primary = true;
        }

        $user = $loginaccount->getPrimaryUser();
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            $contact->$field = $user->$field;
        }

        $relation->contacts()->save($contact);

        return $relation;
    }

    public function findByKey($key)
    {
        $loginaccount = LoginAccount::whereLoginAccountKey($key)
                    ->with('relations.invoices.invoice_items', 'relations.contacts')
                    ->firstOrFail();

        return $loginaccount;
    }

    public function unlinkUserFromOauth($user)
    {
        $user->oauth_provider_id = null;
        $user->oauth_user_id = null;
        $user->save();
    }

    public function updateUserFromOauth($user, $firstName, $lastName, $email, $providerId, $oauthUserId)
    {
        if (!$user->registered) {
            $rules = ['email' => 'email|required|unique:users,email,'.$user->id.',id'];
            $validator = Validator::make(['email' => $email], $rules);
            if ($validator->fails()) {
                $messages = $validator->messages();
                return $messages->first('email');
            }

            $user->email = $email;
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->registered = true;

            $user->loginaccount->startTrial(PLAN_PRO);
        }

        $user->oauth_provider_id = $providerId;
        $user->oauth_user_id = $oauthUserId;
        $user->save();

        return true;
    }

    public function registerNinjaUser($user)
    {
        if ($user->email == TEST_USERNAME) {
            return false;
        }

        $url = (Utils::isNinjaDev() ? SITE_URL : NINJA_APP_URL) . '/signup/register';
        $data = '';
        $fields = [
            'first_name' => urlencode($user->first_name),
            'last_name' => urlencode($user->last_name),
            'email' => urlencode($user->email),
        ];

        foreach ($fields as $key => $value) {
            $data .= $key.'='.$value.'&';
        }
        rtrim($data, '&');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function findUserByOauth($providerId, $oauthUserId)
    {
        return User::where('oauth_user_id', $oauthUserId)
                    ->where('oauth_provider_id', $providerId)
                    ->first();
    }

    public function findUsers($user, $with = null)
    {
        $companies = $this->findUserCompanies($user->id);

        if ($companies) {
            return $this->getUserCompanies($companies, $with);
        } else {
            return [$user];
        }
    }

    public function findUserCompanies($userId1, $userId2 = false)
    {
        if (!Schema::hasTable('login_accounts')) {
            return false;
        }

        $query = LoginAccount::where('user_id1', '=', $userId1)
                                ->orWhere('user_id2', '=', $userId1)
                                ->orWhere('user_id3', '=', $userId1)
                                ->orWhere('user_id4', '=', $userId1)
                                ->orWhere('user_id5', '=', $userId1);

        if ($userId2) {
            $query->orWhere('user_id1', '=', $userId2)
                    ->orWhere('user_id2', '=', $userId2)
                    ->orWhere('user_id3', '=', $userId2)
                    ->orWhere('user_id4', '=', $userId2)
                    ->orWhere('user_id5', '=', $userId2);
        }

        return $query->first(['id', 'user_id1', 'user_id2', 'user_id3', 'user_id4', 'user_id5']);
    }

    public function getUserCompanies($record, $with = null)
    {
        if (!$record) {
            return false;
        }

        $userIds = [];
        for ($i=1; $i<=5; $i++) {
            $field = "user_id$i";
            if ($record->$field) {
                $userIds[] = $record->$field;
            }
        }

        $users = User::with('loginaccount')
                    ->whereIn('id', $userIds);

        if ($with) {
            $users->with($with);
        }

        return $users->get();
    }

    public function prepareUsersData($record)
    {
        if (!$record) {
            return false;
        }

        $users = $this->getUserCompanies($record);

        $data = [];
        foreach ($users as $user) {
            $item = new stdClass();
            $item->id = $record->id;
            $item->user_id = $user->id;
            $item->public_id = $user->public_id;
            $item->user_name = $user->getDisplayName();
            $item->useraccount_id = $user->loginaccount->id;
            $item->useraccount_name = $user->loginaccount->getDisplayName();
            $item->logo_url = $user->loginaccount->hasLogo() ? $user->loginaccount->getLogoUrl() : null;
            $data[] = $item;
        }

        return $data;
    }

    public function loadCompanies($userId) {
        $record = self::findUserCompanies($userId);
        return self::prepareUsersData($record);
    }

    public function associateCompanies($userId1, $userId2) {

        $record = self::findUserCompanies($userId1, $userId2);

        if ($record) {
            foreach ([$userId1, $userId2] as $userId) {
                if (!$record->hasUserId($userId)) {
                    $record->setUserId($userId);
                }
            }
        } else {
            $record = new LoginAccount();
            $record->user_id1 = $userId1;
            $record->user_id2 = $userId2;
        }

        $record->save();

        $users = $this->getUserCompanies($record);

        // Pick the primary user
        foreach ($users as $user) {
            if (!$user->public_id) {
                $useAsPrimary = false;
                if(empty($primaryUser)) {
                    $useAsPrimary = true;
                }

                $planDetails = $user->loginaccount->getPlanDetails(false, false);
                $planLevel = 0;

                if ($planDetails) {
                    $planLevel = 1;
                    if ($planDetails['plan'] == PLAN_ENTERPRISE) {
                        $planLevel = 2;
                    }

                    if (!$useAsPrimary && (
                        $planLevel > $primaryUserPlanLevel
                        || ($planLevel == $primaryUserPlanLevel && $planDetails['expires'] > $primaryUserPlanExpires)
                    )) {
                        $useAsPrimary = true;
                    }
                }

                if  ($useAsPrimary) {
                    $primaryUser = $user;
                    $primaryUserPlanLevel = $planLevel;
                    if ($planDetails) {
                        $primaryUserPlanExpires = $planDetails['expires'];
                    }
                }
            }
        }

        // Merge other companies into the primary user's corporation
        if (!empty($primaryUser)) {
            foreach ($users as $user) {
                if ($user == $primaryUser || $user->public_id) {
                    continue;
                }

                if ($user->loginaccount->corporation_id != $primaryUser->loginaccount->corporation_id) {
                    foreach ($user->loginaccount->corporation->companies as $loginaccount) {
                        $loginaccount->corporation_id = $primaryUser->loginaccount->corporation_id;
                        $loginaccount->save();
                    }
                    $user->loginaccount->corporation->forceDelete();
                }
            }
        }

        return $users;
    }

    public function unlinkLoginAccount($loginaccount) {
        foreach ($loginaccount->users as $user) {
            if ($userAccount = self::findUserCompanies($user->id)) {
                $userAccount->removeUserId($user->id);
                $userAccount->save();
            }
        }
    }

    public function unlinkUser($userAccountId, $userId) {
        $userAccount = LoginAccount::whereId($userAccountId)->first();
        if ($userAccount->hasUserId($userId)) {
            $userAccount->removeUserId($userId);
            $userAccount->save();
        }

        $user = User::whereId($userId)->first();

        if (!$user->public_id && $user->loginaccount->corporation->companies->count() > 1) {
            $corporation = Corporation::create();
            $corporation->save();
            $user->loginaccount->corporation_id = $corporation->id;
            $user->loginaccount->save();
        }
    }

    public function findWithReminders()
    {
        return LoginAccount::whereRaw('enable_reminder1 = 1 OR enable_reminder2 = 1 OR enable_reminder3 = 1')->get();
    }

    public function getReferralCode()
    {
        do {
            $code = strtoupper(str_random(8));
            $match = User::whereReferralCode($code)
                        ->withTrashed()
                        ->first();
        } while ($match);

        return $code;
    }

    public function createTokens($user, $name)
    {
        $name = trim($name) ?: 'TOKEN';
        $users = $this->findUsers($user);

        foreach ($users as $user) {
            if ($token = UserAccountToken::whereUserId($user->id)->whereName($name)->first()) {
                continue;
            }

            $token = UserAccountToken::createNew($user);
            $token->name = $name;
            $token->token = str_random(RANDOM_KEY_LENGTH);
            $token->save();
        }
    }

    public function getUserLoginAccountId($loginaccount)
    {
        $user = $loginaccount->users()->first();
        $userAccount = $this->findUserCompanies($user->id);

        return $userAccount ? $userAccount->id : false;
    }

    public function save($data, $loginaccount)
    {
        $loginaccount->fill($data);
        $loginaccount->save();
    }
}
