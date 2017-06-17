<?php namespace App\Http\Controllers;

use Auth;
use DB;
use View;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;

/**
 * Class DashboardController
 */
class DashboardController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $view_all = Auth::user()->hasPermission('view_all');
        $user_id = Auth::user()->id;

        // total_income, billed_customers, invoice_sent and active_customers
        $select = DB::raw(
            'COUNT(DISTINCT CASE WHEN ' . DB::getQueryGrammar()->wrap('invoices.id', true) . ' IS NOT NULL THEN ' . DB::getQueryGrammar()->wrap('customers.id', true) . ' ELSE null END) billed_customers,
            SUM(CASE WHEN ' . DB::getQueryGrammar()->wrap('invoices.invoice_status_id', true) . ' >= ' . INVOICE_STATUS_SENT . ' THEN 1 ELSE 0 END) invoices_sent,
            COUNT(DISTINCT ' . DB::getQueryGrammar()->wrap('customers.id', true) . ') active_customers'
        );
        $metrics = DB::table('companies')
            ->select($select)
            ->leftJoin('customers', 'companies.id', '=', 'customers.company_id')
            ->leftJoin('invoices', 'customers.id', '=', 'invoices.customer_id')
            ->where('companies.id', '=', Auth::user()->company_id)
            ->where('customers.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.is_recurring', '=', false)
            ->where('invoices.invoice_type_id', '=', INVOICE_TYPE_STANDARD);

        if (!$view_all) {
            $metrics = $metrics->where(function ($query) use ($user_id) {
                $query->where('invoices.user_id', '=', $user_id);
                $query->orwhere(function ($query) use ($user_id) {
                    $query->where('invoices.user_id', '=', null);
                    $query->where('customers.user_id', '=', $user_id);
                });
            });
        }

        $metrics = $metrics->groupBy('companies.id')
            ->first();

        $select = DB::raw(
            'SUM(' . DB::getQueryGrammar()->wrap('customers.paid_to_date', true) . ') as value,'
            . DB::getQueryGrammar()->wrap('customers.currency_id', true) . ' as currency_id'
        );
        $paidToDate = DB::table('companies')
            ->select($select)
            ->leftJoin('customers', 'companies.id', '=', 'customers.company_id')
            ->where('companies.id', '=', Auth::user()->company_id)
            ->where('customers.is_deleted', '=', false);

        if (!$view_all) {
            $paidToDate = $paidToDate->where('customers.user_id', '=', $user_id);
        }

        $paidToDate = $paidToDate->groupBy('companies.id')
            ->groupBy(DB::raw('CASE WHEN ' . DB::getQueryGrammar()->wrap('customers.currency_id', true) . ' IS NULL THEN CASE WHEN ' . DB::getQueryGrammar()->wrap('companies.currency_id', true) . ' IS NULL THEN 1 ELSE ' . DB::getQueryGrammar()->wrap('companies.currency_id', true) . ' END ELSE ' . DB::getQueryGrammar()->wrap('customers.currency_id', true) . ' END'))
            ->get();

        $select = DB::raw(
            'AVG(' . DB::getQueryGrammar()->wrap('invoices.amount', true) . ') as invoice_avg, '
            . DB::getQueryGrammar()->wrap('customers.currency_id', true) . ' as currency_id'
        );
        $averageInvoice = DB::table('companies')
            ->select($select)
            ->leftJoin('customers', 'companies.id', '=', 'customers.company_id')
            ->leftJoin('invoices', 'customers.id', '=', 'invoices.customer_id')
            ->where('companies.id', '=', Auth::user()->company_id)
            ->where('customers.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.invoice_type_id', '=', INVOICE_TYPE_STANDARD)
            ->where('invoices.is_recurring', '=', false);

        if (!$view_all) {
            $averageInvoice = $averageInvoice->where('invoices.user_id', '=', $user_id);
        }

        $averageInvoice = $averageInvoice->groupBy('companies.id')
            ->groupBy(DB::raw('CASE WHEN ' . DB::getQueryGrammar()->wrap('customers.currency_id', true) . ' IS NULL THEN CASE WHEN ' . DB::getQueryGrammar()->wrap('companies.currency_id', true) . ' IS NULL THEN 1 ELSE ' . DB::getQueryGrammar()->wrap('companies.currency_id', true) . ' END ELSE ' . DB::getQueryGrammar()->wrap('customers.currency_id', true) . ' END'))
            ->get();

        $select = DB::raw(
            'SUM(' . DB::getQueryGrammar()->wrap('customers.balance', true) . ') as value, '
            . DB::getQueryGrammar()->wrap('customers.currency_id', true) . ' as currency_id'
        );
        $balances = DB::table('companies')
            ->select($select)
            ->leftJoin('customers', 'companies.id', '=', 'customers.company_id')
            ->where('companies.id', '=', Auth::user()->company_id)
            ->where('customers.is_deleted', '=', false)
            ->groupBy('companies.id')
            ->groupBy(DB::raw('CASE WHEN ' . DB::getQueryGrammar()->wrap('customers.currency_id', true) . ' IS NULL THEN CASE WHEN ' . DB::getQueryGrammar()->wrap('companies.currency_id', true) . ' IS NULL THEN 1 ELSE ' . DB::getQueryGrammar()->wrap('companies.currency_id', true) . ' END ELSE ' . DB::getQueryGrammar()->wrap('customers.currency_id', true) . ' END'));

        if (!$view_all) {
            $balances->where('customers.user_id', '=', $user_id);
        }

        $balances = $balances->get();

        $activities = Activity::where('activities.company_id', '=', Auth::user()->company_id)
            ->where('activities.activity_type_id', '>', 0);

        if (!$view_all) {
            $activities = $activities->where('activities.user_id', '=', $user_id);
        }

        $activities = $activities->orderBy('activities.created_at', 'desc')
            ->with('relation.contacts', 'user', 'invoice', 'payment', 'credit', 'loginaccount', 'task')
            ->take(50)
            ->get();

        $pastDue = DB::table('invoices')
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('relations', 'relations.id', '=', 'customers.relation_id')
            ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
            ->where('invoices.company_id', '=', Auth::user()->company_id)
            ->where('customers.deleted_at', '=', null)
            ->where('contacts.deleted_at', '=', null)
            ->where('invoices.is_recurring', '=', false)
            //->where('invoices.is_quote', '=', false)
            ->where('invoices.quote_invoice_id', '=', null)
            ->where('invoices.balance', '>', 0)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.deleted_at', '=', null)
            ->where('contacts.is_primary', '=', true)
            ->where('invoices.due_date', '<', date('Y-m-d'));

        if (!$view_all) {
            $pastDue = $pastDue->where('invoices.user_id', '=', $user_id);
        }

        $pastDue = $pastDue->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'customers.name as relation_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'customers.currency_id', 'customers.public_id as relation_public_id', 'customers.user_id as relation_user_id', 'invoice_type_id'])
            ->orderBy('invoices.due_date', 'asc')
            ->take(50)
            ->get();

        $upcoming = DB::table('invoices')
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('contacts', 'contacts.relation_id', '=', 'customers.id')
            ->where('invoices.company_id', '=', Auth::user()->company_id)
            ->where('customers.deleted_at', '=', null)
            ->where('contacts.deleted_at', '=', null)
            ->where('invoices.deleted_at', '=', null)
            ->where('invoices.is_recurring', '=', false)
            //->where('invoices.is_quote', '=', false)
            ->where('invoices.quote_invoice_id', '=', null)
            ->where('invoices.balance', '>', 0)
            ->where('invoices.is_deleted', '=', false)
            ->where('contacts.is_primary', '=', true)
            ->where('invoices.due_date', '>=', date('Y-m-d'))
            ->orderBy('invoices.due_date', 'asc');

        if (!$view_all) {
            $upcoming = $upcoming->where('invoices.user_id', '=', $user_id);
        }

        $upcoming = $upcoming->take(50)
            ->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'customers.name as relation_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'customers.currency_id', 'customers.public_id as relation_public_id', 'customers.user_id as relation_user_id', 'invoice_type_id'])
            ->get();

        $payments = DB::table('payments')
            ->leftJoin('customers', 'customers.id', '=', 'payments.customer_id')
            ->leftJoin('contacts', 'contacts.relation_id', '=', 'customers.id')
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('payments.company_id', '=', Auth::user()->company_id)
            ->where('payments.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('customers.is_deleted', '=', false)
            ->where('contacts.deleted_at', '=', null)
            ->where('contacts.is_primary', '=', true);

        if (!$view_all) {
            $payments = $payments->where('payments.user_id', '=', $user_id);
        }

        $payments = $payments->select(['payments.payment_date', 'payments.amount', 'invoices.public_id', 'invoices.invoice_number', 'customers.name as relation_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'customers.currency_id', 'customers.public_id as relation_public_id', 'customers.user_id as relation_user_id'])
            ->orderBy('payments.payment_date', 'desc')
            ->take(50)
            ->get();

        $hasQuotes = false;
        foreach ([$upcoming, $pastDue] as $data) {
            foreach ($data as $invoice) {
                if ($invoice->invoice_type_id == INVOICE_TYPE_QUOTE) {
                    $hasQuotes = true;
                }
            }
        }

        $data = [
            'company' => Auth::user()->loginaccount,
            'paidToDate' => $paidToDate,
            'balances' => $balances,
            'averageInvoice' => $averageInvoice,
            'invoicesSent' => $metrics ? $metrics->invoices_sent : 0,
            'activeCustomers' => $metrics ? $metrics->active_customers : 0,
            'activities' => $activities,
            'pastDue' => $pastDue,
            'upcoming' => $upcoming,
            'payments' => $payments,
            'title' => trans('texts.dashboard'),
            'hasQuotes' => $hasQuotes,
        ];

        return View::make('dashboard', $data);
    }
}
