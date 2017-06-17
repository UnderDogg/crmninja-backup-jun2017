<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\CompanyRepository;
use App\Services\PaymentService;
use App\Models\Invoice;
use App\Models\Company;

/**
 * Class ChargeRenewalInvoices
 */
class ChargeRenewalInvoices extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:charge-renewals';

    /**
     * @var string
     */
    protected $description = 'Charge renewal invoices';

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var CompanyRepository
     */
    protected $companyRepo;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * ChargeRenewalInvoices constructor.
     * @param Mailer $mailer
     * @param CompanyRepository $repo
     * @param PaymentService $paymentService
     */
    public function __construct(Mailer $mailer, CompanyRepository $repo, PaymentService $paymentService)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->companyRepo = $repo;
        $this->paymentService = $paymentService;
    }

    public function fire()
    {
        $this->info(date('Y-m-d') . ' ChargeRenewalInvoices...');

        $ninjaCompany = $this->companyRepo->getNinjaCompany();
        $invoices = Invoice::whereCompanyId($ninjaCompany->id)
            ->whereDueDate(date('Y-m-d'))
            ->where('balance', '>', 0)
            ->with('relation')
            ->orderBy('id')
            ->get();

        $this->info(count($invoices) . ' invoices found');

        foreach ($invoices as $invoice) {

            // check if loginaccount has switched to free since the invoice was created
            $company = Company::find($invoice->relation->public_id);

            if (!$company) {
                continue;
            }

            $corporation = $company->corporation;
            if (!$corporation->plan || $corporation->plan == PLAN_FREE) {
                continue;
            }

            $this->info("Charging invoice {$invoice->invoice_number}");
            $this->paymentService->autoBillInvoice($invoice);
        }

        $this->info('Done');
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
