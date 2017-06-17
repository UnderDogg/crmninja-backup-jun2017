<?php namespace App\Http\Controllers;

use Auth;
use DB;

class DashboardApiController extends BaseAPIController
{
    public function index()
    {
        $view_all = Auth::user()->hasPermission('view_all');
        $user_id = Auth::user()->id;

        // total_income, billed_relations, invoice_sent and active_relations
        $select = DB::raw('COUNT(DISTINCT CASE WHEN invoices.id IS NOT NULL THEN relations.id ELSE null END) billed_relations,
                        SUM(CASE WHEN invoices.invoice_status_id >= '.INVOICE_STATUS_SENT.' THEN 1 ELSE 0 END) invoices_sent,
                        COUNT(DISTINCT relations.id) active_relations');
        $metrics = DB::table('companies')
            ->select($select)
            ->leftJoin('relations', 'companies.id', '=', 'relations.company_id')
            ->leftJoin('invoices', 'relations.id', '=', 'invoices.relation_id')
            ->where('companies.id', '=', Auth::user()->company_id)
            ->where('relations.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.is_recurring', '=', false)
            ->where('invoices.invoice_type_id', '=', false);

        if(!$view_all){
            $metrics = $metrics->where(function($query) use($user_id){
                $query->where('invoices.user_id', '=', $user_id);
                $query->orwhere(function($query) use($user_id){
                    $query->where('invoices.user_id', '=', null);
                    $query->where('relations.user_id', '=', $user_id);
                });
            });
        }

        $metrics = $metrics->groupBy('companies.id')
            ->first();

        $select = DB::raw('SUM(relations.paid_to_date) as value, relations.currency_id as currency_id');
        $paidToDate = DB::table('companies')
            ->select($select)
            ->leftJoin('relations', 'companies.id', '=', 'relations.company_id')
            ->where('companies.id', '=', Auth::user()->company_id)
            ->where('relations.is_deleted', '=', false);

        if(!$view_all){
            $paidToDate = $paidToDate->where('relations.user_id', '=', $user_id);
        }

        $paidToDate = $paidToDate->groupBy('companies.id')
            ->groupBy(DB::raw('CASE WHEN relations.currency_id IS NULL THEN CASE WHEN companies.currency_id IS NULL THEN 1 ELSE companies.currency_id END ELSE relations.currency_id END'))
            ->get();

        $select = DB::raw('AVG(invoices.amount) as invoice_avg, relations.currency_id as currency_id');
        $averageInvoice = DB::table('companies')
            ->select($select)
            ->leftJoin('relations', 'companies.id', '=', 'relations.company_id')
            ->leftJoin('invoices', 'relations.id', '=', 'invoices.relation_id')
            ->where('companies.id', '=', Auth::user()->company_id)
            ->where('relations.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.invoice_type_id', '=', INVOICE_TYPE_STANDARD)
            ->where('invoices.is_recurring', '=', false);

        if(!$view_all){
            $averageInvoice = $averageInvoice->where('invoices.user_id', '=', $user_id);
        }

        $averageInvoice = $averageInvoice->groupBy('companies.id')
            ->groupBy(DB::raw('CASE WHEN relations.currency_id IS NULL THEN CASE WHEN companies.currency_id IS NULL THEN 1 ELSE companies.currency_id END ELSE relations.currency_id END'))
            ->get();

        $select = DB::raw('SUM(relations.balance) as value, relations.currency_id as currency_id');
        $balances = DB::table('companies')
            ->select($select)
            ->leftJoin('relations', 'companies.id', '=', 'relations.company_id')
            ->where('companies.id', '=', Auth::user()->company_id)
            ->where('relations.is_deleted', '=', false)
            ->groupBy('companies.id')
            ->groupBy(DB::raw('CASE WHEN relations.currency_id IS NULL THEN CASE WHEN companies.currency_id IS NULL THEN 1 ELSE companies.currency_id END ELSE relations.currency_id END'));

        if (!$view_all) {
            $balances->where('relations.user_id', '=', $user_id);
        }

        $balances = $balances->get();

        $pastDue = DB::table('invoices')
                    ->leftJoin('relations', 'relations.id', '=', 'invoices.relation_id')
                    ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->where('invoices.company_id', '=', Auth::user()->company_id)
                    ->where('relations.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', false)
                    //->where('invoices.is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('invoices.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '<', date('Y-m-d'));

        if(!$view_all){
            $pastDue = $pastDue->where('invoices.user_id', '=', $user_id);
        }

        $pastDue = $pastDue->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'relations.name as relation_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'relations.currency_id', 'relations.public_id as relation_public_id', 'relations.user_id as relation_user_id', 'invoice_type_id'])
                    ->orderBy('invoices.due_date', 'asc')
                    ->take(50)
                    ->get();

        $upcoming = DB::table('invoices')
                    ->leftJoin('relations', 'relations.id', '=', 'invoices.relation_id')
                    ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->where('invoices.company_id', '=', Auth::user()->company_id)
                    ->where('relations.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', false)
                    //->where('invoices.is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '>=', date('Y-m-d'))
                    ->orderBy('invoices.due_date', 'asc');

        if(!$view_all){
            $upcoming = $upcoming->where('invoices.user_id', '=', $user_id);
        }

        $upcoming = $upcoming->take(50)
                    ->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'relations.name as relation_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'relations.currency_id', 'relations.public_id as relation_public_id', 'relations.user_id as relation_user_id', 'invoice_type_id'])
                    ->get();

        $payments = DB::table('payments')
                    ->leftJoin('relations', 'relations.id', '=', 'payments.relation_id')
                    ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->where('payments.company_id', '=', Auth::user()->company_id)
                    ->where('payments.is_deleted', '=', false)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('relations.is_deleted', '=', false)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true);

        if(!$view_all){
            $payments = $payments->where('payments.user_id', '=', $user_id);
        }

        $payments = $payments->select(['payments.payment_date', 'payments.amount', 'invoices.public_id', 'invoices.invoice_number', 'relations.name as relation_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'relations.currency_id', 'relations.public_id as relation_public_id', 'relations.user_id as relation_user_id'])
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
                'id' => 1,
                'paidToDate' => $paidToDate[0]->value ? $paidToDate[0]->value : 0,
                'paidToDateCurrency' => $paidToDate[0]->currency_id ? $paidToDate[0]->currency_id : 0,
                'balances' => $balances[0]->value ? $balances[0]->value : 0,
                'balancesCurrency' => $balances[0]->currency_id ? $balances[0]->currency_id : 0,
                'averageInvoice' => $averageInvoice[0]->invoice_avg ? $averageInvoice[0]->invoice_avg : 0,
                'averageInvoiceCurrency' => $averageInvoice[0]->currency_id ? $averageInvoice[0]->currency_id : 0,
                'invoicesSent' => $metrics ? $metrics->invoices_sent : 0,
                'activeRelations' => $metrics ? $metrics->active_relations : 0,
            ];



            return $this->response($data);

    }
}
