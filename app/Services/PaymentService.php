<?php namespace App\Services;

use App\Models\Invoice;
use Utils;
use Auth;
use App\Models\Company;
use App\Models\Relation;
use App\Models\Activity;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\CompanyRepository;
use App\Ninja\Datatables\PaymentDatatable;

class PaymentService extends BaseService
{
    /**
     * PaymentService constructor.
     *
     * @param PaymentRepository $paymentRepo
     * @param CompanyRepository $companyRepo
     * @param DatatableService $datatableService
     */
    public function __construct(
        PaymentRepository $paymentRepo,
        CompanyRepository $companyRepo,
        DatatableService $datatableService
    )
    {
        $this->datatableService = $datatableService;
        $this->paymentRepo = $paymentRepo;
        $this->companyRepo = $companyRepo;
    }

    /**
     * @return PaymentRepository
     */
    protected function getRepo()
    {
        return $this->paymentRepo;
    }

    /**
     * @param Invoice $invoice
     * @return bool
     */
    public function autoBillInvoice(Invoice $invoice)
    {
        /** @var \App\Models\Relation $relation */
        $relation = $invoice->relation;

        /** @var \App\Models\Company $company */
        $company = $relation->loginaccount;

        /** @var \App\Models\Invitation $invitation */
        $invitation = $invoice->invitations->first();

        if (!$invitation) {
            return false;
        }

        if ($credits = $relation->credits->sum('balance')) {
            $balance = $invoice->balance;
            $amount = min($credits, $balance);
            $data = [
                'payment_type_id' => PAYMENT_TYPE_CREDIT,
                'invoice_id' => $invoice->id,
                'relation_id' => $relation->id,
                'amount' => $amount,
            ];
            $payment = $this->paymentRepo->save($data);
            if ($amount == $balance) {
                return $payment;
            }
        }

        $paymentDriver = $company->paymentDriver($invitation, GATEWAY_TYPE_TOKEN);

        if (!$paymentDriver) {
            return false;
        }

        $customer = $paymentDriver->customer();

        if (!$customer) {
            return false;
        }

        $paymentMethod = $customer->default_payment_method;

        if ($paymentMethod->requiresDelayedAutoBill()) {
            $invoiceDate = \DateTime::createFromFormat('Y-m-d', $invoice->invoice_date);
            $minDueDate = clone $invoiceDate;
            $minDueDate->modify('+10 days');

            if (date_create() < $minDueDate) {
                // Can't auto bill now
                return false;
            }

            if ($invoice->partial > 0) {
                // The amount would be different than the amount in the email
                return false;
            }

            $firstUpdate = Activity::where('invoice_id', '=', $invoice->id)
                ->where('activity_type_id', '=', ACTIVITY_TYPE_UPDATE_INVOICE)
                ->first();

            if ($firstUpdate) {
                $backup = json_decode($firstUpdate->json_backup);

                if ($backup->balance != $invoice->balance || $backup->due_date != $invoice->due_date) {
                    // It's changed since we sent the email can't bill now
                    return false;
                }
            }

            if ($invoice->payments->count()) {
                // ACH requirements are strict; don't auto bill this
                return false;
            }
        }

        return $paymentDriver->completeOnsitePurchase(false, $paymentMethod);
    }

    public function getDatatable($relationPublicId, $search)
    {
        $datatable = new PaymentDatatable(!$relationPublicId, $relationPublicId);
        $query = $this->paymentRepo->find($relationPublicId, $search);

        if (!Utils::hasPermission('view_all')) {
            $query->where('payments.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }


    public function bulk($ids, $action, $params = [])
    {
        if ($action == 'refund') {
            if (!$ids) {
                return 0;
            }

            $payments = $this->getRepo()->findByPublicIdsWithTrashed($ids);
            $successful = 0;

            foreach ($payments as $payment) {
                if (Auth::user()->can('edit', $payment)) {
                    $amount = !empty($params['amount']) ? floatval($params['amount']) : null;
                    $accountGateway = $payment->account_gateway;
                    $paymentDriver = $accountGateway->paymentDriver();
                    if ($paymentDriver->refundPayment($payment, $amount)) {
                        $successful++;
                    }
                }
            }

            return $successful;
        } else {
            return parent::bulk($ids, $action);
        }
    }
}
