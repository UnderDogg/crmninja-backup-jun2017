<?php namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\CompanyRepository;
use App\Ninja\Repositories\InvoiceRepository;

/**
 * Class SendReminders
 */
class SendReminders extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:send-reminders';

    /**
     * @var string
     */
    protected $description = 'Send reminder emails';

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;
    
    /**
     * @var companyRepository
     */
    protected $companyRepo;

    /**
     * SendReminders constructor.
     * @param Mailer $mailer
     * @param InvoiceRepository $invoiceRepo
     * @param companyRepository $companyRepo
     */
    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, CompanyRepository $companyRepo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->companyRepo = $companyRepo;
    }

    public function fire()
    {
        $this->info(date('Y-m-d') . ' Running SendReminders...');

        $companies = $this->companyRepo->findWithReminders();
        $this->info(count($companies) . ' companies found');

        /** @var \App\Models\Company $company */
        foreach ($companies as $company) {
            if (!$company->hasFeature(FEATURE_EMAIL_TEMPLATES_REMINDERS)) {
                continue;
            }

            $invoices = $this->invoiceRepo->findNeedingReminding($company);
            $this->info($company->name . ': ' . count($invoices) . ' invoices found');

            /** @var Invoice $invoice */
            foreach ($invoices as $invoice) {
                if ($reminder = $company->getInvoiceReminder($invoice)) {
                    $this->info('Send to ' . $invoice->id);
                    $this->mailer->sendInvoice($invoice, $reminder);
                }
            }
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
