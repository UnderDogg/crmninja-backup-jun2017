<?php namespace App\Http\Controllers;

use Auth;
use Input;
use Redirect;
use Utils;
use View;
use Cache;
use Session;
use App\Models\Company;
use App\Models\Relation;
use App\Models\Country;
use App\Models\InvoiceDesign;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\RelationRepository;
use App\Services\InvoiceService;
use App\Http\Requests\InvoiceRequest;

class QuoteController extends BaseController
{
    protected $mailer;
    protected $invoiceRepo;
    protected $relationRepo;
    protected $invoiceService;
    protected $entityType = ENTITY_INVOICE;

    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, RelationRepository $relationRepo, InvoiceService $invoiceService)
    {
        // parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->relationRepo = $relationRepo;
        $this->invoiceService = $invoiceService;
    }

    public function index()
    {
        $data = [
          'title' => trans('texts.quotes'),
          'entityType' => ENTITY_QUOTE,
          'sortCol' => '3',
          'columns' => Utils::trans([
            'checkbox',
            'quote_number',
            'relation',
            'quote_date',
            'quote_total',
            'valid_until',
            'status',
            'action'
          ]),
        ];

        return response()->view('list', $data);
    }

    public function getDatatable($relationPublicId = null)
    {
        $companyId = Auth::user()->company_id;
        $search = Input::get('sSearch');

        return $this->invoiceService->getDatatable($companyId, $relationPublicId, ENTITY_QUOTE, $search);
    }

    public function create(InvoiceRequest $request, $relationPublicId = 0)
    {
        if (!Utils::hasFeature(FEATURE_QUOTES)) {
            return Redirect::to('/invoices/create');
        }

        $company = Auth::user()->loginaccount;
        $relationId = null;
        if ($relationPublicId) {
            $relationId = Relation::getPrivateId($relationPublicId);
        }
        $invoice = $company->createInvoice(ENTITY_QUOTE, $relationId);
        $invoice->public_id = 0;

        $data = [
            'entityType' => $invoice->getEntityType(),
            'invoice' => $invoice,
            'data' => Input::old('data'),
            'method' => 'POST',
            'url' => 'invoices',
            'title' => trans('texts.new_quote'),
        ];
        $data = array_merge($data, self::getViewModel());

        return View::make('invoices.edit', $data);
    }

    private static function getViewModel()
    {
        // Tax rate $options
        $company = Auth::user()->loginaccount;
        $rates = TaxRate::scope()->orderBy('name')->get();
        $options = [];
        $defaultTax = false;

        foreach ($rates as $rate) {
            $options[$rate->rate . ' ' . $rate->name] = $rate->name . ' ' . ($rate->rate+0) . '%';

            // load default invoice tax
            if ($rate->id == $company->default_tax_rate_id) {
                $defaultTax = $rate;
            }
        }

        return [
          'entityType' => ENTITY_QUOTE,
          'company' => Auth::user()->loginaccount,
          'products' => Product::scope()->orderBy('id')->get(['product_key', 'notes', 'cost', 'qty']),
          'taxRateOptions' => $options,
          'defaultTax' => $defaultTax,
          'countries' => Cache::get('countries'),
          'relations' => Relation::scope()->with('contacts', 'country')->orderBy('name')->get(),
          'taxRates' => TaxRate::scope()->orderBy('name')->get(),
          'currencies' => Cache::get('currencies'),
          'sizes' => Cache::get('sizes'),
          'paymentTerms' => Cache::get('paymentTerms'),
          'languages' => Cache::get('languages'),
          'industries' => Cache::get('industries'),
          'invoiceDesigns' => InvoiceDesign::getDesigns(),
          'invoiceFonts' => Cache::get('fonts'),
          'invoiceLabels' => Auth::user()->loginaccount->getInvoiceLabels(),
          'isRecurring' => false,
        ];
    }

    public function bulk()
    {
        $action = Input::get('bulk_action') ?: Input::get('action');;
        $ids = Input::get('bulk_public_id') ?: (Input::get('public_id') ?: Input::get('ids'));

        if ($action == 'convert') {
            $invoice = Invoice::with('invoice_items')->scope($ids)->firstOrFail();
            $clone = $this->invoiceService->convertQuote($invoice);

            Session::flash('message', trans('texts.converted_to_invoice'));
            return Redirect::to('invoices/'.$clone->public_id);
        }

        $count = $this->invoiceService->bulk($ids, $action);

        if ($count > 0) {
            $key = $action == 'markSent' ? 'updated_quote' : "{$action}d_quote";
            $message = Utils::pluralize($key, $count);
            Session::flash('message', $message);
        }

        if ($action == 'restore' && $count == 1) {
            return Redirect::to('quotes/'.Utils::getFirst($ids));
        } else {
            return Redirect::to('quotes');
        }
    }

    public function approve($invitationKey)
    {
        $invitation = Invitation::with('invoice.invoice_items', 'invoice.invitations')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;

        $invitationKey = $this->invoiceService->approveQuote($invoice, $invitation);
        Session::flash('message', trans('texts.quote_is_approved'));

        return Redirect::to("view/{$invitationKey}");
    }
}
