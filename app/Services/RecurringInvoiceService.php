<?php namespace App\Services;

use Auth;
use Utils;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Datatables\RecurringInvoiceDatatable;

class RecurringInvoiceService extends BaseService
{
    protected $invoiceRepo;
    protected $datatableService;

    public function __construct(InvoiceRepository $invoiceRepo, DatatableService $datatableService)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->datatableService = $datatableService;
    }

    public function getDatatable($companyId, $relationPublicId = null, $entityType, $search)
    {
        $datatable = new RecurringInvoiceDatatable(!$relationPublicId, $relationPublicId);
        $query = $this->invoiceRepo->getRecurringInvoices($companyId, $relationPublicId, $search);

        if (!Utils::hasPermission('view_all')) {
            $query->where('invoices.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }

}
