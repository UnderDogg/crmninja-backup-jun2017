<?php namespace App\Ninja\Transformers;

use App\Models\Expense;

class ExpenseTransformer extends EntityTransformer
{
    public function __construct($company = null, $serializer = null, $relation = null)
    {
        parent::__construct($company, $serializer);
        
        $this->relation = $relation;
    }

    public function transform(Expense $expense)
    {
        return array_merge($this->getDefaults($expense), [
            'id' => (int) $expense->public_id,
            'private_notes' => $expense->private_notes,
            'public_notes' => $expense->public_notes,
            'should_be_invoiced' => (bool) $expense->should_be_invoiced,
            'updated_at' => $this->getTimestamp($expense->updated_at),
            'archived_at' => $this->getTimestamp($expense->deleted_at),
            'transaction_id' => $expense->transaction_id,
            'bank_id' => $expense->bank_id,
            'expense_currency_id' => (int) $expense->expense_currency_id,
            'amount' => (float) $expense->amount,
            'expense_date' => $expense->expense_date,
            'exchange_rate' => (float) $expense->exchange_rate,
            'invoice_currency_id' => (int) $expense->invoice_currency_id,
            'is_deleted' => (bool) $expense->is_deleted,
            'relation_id' => $this->relation ? $this->relation->public_id : (isset($expense->relation->public_id) ? (int) $expense->relation->public_id : null),
            'invoice_id' => isset($expense->invoice->public_id) ? (int) $expense->invoice->public_id : null,
            'vendor_id' => isset($expense->vendor->public_id) ? (int) $expense->vendor->public_id : null,
        ]);
    }
}