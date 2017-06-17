<?php namespace App\Http\Controllers;

use Input;
use Session;
use Utils;
use View;
use Cache;
use App\Models\Invoice;
use App\Models\Relation;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Mailers\ContactMailer;
use App\Services\PaymentService;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;

class PaymentController extends BaseController
{
    /**
     * @var string
     */
    protected $entityType = ENTITY_PAYMENT;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepo;

    /**
     * @var ContactMailer
     */
    protected $contactMailer;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * PaymentController constructor.
     *
     * @param PaymentRepository $paymentRepo
     * @param ContactMailer $contactMailer
     * @param PaymentService $paymentService
     */
    public function __construct(
        PaymentRepository $paymentRepo,
        ContactMailer $contactMailer,
        PaymentService $paymentService
    )
    {
        $this->paymentRepo = $paymentRepo;
        $this->contactMailer = $contactMailer;
        $this->paymentService = $paymentService;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        return View::make('list', [
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'sortCol' => '7',
            'columns' => Utils::trans([
              'checkbox',
              'invoice',
              'relation',
              'transaction_reference',
              'method',
              'source',
              'payment_amount',
              'payment_date',
              'status',
              ''
            ]),
        ]);
    }

    /**
     * @param null $relationPublicId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($relationPublicId = null)
    {
        return $this->paymentService->getDatatable($relationPublicId, Input::get('sSearch'));
    }

    /**
     * @param PaymentRequest $request
     * @return \Illuminate\Contracts\View\View
     */
    public function create(PaymentRequest $request)
    {
        $invoices = Invoice::scope()
                    ->invoiceType(INVOICE_TYPE_STANDARD)
                    ->where('is_recurring', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->with('relation', 'invoice_status')
                    ->orderBy('invoice_number')->get();

        $data = [
            'relationPublicId' => Input::old('relation') ? Input::old('relation') : ($request->relation_id ?: 0),
            'invoicePublicId' => Input::old('invoice') ? Input::old('invoice') : ($request->invoice_id ?: 0),
            'invoice' => null,
            'invoices' => $invoices,
            'payment' => null,
            'method' => 'POST',
            'url' => 'payments',
            'title' => trans('texts.new_payment'),
            'paymentTypeId' => Input::get('paymentTypeId'),
            'relations' => Relation::scope()->with('contacts')->orderBy('name')->get(), ];

        return View::make('payments.edit', $data);
    }

    /**
     * @param $publicId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show($publicId)
    {
        Session::reflash();

        return redirect()->to("payments/{$publicId}/edit");
    }

    /**
     * @param PaymentRequest $request
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(PaymentRequest $request)
    {
        $payment = $request->entity();

        $payment->payment_date = Utils::fromSqlDate($payment->payment_date);

        $data = [
            'relation' => null,
            'invoice' => null,
            'invoices' => Invoice::scope()->invoiceType(INVOICE_TYPE_STANDARD)->where('is_recurring', '=', false)
                            ->with('relation', 'invoice_status')->orderBy('invoice_number')->get(),
            'payment' => $payment,
            'method' => 'PUT',
            'url' => 'payments/'.$payment->public_id,
            'title' => trans('texts.edit_payment'),
            'paymentTypes' => Cache::get('paymentTypes'),
            'relations' => Relation::scope()->with('contacts')->orderBy('name')->get(), ];

        return View::make('payments.edit', $data);
    }

    /**
     * @param CreatePaymentRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreatePaymentRequest $request)
    {
        $input = $request->input();

        $input['invoice_id'] = Invoice::getPrivateId($input['invoice']);
        $input['relation_id'] = Relation::getPrivateId($input['relation']);
        $payment = $this->paymentRepo->save($input);

        if (Input::get('email_receipt')) {
            $this->contactMailer->sendPaymentConfirmation($payment);
            Session::flash('message', trans('texts.created_payment_emailed_relation'));
        } else {
            Session::flash('message', trans('texts.created_payment'));
        }

        return redirect()->to($payment->relation->getRoute());
    }

    /**
     * @param UpdatePaymentRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdatePaymentRequest $request)
    {
        $payment = $this->paymentRepo->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_payment'));

        return redirect()->to($payment->getRoute());
    }

    /**
     * @return mixed
     */
    public function bulk()
    {
        $action = Input::get('action');
        $amount = Input::get('amount');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->paymentService->bulk($ids, $action, ['amount'=>$amount]);

        if ($count > 0) {
            $message = Utils::pluralize($action=='refund'?'refunded_payment':$action.'d_payment', $count);
            Session::flash('message', $message);
        }

        return redirect()->to('payments');
    }
}
