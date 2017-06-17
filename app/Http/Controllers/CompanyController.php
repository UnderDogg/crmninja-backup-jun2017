<?php
namespace App\Http\Controllers;

use App\Models\AccountGateway;
use App\Services\TemplateService;
use Auth;
use File;
use Image;
use Input;
use Redirect;
use Session;
use Utils;
use Validator;
use View;
use URL;
use stdClass;
use Cache;
use Response;
use Request;
use App\Models\Affiliate;
use App\Models\License;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Company;
use App\Models\Document;
use App\Models\Gateway;
use App\Models\InvoiceDesign;
use App\Models\TaxRate;
use App\Models\Product;
use App\Models\PaymentTerm;
use App\Ninja\Repositories\CompanyRepository;
use App\Ninja\Repositories\ReferralRepository;
use App\Ninja\Mailers\UserMailer;
use App\Ninja\Mailers\ContactMailer;
use App\Events\UserSignedUp;
use App\Events\UserSettingsChanged;
use App\Services\AuthService;
use App\Services\PaymentService;
use App\Http\Requests\UpdateCompanyRequest;

/**
 * Class LoginAccountController
 */
class LoginAccountController extends BaseController
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
     * @var ContactMailer
     */
    protected $contactMailer;

    /**
     * @var ReferralRepository
     */
    protected $referralRepository;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * LoginAccountController constructor.
     *
     * @param CompanyRepository $companyRepo
     * @param UserMailer $userMailer
     * @param ContactMailer $contactMailer
     * @param ReferralRepository $referralRepository
     * @param PaymentService $paymentService
     */
    public function __construct(
        CompanyRepository $companyRepo,
        UserMailer $userMailer,
        ContactMailer $contactMailer,
        ReferralRepository $referralRepository,
        PaymentService $paymentService
    )
    {
        $this->companyRepo = $companyRepo;
        $this->userMailer = $userMailer;
        $this->contactMailer = $contactMailer;
        $this->referralRepository = $referralRepository;
        $this->paymentService = $paymentService;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function demo()
    {
        $demoCompanyId = Utils::getDemoCompanyId();

        if (!$demoCompanyId) {
            return Redirect::to('/');
        }

        $company = Company::find($demoCompanyId);
        $user = $company->users()->first();

        Auth::login($user, true);

        return Redirect::to('invoices/create');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getStarted()
    {
        $user = false;
        $guestKey = Input::get('guest_key'); // local storage key to login until registered
        $prevUserId = Session::pull(PREV_USER_ID); // last user id used to link to new loginaccount

        if (Auth::check()) {
            return Redirect::to('invoices/create');
        }

        if (!Utils::isNinja() && (Company::count() > 0 && !$prevUserId)) {
            return Redirect::to('/login');
        }

        if ($guestKey && !$prevUserId) {
            $user = User::where('password', '=', $guestKey)->first();

            if ($user && $user->registered) {
                return Redirect::to('/');
            }
        }

        if (!$user) {
            $company = $this->companyRepo->create();
            $user = $company->users()->first();

            Session::forget(RECENTLY_VIEWED);

            if ($prevUserId) {
                $users = $this->companyRepo->associateCompanies($user->id, $prevUserId);
                Session::put(SESSION_USER_COMPANIES, $users);
            }
        }

        Auth::login($user, true);
        event(new UserSignedUp());

        $redirectTo = Input::get('redirect_to') ?: 'invoices/create';

        return Redirect::to($redirectTo)->with('sign_up', Input::get('sign_up'));
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function changePlan()
    {
        $user = Auth::user();
        $company = $user->loginaccount;
        $corporation = $company->corporation;

        $plan = Input::get('plan');
        $term = Input::get('plan_term');
        $numUsers = Input::get('num_users');

        $planDetails = $company->getPlanDetails(false, false);

        $newPlan = [
            'plan' => $plan,
            'term' => $term,
            'num_users' => $numUsers,
        ];
        $newPlan['price'] = Utils::getPlanPrice($newPlan);
        $credit = 0;

        if (!empty($planDetails['started']) && $plan == PLAN_FREE) {
            // Downgrade
            $refund_deadline = clone $planDetails['started'];
            $refund_deadline->modify('+30 days');

            if ($plan == PLAN_FREE && $refund_deadline >= date_create()) {
                if ($payment = $company->corporation->payment) {
                    $ninjaCompany = $this->companyRepo->getNinjaCompany();
                    $paymentDriver = $ninjaCompany->paymentDriver();
                    $paymentDriver->refundPayment($payment);
                    Session::flash('message', trans('texts.plan_refunded'));
                    \Log::info("Refunded Plan Payment: {$company->name} - {$user->email}");
                } else {
                    Session::flash('message', trans('texts.updated_plan'));
                }
            }
        }

        if (!empty($planDetails['paid']) && $plan != PLAN_FREE) {
            $time_used = $planDetails['paid']->diff(date_create());
            $days_used = $time_used->days;

            if ($time_used->invert) {
                // They paid in advance
                $days_used *= -1;
            }

            $days_total = $planDetails['paid']->diff($planDetails['expires'])->days;
            $percent_used = $days_used / $days_total;
            $credit = $planDetails['plan_price'] * (1 - $percent_used);
        }

        if ($newPlan['price'] > $credit) {
            $invitation = $this->companyRepo->enablePlan($newPlan, $credit);
            return Redirect::to('view/' . $invitation->invitation_key);
        } else {

            if ($plan != PLAN_FREE) {
                $corporation->plan_term = $term;
                $corporation->plan_price = $newPlan['price'];
                $corporation->num_users = $numUsers;
                $corporation->plan_expires = date_create()->modify($term == PLAN_TERM_MONTHLY ? '+1 month' : '+1 year')->format('Y-m-d');
            }

            $corporation->plan = $plan;
            $corporation->save();

            return Redirect::to('settings/company_management');
        }
    }


    /**
     * @param $entityType
     * @param $visible
     * @return mixed
     */
    public function setTrashVisible($entityType, $visible)
    {
        Session::put("show_trash:{$entityType}", $visible == 'true');

        return RESULT_SUCCESS;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSearchData()
    {
        $data = $this->companyRepo->getSearchData(Auth::user());

        return Response::json($data);
    }

    /**
     * @param bool $section
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showSection($section = false)
    {
        if (!$section) {
            return Redirect::to('/settings/' . COMPANY_COMPANY_DETAILS, 301);
        }

        if ($section == COMPANY_COMPANY_DETAILS) {
            return self::showCorporationDetails();
        } elseif ($section == COMPANY_LOCALIZATION) {
            return self::showLocalization();
        } elseif ($section == COMPANY_PAYMENTS) {
            return self::showOnlinePayments();
        } elseif ($section == COMPANY_BANKS) {
            return self::showBankRekeningen();
        } elseif ($section == COMPANY_INVOICE_SETTINGS) {
            return self::showInvoiceSettings();
        } elseif ($section == COMPANY_IMPORT_EXPORT) {
            return View::make('companies.import_export', ['title' => trans('texts.import_export')]);
        } elseif ($section == COMPANY_MANAGEMENT) {
            return self::showCompanyManagement();
        } elseif ($section == COMPANY_INVOICE_DESIGN || $section == COMPANY_CUSTOMIZE_DESIGN) {
            return self::showInvoiceDesign($section);
        } elseif ($section == COMPANY_CUSTOMER_PORTAL) {
            return self::showRelationPortal();
        } elseif ($section === COMPANY_TEMPLATES_AND_REMINDERS) {
            return self::showTemplates();
        } elseif ($section === COMPANY_PRODUCTS) {
            return self::showProducts();
        } elseif ($section === COMPANY_TAX_RATES) {
            return self::showTaxRates();
        } elseif ($section === COMPANY_PAYMENT_TERMS) {
            return self::showPaymentTerms();
        } elseif ($section === COMPANY_SYSTEM_SETTINGS) {
            return self::showSystemSettings();
        } else {
            $data = [
                'company' => Company::with('users')->findOrFail(Auth::user()->company_id),
                'title' => trans("texts.{$section}"),
                'section' => $section,
            ];

            return View::make("companies.{$section}", $data);
        }
    }

    /**
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    private function showSystemSettings()
    {
        if (Utils::isNinjaProd()) {
            return Redirect::to('/');
        }

        $data = [
            'company' => Company::with('users')->findOrFail(Auth::user()->company_id),
            'title' => trans('texts.system_settings'),
            'section' => COMPANY_SYSTEM_SETTINGS,
        ];

        return View::make('companies.system_settings', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showInvoiceSettings()
    {
        $company = Auth::user()->loginaccount;
        $recurringHours = [];

        for ($i = 0; $i < 24; $i++) {
            if ($company->military_time) {
                $format = 'H:i';
            } else {
                $format = 'g:i a';
            }
            $recurringHours[$i] = date($format, strtotime("{$i}:00"));
        }

        $data = [
            'company' => Company::with('users')->findOrFail(Auth::user()->company_id),
            'title' => trans('texts.invoice_settings'),
            'section' => COMPANY_INVOICE_SETTINGS,
            'recurringHours' => $recurringHours,
        ];

        return View::make('companies.invoice_settings', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showCorporationDetails()
    {
        // check that logo is less than the max file size
        $company = Auth::user()->loginaccount;
        if ($company->isLogoTooLarge()) {
            Session::flash('warning', trans('texts.logo_too_large', ['size' => $company->getLogoSize() . 'KB']));
        }

        $data = [
            'company' => Company::with('users')->findOrFail(Auth::user()->company_id),
            'sizes' => Cache::get('sizes'),
            'title' => trans('texts.company_details'),
        ];

        return View::make('companies.details', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showCompanyManagement()
    {
        $company = Auth::user()->loginaccount;
        $data = [
            'company' => $company,
            'planDetails' => $company->getPlanDetails(true),
            'title' => trans('texts.company_management'),
        ];

        return View::make('companies.management', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function showUserDetails()
    {
        $oauthLoginUrls = [];
        foreach (AuthService::$providers as $provider) {
            $oauthLoginUrls[] = ['label' => $provider, 'url' => URL::to('/auth/' . strtolower($provider))];
        }

        $data = [
            'company' => Company::with('users')->findOrFail(Auth::user()->company_id),
            'title' => trans('texts.user_details'),
            'user' => Auth::user(),
            'oauthProviderName' => AuthService::getProviderName(Auth::user()->oauth_provider_id),
            'oauthLoginUrls' => $oauthLoginUrls,
            'referralCounts' => $this->referralRepository->getCounts(Auth::user()->id),
        ];

        return View::make('companies.user_details', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showLocalization()
    {
        $data = [
            'company' => Company::with('users')->findOrFail(Auth::user()->company_id),
            'timezones' => Cache::get('timezones'),
            'dateFormats' => Cache::get('dateFormats'),
            'datetimeFormats' => Cache::get('datetimeFormats'),
            'currencies' => Cache::get('currencies'),
            'title' => trans('texts.localization'),
            'weekdays' => Utils::getTranslatedWeekdayNames(),
        ];

        return View::make('companies.localization', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showBankRekeningen()
    {
        return View::make('companies.banks', [
            'title' => trans('texts.bankrekeningen'),
            'advanced' => !Auth::user()->hasFeature(FEATURE_EXPENSES),
        ]);
    }

    /**
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    private function showOnlinePayments()
    {
        $company = Auth::user()->loginaccount;
        $company->load('account_gateways');
        $count = count($company->account_gateways);
        $trashedCount = AccountGateway::scope()->withTrashed()->count();

        if ($accountGateway = $company->getGatewayConfig(GATEWAY_STRIPE)) {
            if (!$accountGateway->getPublishableStripeKey()) {
                Session::flash('warning', trans('texts.missing_publishable_key'));
            }
        }

        if ($trashedCount == 0) {
            return Redirect::to('gateways/create');
        } else {
            $tokenBillingOptions = [];
            for ($i = 1; $i <= 4; $i++) {
                $tokenBillingOptions[$i] = trans("texts.token_billing_{$i}");
            }

            return View::make('companies.payments', [
                'showAdd' => $count < count(Gateway::$alternate) + 1,
                'title' => trans('texts.online_payments'),
                'tokenBillingOptions' => $tokenBillingOptions,
                'company' => $company,
            ]);
        }
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showProducts()
    {
        $columns = ['product', 'description', 'unit_cost'];
        if (Auth::user()->loginaccount->invoice_item_taxes) {
            $columns[] = 'tax_rate';
        }
        $columns[] = 'action';

        $data = [
            'company' => Auth::user()->loginaccount,
            'title' => trans('texts.product_library'),
            'columns' => Utils::trans($columns),
        ];

        return View::make('companies.products', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showTaxRates()
    {
        $data = [
            'company' => Auth::user()->loginaccount,
            'title' => trans('texts.tax_rates'),
            'taxRates' => TaxRate::scope()->get(['id', 'name', 'rate']),
        ];

        return View::make('companies.tax_rates', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showPaymentTerms()
    {
        $data = [
            'company' => Auth::user()->loginaccount,
            'title' => trans('texts.payment_terms'),
            'taxRates' => PaymentTerm::scope()->get(['id', 'name', 'num_days']),
        ];

        return View::make('companies.payment_terms', $data);
    }

    /**
     * @param $section
     * @return \Illuminate\Contracts\View\View
     */
    private function showInvoiceDesign($section)
    {
        $company = Auth::user()->loginaccount->load('country');
        $invoice = new stdClass();
        $relation = new stdClass();
        $contact = new stdClass();
        $invoiceItem = new stdClass();
        $document = new stdClass();

        $relation->name = 'Sample Relation';
        $relation->address1 = trans('texts.address1');
        $relation->city = trans('texts.city');
        $relation->state = trans('texts.state');
        $relation->postal_code = trans('texts.postal_code');
        $relation->work_phone = trans('texts.work_phone');
        $relation->work_email = trans('texts.work_id');

        $invoice->invoice_number = '0000';
        $invoice->invoice_date = Utils::fromSqlDate(date('Y-m-d'));
        $invoice->loginaccount = json_decode($company->toJson());
        $invoice->amount = $invoice->balance = 100;

        $invoice->terms = trim($company->invoice_terms);
        $invoice->invoice_footer = trim($company->invoice_footer);

        $contact->email = 'contact@gmail.com';
        $relation->contacts = [$contact];

        $invoiceItem->cost = 100;
        $invoiceItem->qty = 1;
        $invoiceItem->notes = 'Notes';
        $invoiceItem->product_key = 'Item';

        $document->base64 = 'data:image/jpeg;base64,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';

        $invoice->relation = $relation;
        $invoice->invoice_items = [$invoiceItem];
        //$invoice->documents = $company->hasFeature(FEATURE_DOCUMENTS) ? [$document] : [];
        $invoice->documents = [];

        $data['company'] = $company;
        $data['invoice'] = $invoice;
        $data['invoiceLabels'] = json_decode($company->invoice_labels) ?: [];
        $data['title'] = trans('texts.invoice_design');
        $data['invoiceDesigns'] = InvoiceDesign::getDesigns();
        $data['invoiceFonts'] = Cache::get('fonts');
        $data['section'] = $section;

        $pageSizes = [
            'A0',
            'A1',
            'A2',
            'A3',
            'A4',
            'A5',
            'A6',
            'A7',
            'A8',
            'A9',
            'A10',
            'B0',
            'B1',
            'B2',
            'B3',
            'B4',
            'B5',
            'B6',
            'B7',
            'B8',
            'B9',
            'B10',
            'C0',
            'C1',
            'C2',
            'C3',
            'C4',
            'C5',
            'C6',
            'C7',
            'C8',
            'C9',
            'C10',
            'RA0',
            'RA1',
            'RA2',
            'RA3',
            'RA4',
            'SRA0',
            'SRA1',
            'SRA2',
            'SRA3',
            'SRA4',
            'Executive',
            'Folio',
            'Legal',
            'Letter',
            'Tabloid',
        ];
        $data['pageSizes'] = array_combine($pageSizes, $pageSizes);

        $design = false;
        foreach ($data['invoiceDesigns'] as $item) {
            if ($item->id == $company->invoice_design_id) {
                $design = $item->javascript;
                break;
            }
        }

        if ($section == COMPANY_CUSTOMIZE_DESIGN) {
            $data['customDesign'] = ($company->custom_design && !$design) ? $company->custom_design : $design;

            // sample invoice to help determine variables
            $invoice = Invoice::scope()
                ->invoiceType(INVOICE_TYPE_STANDARD)
                ->with('relation', 'loginaccount')
                ->where('is_recurring', '=', false)
                ->first();

            if ($invoice) {
                $invoice->hidePrivateFields();
                unset($invoice->loginaccount);
                unset($invoice->invoice_items);
                unset($invoice->relation->contacts);
                $data['sampleInvoice'] = $invoice;
            }
        }

        return View::make("companies.{$section}", $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showRelationPortal()
    {
        $company = Auth::user()->loginaccount->load('country');
        $css = $company->relation_view_css ? $company->relation_view_css : '';

        if (Utils::isNinja() && $css) {
            // Unescape the CSS for display purposes
            $css = str_replace(
                ['\3C ', '\3E ', '\26 '],
                ['<', '>', '&'],
                $css
            );
        }

        $types = [GATEWAY_TYPE_CREDIT_CARD, GATEWAY_TYPE_BANK_TRANSFER, GATEWAY_TYPE_PAYPAL, GATEWAY_TYPE_BITCOIN, GATEWAY_TYPE_DWOLLA];
        $options = [];
        foreach ($types as $type) {
            if ($company->getGatewayByType($type)) {
                $options[$type] = trans("texts.{$type}");
            }
        }

        $data = [
            'relation_view_css' => $css,
            'enable_portal_password' => $company->enable_portal_password,
            'send_portal_password' => $company->send_portal_password,
            'title' => trans('texts.customer_portal'),
            'section' => COMPANY_CUSTOMER_PORTAL,
            'company' => $company,
            'products' => Product::scope()->orderBy('product_key')->get(),
            'gateway_types' => $options,
        ];

        return View::make('companies.customer_portal', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    private function showTemplates()
    {
        $company = Auth::user()->loginaccount->load('country');
        $data['company'] = $company;
        $data['templates'] = [];
        $data['defaultTemplates'] = [];
        foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, REMINDER1, REMINDER2, REMINDER3] as $type) {
            $data['templates'][$type] = [
                'subject' => $company->getEmailSubject($type),
                'template' => $company->getEmailTemplate($type),
            ];
            $data['defaultTemplates'][$type] = [
                'subject' => $company->getDefaultEmailSubject($type),
                'template' => $company->getDefaultEmailTemplate($type),
            ];
        }
        $data['emailFooter'] = $company->getEmailFooter();
        $data['title'] = trans('texts.email_templates');

        return View::make('companies.templates_and_reminders', $data);
    }

    /**
     * @param $section
     * @return \Illuminate\Http\RedirectResponse
     */
    public function doSection($section = COMPANY_COMPANY_DETAILS)
    {
        if ($section === COMPANY_COMPANY_DETAILS) {
            return LoginAccountController::saveDetails();
        } elseif ($section === COMPANY_LOCALIZATION) {
            return LoginAccountController::saveLocalization();
        } elseif ($section == COMPANY_PAYMENTS) {
            return self::saveOnlinePayments();
        } elseif ($section === COMPANY_NOTIFICATIONS) {
            return LoginAccountController::saveNotifications();
        } elseif ($section === COMPANY_EXPORT) {
            return LoginAccountController::export();
        } elseif ($section === COMPANY_INVOICE_SETTINGS) {
            return LoginAccountController::saveInvoiceSettings();
        } elseif ($section === COMPANY_EMAIL_SETTINGS) {
            return LoginAccountController::saveEmailSettings();
        } elseif ($section === COMPANY_INVOICE_DESIGN) {
            return LoginAccountController::saveInvoiceDesign();
        } elseif ($section === COMPANY_CUSTOMIZE_DESIGN) {
            return LoginAccountController::saveCustomizeDesign();
        } elseif ($section === COMPANY_CUSTOMER_PORTAL) {
            return LoginAccountController::saveRelationPortal();
        } elseif ($section === COMPANY_TEMPLATES_AND_REMINDERS) {
            return LoginAccountController::saveEmailTemplates();
        } elseif ($section === COMPANY_PRODUCTS) {
            return LoginAccountController::saveProducts();
        } elseif ($section === COMPANY_TAX_RATES) {
            return LoginAccountController::saveTaxRates();
        } elseif ($section === COMPANY_PAYMENT_TERMS) {
            return LoginAccountController::savePaymetTerms();
        }
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveCustomizeDesign()
    {
        if (Auth::user()->loginaccount->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN)) {
            $company = Auth::user()->loginaccount;
            $company->custom_design = Input::get('custom_design');
            $company->invoice_design_id = CUSTOM_DESIGN;
            $company->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/' . COMPANY_CUSTOMIZE_DESIGN);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveRelationPortal()
    {
        $company = Auth::user()->loginaccount;

        $company->enable_relation_portal = !!Input::get('enable_relation_portal');
        $company->enable_relation_portal_dashboard = !!Input::get('enable_relation_portal_dashboard');
        $company->enable_portal_password = !!Input::get('enable_portal_password');
        $company->send_portal_password = !!Input::get('send_portal_password');
        $company->enable_buy_now_buttons = !!Input::get('enable_buy_now_buttons');

        // Only allowed for pro Invoice Ninja users or white labeled self-hosted users
        if (Auth::user()->loginaccount->hasFeature(FEATURE_CUSTOMER_PORTAL_CSS)) {
            $input_css = Input::get('relation_view_css');
            if (Utils::isNinja()) {
                // Allow referencing the body element
                $input_css = preg_replace('/(?<![a-z0-9\-\_\#\.])body(?![a-z0-9\-\_])/i', '.body', $input_css);

                //
                // Inspired by http://stackoverflow.com/a/5209050/1721527, dleavitt <https://stackoverflow.com/users/362110/dleavitt>
                //

                // Create a new configuration object
                $config = \HTMLPurifier_Config::createDefault();
                $config->set('Filter.ExtractStyleBlocks', true);
                $config->set('CSS.AllowImportant', true);
                $config->set('CSS.AllowTricky', true);
                $config->set('CSS.Trusted', true);

                // Create a new purifier instance
                $purifier = new \HTMLPurifier($config);

                // Wrap our CSS in style tags and pass to purifier.
                // we're not actually interested in the html response though
                $html = $purifier->purify('<style>' . $input_css . '</style>');

                // The "style" blocks are stored seperately
                $output_css = $purifier->context->get('StyleBlocks');

                // Get the first style block
                $sanitized_css = count($output_css) ? $output_css[0] : '';
            } else {
                $sanitized_css = $input_css;
            }

            $company->relation_view_css = $sanitized_css;
        }

        $company->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/' . COMPANY_CUSTOMER_PORTAL);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveEmailTemplates()
    {
        if (Auth::user()->loginaccount->hasFeature(FEATURE_EMAIL_TEMPLATES_REMINDERS)) {
            $company = Auth::user()->loginaccount;

            foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, REMINDER1, REMINDER2, REMINDER3] as $type) {
                $subjectField = "email_subject_{$type}";
                $subject = Input::get($subjectField, $company->getEmailSubject($type));
                $company->$subjectField = ($subject == $company->getDefaultEmailSubject($type) ? null : $subject);

                $bodyField = "email_template_{$type}";
                $body = Input::get($bodyField, $company->getEmailTemplate($type));
                $company->$bodyField = ($body == $company->getDefaultEmailTemplate($type) ? null : $body);
            }

            foreach ([REMINDER1, REMINDER2, REMINDER3] as $type) {
                $enableField = "enable_{$type}";
                $company->$enableField = Input::get($enableField) ? true : false;

                if ($company->$enableField) {
                    $company->{"num_days_{$type}"} = Input::get("num_days_{$type}");
                    $company->{"field_{$type}"} = Input::get("field_{$type}");
                    $company->{"direction_{$type}"} = Input::get("field_{$type}") == REMINDER_FIELD_INVOICE_DATE ? REMINDER_DIRECTION_AFTER : Input::get("direction_{$type}");
                }
            }

            $company->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/' . COMPANY_TEMPLATES_AND_REMINDERS);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveTaxRates()
    {
        $company = Auth::user()->loginaccount;
        $company->fill(Input::all());
        $company->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/' . COMPANY_TAX_RATES);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveProducts()
    {
        $company = Auth::user()->loginaccount;

        $company->fill_products = Input::get('fill_products') ? true : false;
        $company->update_products = Input::get('update_products') ? true : false;
        $company->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/' . COMPANY_PRODUCTS);
    }

    /**
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    private function saveEmailSettings()
    {
        if (Auth::user()->loginaccount->hasFeature(FEATURE_CUSTOM_EMAILS)) {
            $user = Auth::user();
            $subdomain = null;
            $iframeURL = null;
            $rules = [];

            if (Input::get('custom_link') == 'subdomain') {
                $subdomain = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', substr(strtolower(Input::get('subdomain')), 0, MAX_SUBDOMAIN_LENGTH));
                $exclude = ['www', 'app', 'mail', 'admin', 'blog', 'user', 'contact', 'payment', 'payments', 'billing', 'invoice', 'business', 'owner', 'info', 'ninja'];
                $rules['subdomain'] = "unique:companies,subdomain,{$user->company_id},id|not_in:" . implode(',', $exclude);
            } else {
                $iframeURL = preg_replace('/[^a-zA-Z0-9_\-\:\/\.]/', '', substr(strtolower(Input::get('iframe_url')), 0, MAX_IFRAME_URL_LENGTH));
                $iframeURL = rtrim($iframeURL, '/');
            }

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('settings/' . COMPANY_EMAIL_SETTINGS)
                    ->withErrors($validator)
                    ->withInput();
            } else {
                $company = Auth::user()->loginaccount;
                $company->subdomain = $subdomain;
                $company->iframe_url = $iframeURL;
                $company->pdf_email_attachment = Input::get('pdf_email_attachment') ? true : false;
                $company->document_email_attachment = Input::get('document_email_attachment') ? true : false;
                $company->email_design_id = Input::get('email_design_id');

                if (Utils::isNinja()) {
                    $company->enable_email_markup = Input::get('enable_email_markup') ? true : false;
                }

                $company->save();
                Session::flash('message', trans('texts.updated_settings'));
            }
        }

        return Redirect::to('settings/' . COMPANY_EMAIL_SETTINGS);
    }

    /**
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    private function saveInvoiceSettings()
    {
        if (Auth::user()->loginaccount->hasFeature(FEATURE_INVOICE_SETTINGS)) {
            $rules = [
                'invoice_number_pattern' => 'has_counter',
                'quote_number_pattern' => 'has_counter',
            ];

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('settings/' . COMPANY_INVOICE_SETTINGS)
                    ->withErrors($validator)
                    ->withInput();
            } else {
                $company = Auth::user()->loginaccount;
                $company->custom_label1 = trim(Input::get('custom_label1'));
                $company->custom_value1 = trim(Input::get('custom_value1'));
                $company->custom_label2 = trim(Input::get('custom_label2'));
                $company->custom_value2 = trim(Input::get('custom_value2'));
                $company->custom_relation_label1 = trim(Input::get('custom_relation_label1'));
                $company->custom_relation_label2 = trim(Input::get('custom_relation_label2'));
                $company->custom_invoice_label1 = trim(Input::get('custom_invoice_label1'));
                $company->custom_invoice_label2 = trim(Input::get('custom_invoice_label2'));
                $company->custom_invoice_taxes1 = Input::get('custom_invoice_taxes1') ? true : false;
                $company->custom_invoice_taxes2 = Input::get('custom_invoice_taxes2') ? true : false;
                $company->custom_invoice_text_label1 = trim(Input::get('custom_invoice_text_label1'));
                $company->custom_invoice_text_label2 = trim(Input::get('custom_invoice_text_label2'));
                $company->custom_invoice_item_label1 = trim(Input::get('custom_invoice_item_label1'));
                $company->custom_invoice_item_label2 = trim(Input::get('custom_invoice_item_label2'));

                $company->invoice_number_padding = Input::get('invoice_number_padding');
                $company->invoice_number_counter = Input::get('invoice_number_counter');
                $company->quote_number_prefix = Input::get('quote_number_prefix');
                $company->share_counter = Input::get('share_counter') ? true : false;
                $company->invoice_terms = Input::get('invoice_terms');
                $company->invoice_footer = Input::get('invoice_footer');
                $company->quote_terms = Input::get('quote_terms');
                $company->auto_convert_quote = Input::get('auto_convert_quote');
                $company->recurring_invoice_number_prefix = Input::get('recurring_invoice_number_prefix');

                if (Input::has('recurring_hour')) {
                    $company->recurring_hour = Input::get('recurring_hour');
                }

                if (!$company->share_counter) {
                    $company->quote_number_counter = Input::get('quote_number_counter');
                }

                if (Input::get('invoice_number_type') == 'prefix') {
                    $company->invoice_number_prefix = trim(Input::get('invoice_number_prefix'));
                    $company->invoice_number_pattern = null;
                } else {
                    $company->invoice_number_pattern = trim(Input::get('invoice_number_pattern'));
                    $company->invoice_number_prefix = null;
                }

                if (Input::get('quote_number_type') == 'prefix') {
                    $company->quote_number_prefix = trim(Input::get('quote_number_prefix'));
                    $company->quote_number_pattern = null;
                } else {
                    $company->quote_number_pattern = trim(Input::get('quote_number_pattern'));
                    $company->quote_number_prefix = null;
                }

                if (!$company->share_counter
                    && $company->invoice_number_prefix == $company->quote_number_prefix
                    && $company->invoice_number_pattern == $company->quote_number_pattern
                ) {
                    Session::flash('error', trans('texts.invalid_counter'));

                    return Redirect::to('settings/' . COMPANY_INVOICE_SETTINGS)->withInput();
                } else {
                    $company->save();
                    Session::flash('message', trans('texts.updated_settings'));
                }
            }
        }

        return Redirect::to('settings/' . COMPANY_INVOICE_SETTINGS);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveInvoiceDesign()
    {
        if (Auth::user()->loginaccount->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN)) {
            $company = Auth::user()->loginaccount;
            $company->hide_quantity = Input::get('hide_quantity') ? true : false;
            $company->hide_paid_to_date = Input::get('hide_paid_to_date') ? true : false;
            $company->all_pages_header = Input::get('all_pages_header') ? true : false;
            $company->all_pages_footer = Input::get('all_pages_footer') ? true : false;
            $company->invoice_embed_documents = Input::get('invoice_embed_documents') ? true : false;
            $company->header_font_id = Input::get('header_font_id');
            $company->body_font_id = Input::get('body_font_id');
            $company->primary_color = Input::get('primary_color');
            $company->secondary_color = Input::get('secondary_color');
            $company->invoice_design_id = Input::get('invoice_design_id');
            $company->font_size = intval(Input::get('font_size'));
            $company->page_size = Input::get('page_size');
            $company->live_preview = Input::get('live_preview') ? true : false;

            // Automatically disable live preview when using a large font
            $fonts = Cache::get('fonts')->filter(function ($font) use ($company) {
                if ($font->google_font) {
                    return false;
                }
                return $font->id == $company->header_font_id || $font->id == $company->body_font_id;
            });
            if ($company->live_preview && count($fonts)) {
                $company->live_preview = false;
                Session::flash('warning', trans('texts.live_preview_disabled'));
            }

            $labels = [];
            foreach (['item', 'description', 'unit_cost', 'quantity', 'line_total', 'terms', 'balance_due', 'partial_due', 'subtotal', 'paid_to_date', 'discount'] as $field) {
                $labels[$field] = Input::get("labels_{$field}");
            }
            $company->invoice_labels = json_encode($labels);

            $company->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/' . COMPANY_INVOICE_DESIGN);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveNotifications()
    {
        $user = Auth::user();
        $user->notify_sent = Input::get('notify_sent');
        $user->notify_viewed = Input::get('notify_viewed');
        $user->notify_paid = Input::get('notify_paid');
        $user->notify_approved = Input::get('notify_approved');
        $user->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/' . COMPANY_NOTIFICATIONS);
    }

    /**
     * @param UpdateCompanyRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateDetails(UpdateCompanyRequest $request)
    {
        $company = Auth::user()->loginaccount;
        $this->companyRepo->save($request->input(), $company);

        /* Logo image file */
        if ($uploaded = Input::file('logo')) {
            $path = Input::file('logo')->getRealPath();

            $disk = $company->getLogoDisk();
            if ($company->hasLogo()) {
                $disk->delete($company->logo);
            }

            $extension = strtolower($uploaded->getRelationOriginalExtension());
            if (empty(Document::$types[$extension]) && !empty(Document::$extraExtensions[$extension])) {
                $documentType = Document::$extraExtensions[$extension];
            } else {
                $documentType = $extension;
            }

            if (!in_array($documentType, ['jpeg', 'png', 'gif'])) {
                Session::flash('warning', 'Unsupported file type');
            } else {
                $documentTypeData = Document::$types[$documentType];

                $filePath = $uploaded->path();
                $size = filesize($filePath);

                if ($size / 1000 > MAX_DOCUMENT_SIZE) {
                    Session::flash('warning', 'File too large');
                } else {
                    if ($documentType != 'gif') {
                        $company->logo = $company->company_key . '.' . $documentType;

                        $imageSize = getimagesize($filePath);
                        $company->logo_width = $imageSize[0];
                        $company->logo_height = $imageSize[1];
                        $company->logo_size = $size;

                        // make sure image isn't interlaced
                        if (extension_loaded('fileinfo')) {
                            $image = Image::make($path);
                            $image->interlace(false);
                            $imageStr = (string)$image->encode($documentType);
                            $disk->put($company->logo, $imageStr);

                            $company->logo_size = strlen($imageStr);
                        } else {
                            $stream = fopen($filePath, 'r');
                            $disk->getDriver()->putStream($company->logo, $stream, ['mimetype' => $documentTypeData['mime']]);
                            fclose($stream);
                        }
                    } else {
                        if (extension_loaded('fileinfo')) {
                            $image = Image::make($path);
                            $image->resize(200, 120, function ($constraint) {
                                $constraint->aspectRatio();
                            });

                            $company->logo = $company->company_key . '.png';
                            $image = Image::canvas($image->width(), $image->height(), '#FFFFFF')->insert($image);
                            $imageStr = (string)$image->encode('png');
                            $disk->put($company->logo, $imageStr);

                            $company->logo_size = strlen($imageStr);
                            $company->logo_width = $image->width();
                            $company->logo_height = $image->height();
                        } else {
                            Session::flash('warning', 'Warning: To support gifs the fileinfo PHP extension needs to be enabled.');
                        }
                    }
                }
            }

            $company->save();
        }

        event(new UserSettingsChanged());

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/' . COMPANY_COMPANY_DETAILS);
    }

    /**
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function saveUserDetails()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $rules = ['email' => 'email|required|unique:users,email,' . $user->id . ',id'];
        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('settings/' . COMPANY_USER_DETAILS)
                ->withErrors($validator)
                ->withInput();
        } else {
            $user->first_name = trim(Input::get('first_name'));
            $user->last_name = trim(Input::get('last_name'));
            $user->username = trim(Input::get('email'));
            $user->email = trim(strtolower(Input::get('email')));
            $user->phone = trim(Input::get('phone'));

            if (Utils::isNinja()) {
                if (Input::get('referral_code') && !$user->referral_code) {
                    $user->referral_code = $this->companyRepo->getReferralCode();
                }
            }
            if (Utils::isNinjaDev()) {
                $user->dark_mode = Input::get('dark_mode') ? true : false;
            }

            $user->save();

            event(new UserSettingsChanged());
            Session::flash('message', trans('texts.updated_settings'));

            return Redirect::to('settings/' . COMPANY_USER_DETAILS);
        }
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveLocalization()
    {
        /** @var \App\Models\Company $company */
        $company = Auth::user()->loginaccount;

        $company->timezone_id = Input::get('timezone_id') ? Input::get('timezone_id') : null;
        $company->date_format_id = Input::get('date_format_id') ? Input::get('date_format_id') : null;
        $company->datetime_format_id = Input::get('datetime_format_id') ? Input::get('datetime_format_id') : null;
        $company->currency_id = Input::get('currency_id') ? Input::get('currency_id') : 1; // US Dollar
        $company->language_id = Input::get('language_id') ? Input::get('language_id') : 1; // English
        $company->military_time = Input::get('military_time') ? true : false;
        $company->show_currency_code = Input::get('show_currency_code') ? true : false;
        $company->start_of_week = Input::get('start_of_week') ? Input::get('start_of_week') : 0;
        $company->save();

        event(new UserSettingsChanged());

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/' . COMPANY_LOCALIZATION);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveOnlinePayments()
    {
        $company = Auth::user()->loginaccount;
        $company->token_billing_type_id = Input::get('token_billing_type_id');
        $company->auto_bill_on_due_date = boolval(Input::get('auto_bill_on_due_date'));
        $company->save();

        event(new UserSettingsChanged());

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/' . COMPANY_PAYMENTS);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeLogo()
    {
        $company = Auth::user()->loginaccount;
        if ($company->hasLogo()) {
            $company->getLogoDisk()->delete($company->logo);

            $company->logo = null;
            $company->logo_size = null;
            $company->logo_width = null;
            $company->logo_height = null;
            $company->save();

            Session::flash('message', trans('texts.removed_logo'));
        }

        return Redirect::to('settings/' . COMPANY_COMPANY_DETAILS);
    }

    /**
     * @return string
     */
    public function checkEmail()
    {
        $email = User::withTrashed()->where('email', '=', Input::get('email'))
            ->where('id', '<>', Auth::user()->id)
            ->first();

        if ($email) {
            return 'taken';
        } else {
            return 'available';
        }
    }

    /**
     * @return string
     */
    public function submitSignup()
    {
        $rules = [
            'new_first_name' => 'required',
            'new_last_name' => 'required',
            'new_password' => 'required|min:6',
            'new_email' => 'email|required|unique:users,email,' . Auth::user()->id . ',id',
        ];

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return '';
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->first_name = trim(Input::get('new_first_name'));
        $user->last_name = trim(Input::get('new_last_name'));
        $user->email = trim(strtolower(Input::get('new_email')));
        $user->username = $user->email;
        $user->password = bcrypt(trim(Input::get('new_password')));
        $user->registered = true;
        $user->save();

        $user->loginaccount->startTrial(PLAN_PRO);

        if (Input::get('go_pro') == 'true') {
            Session::set(REQUESTED_PRO_PLAN, true);
        }

        return "{$user->first_name} {$user->last_name}";
    }

    /**
     * @return mixed
     */
    public function doRegister()
    {
        $affiliate = Affiliate::where('affiliate_key', '=', SELF_HOST_AFFILIATE_KEY)->first();
        $email = trim(Input::get('email'));

        if (!$email || $email == TEST_USERNAME) {
            return RESULT_FAILURE;
        }

        $license = new License();
        $license->first_name = Input::get('first_name');
        $license->last_name = Input::get('last_name');
        $license->email = $email;
        $license->transaction_reference = Request::getClientIp();
        $license->license_key = Utils::generateLicense();
        $license->affiliate_id = $affiliate->id;
        $license->product_id = PRODUCT_SELF_HOST;
        $license->is_claimed = 1;
        $license->save();

        return RESULT_SUCCESS;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancelCompany()
    {
        if ($reason = trim(Input::get('reason'))) {
            $email = Auth::user()->email;
            $name = Auth::user()->getDisplayName();

            $data = [
                'text' => $reason,
            ];

            $subject = 'Invoice Ninja - Canceled Company';

            $this->userMailer->sendTo(CONTACT_EMAIL, $email, $name, $subject, 'contact', $data);
        }

        $user = Auth::user();
        $company = Auth::user()->loginaccount;
        \Log::info("Canceled Company: {$company->name} - {$user->email}");

        Document::scope()->each(function ($item, $key) {
            $item->delete();
        });

        $this->companyRepo->unlinkCompany($company);
        if ($company->corporation->companies->count() == 1) {
            $company->corporation->forceDelete();
        } else {
            $company->forceDelete();
        }

        Auth::logout();
        Session::flush();

        return Redirect::to('/')->with('clearGuestKey', true);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resendConfirmation()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->userMailer->sendConfirmation($user);

        return Redirect::to('/settings/' . COMPANY_USER_DETAILS)->with('message', trans('texts.confirmation_resent'));
    }

    /**
     * @param $plan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function startTrial($plan)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->isEligibleForTrial($plan)) {
            $user->loginaccount->startTrial($plan);
        }

        return Redirect::back()->with('message', trans('texts.trial_success'));
    }

    /**
     * @param $section
     * @param bool $subSection
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectLegacy($section, $subSection = false)
    {
        if ($section === 'details') {
            $section = COMPANY_COMPANY_DETAILS;
        } elseif ($section === 'payments') {
            $section = COMPANY_PAYMENTS;
        } elseif ($section === 'advanced_settings') {
            $section = $subSection;
            if ($section === 'token_management') {
                $section = COMPANY_API_TOKENS;
            }
        }

        if (!in_array($section, array_merge(Company::$basicSettings, Company::$advancedSettings))) {
            $section = COMPANY_COMPANY_DETAILS;
        }

        return Redirect::to("/settings/$section/", 301);
    }

    /**
     * @param TemplateService $templateService
     * @return \Illuminate\Http\Response
     */
    public function previewEmail(TemplateService $templateService)
    {
        $template = Input::get('template');
        $invoice = Invoice::scope()
            ->invoices()
            ->withTrashed()
            ->first();

        if (!$invoice) {
            return trans('texts.create_invoice_for_sample');
        }

        /** @var \App\Models\Company $company */
        $company = Auth::user()->loginaccount;
        $invitation = $invoice->invitations->first();

        // replace the variables with sample data
        $data = [
            'company' => $company,
            'invoice' => $invoice,
            'invitation' => $invitation,
            'link' => $invitation->getLink(),
            'relation' => $invoice->relation,
            'amount' => $invoice->amount
        ];

        // create the email view
        $view = 'emails.' . $company->getTemplateView(ENTITY_INVOICE) . '_html';
        $data = array_merge($data, [
            'body' => $templateService->processVariables($template, $data),
            'entityType' => ENTITY_INVOICE,
        ]);

        return Response::view($view, $data);
    }
}
