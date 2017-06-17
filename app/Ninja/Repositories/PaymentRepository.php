<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use App\Models\Payment;
use App\Models\Credit;
use App\Models\Invoice;

class PaymentRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Payment';
    }

    public function find($relationPublicId = null, $filter = null)
    {
        $query = DB::table('payments')
                    ->join('companies', 'companies.id', '=', 'payments.company_id')
                    ->join('relations', 'relations.id', '=', 'payments.relation_id')
                    ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->join('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->join('payment_statuses', 'payment_statuses.id', '=', 'payments.payment_status_id')
                    ->leftJoin('payment_types', 'payment_types.id', '=', 'payments.payment_type_id')
                    ->leftJoin('account_gateways', 'account_gateways.id', '=', 'payments.account_gateway_id')
                    ->leftJoin('gateways', 'gateways.id', '=', 'account_gateways.gateway_id')
                    ->where('payments.company_id', '=', \Auth::user()->company_id)
                    ->where('relations.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.is_deleted', '=', false)
                    ->select('payments.public_id',
                        DB::raw('COALESCE(relations.currency_id, companies.currency_id) currency_id'),
                        DB::raw('COALESCE(relations.country_id, companies.country_id) country_id'),
                        'payments.transaction_reference',
                        DB::raw("COALESCE(NULLIF(relations.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) relation_name"),
                        'relations.public_id as relation_public_id',
                        'relations.user_id as relation_user_id',
                        'payments.amount',
                        'payments.payment_date',
                        'payments.payment_status_id',
                        'payments.payment_type_id',
                        'invoices.public_id as invoice_public_id',
                        'invoices.user_id as invoice_user_id',
                        'invoices.invoice_number',
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'payment_types.name as payment_type',
                        'payments.account_gateway_id',
                        'payments.deleted_at',
                        'payments.is_deleted',
                        'payments.user_id',
                        'payments.refunded',
                        'payments.expiration',
                        'payments.last4',
                        'payments.email',
                        'payments.routing_number',
                        'payments.bank_name',
                        'invoices.is_deleted as invoice_is_deleted',
                        'gateways.name as gateway_name',
                        'gateways.id as gateway_id',
                        'payment_statuses.name as payment_status_name'
                    );

        if (!\Session::get('show_trash:payment')) {
            $query->where('payments.deleted_at', '=', null);
        }

        if ($relationPublicId) {
            $query->where('relations.public_id', '=', $relationPublicId);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('relations.name', 'like', '%'.$filter.'%')
                      ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%')
                      ->orWhere('payments.transaction_reference', 'like', '%'.$filter.'%')
                      ->orWhere('gateways.name', 'like', '%'.$filter.'%')
                      ->orWhere('payment_types.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function findForContact($contactId = null, $filter = null)
    {
        $query = DB::table('payments')
                    ->join('companies', 'companies.id', '=', 'payments.company_id')
                    ->join('relations', 'relations.id', '=', 'payments.relation_id')
                    ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->join('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->join('payment_statuses', 'payment_statuses.id', '=', 'payments.payment_status_id')
                    ->leftJoin('invitations', function ($join) {
                        $join->on('invitations.invoice_id', '=', 'invoices.id')
                             ->on('invitations.contact_id', '=', 'contacts.id');
                    })
                    ->leftJoin('payment_types', 'payment_types.id', '=', 'payments.payment_type_id')
                    ->where('relations.is_deleted', '=', false)
                    ->where('payments.is_deleted', '=', false)
                    ->where('invitations.deleted_at', '=', null)
                    ->where('invoices.deleted_at', '=', null)
                    ->where('invitations.contact_id', '=', $contactId)
                    ->select(
                        DB::raw('COALESCE(relations.currency_id, companies.currency_id) currency_id'),
                        DB::raw('COALESCE(relations.country_id, companies.country_id) country_id'),
                        'invitations.invitation_key',
                        'payments.public_id',
                        'payments.transaction_reference',
                        DB::raw("COALESCE(NULLIF(relations.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) relation_name"),
                        'relations.public_id as relation_public_id',
                        'payments.amount',
                        'payments.payment_date',
                        'payments.payment_type_id',
                        'invoices.public_id as invoice_public_id',
                        'invoices.invoice_number',
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'payment_types.name as payment_type',
                        'payments.account_gateway_id',
                        'payments.refunded',
                        'payments.expiration',
                        'payments.last4',
                        'payments.email',
                        'payments.routing_number',
                        'payments.bank_name',
                        'payments.payment_status_id',
                        'payment_statuses.name as payment_status_name'
                    );

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('relations.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($input, $payment = null)
    {
        $publicId = isset($input['public_id']) ? $input['public_id'] : false;

        if ($payment) {
            // do nothing
        } elseif ($publicId) {
            $payment = Payment::scope($publicId)->firstOrFail();
            \Log::warning('Entity not set in payment repo save');
        } else {
            $payment = Payment::createNew();
        }

        $paymentTypeId = false;
        if (isset($input['payment_type_id'])) {
            $paymentTypeId = $input['payment_type_id'] ? $input['payment_type_id'] : null;
            $payment->payment_type_id = $paymentTypeId;
        }

        if (isset($input['payment_date_sql'])) {
            $payment->payment_date = $input['payment_date_sql'];
        } elseif (isset($input['payment_date'])) {
            $payment->payment_date = Utils::toSqlDate($input['payment_date']);
        } else {
            $payment->payment_date = date('Y-m-d');
        }

        if (isset($input['transaction_reference'])) {
            $payment->transaction_reference = trim($input['transaction_reference']);
        }

        if (!$publicId) {
            $relationId = $input['relation_id'];
            $amount = Utils::parseFloat($input['amount']);

            if ($paymentTypeId == PAYMENT_TYPE_CREDIT) {
                $credits = Credit::scope()->where('relation_id', '=', $relationId)
                            ->where('balance', '>', 0)->orderBy('created_at')->get();

                $remaining = $amount;
                foreach ($credits as $credit) {
                    $remaining -= $credit->apply($remaining);
                    if ( ! $remaining) {
                        break;
                    }
                }
            }

            $payment->invoice_id = $input['invoice_id'];
            $payment->relation_id = $relationId;
            $payment->amount = $amount;
        }

        $payment->save();

        return $payment;
    }

    public function delete($payment)
    {
        if ($payment->invoice->is_deleted) {
            return false;
        }

        parent::delete($payment);
    }

    public function restore($payment)
    {
        if ($payment->invoice->is_deleted) {
            return false;
        }

        parent::restore($payment);
    }
}
