<?php namespace App\Ninja\Transformers;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Relation;

/**
 * @SWG\Definition(definition="Payment", required={"invoice_id"}, @SWG\Xml(name="Payment"))
 */

class PaymentTransformer extends EntityTransformer
{
    /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="amount", type="float", example=10, readOnly=true)
    * @SWG\Property(property="invoice_id", type="integer", example=1)
    */
    protected $defaultIncludes = [];

    protected $availableIncludes = [
        'relation',
        'invoice',
    ];


    public function __construct($company = null, $serializer = null, $invoice = null)
    {
        parent::__construct($company, $serializer);

        $this->invoice = $invoice;
    }

    public function includeInvoice(Payment $payment)
    {
        $transformer = new InvoiceTransformer($this->loginaccount, $this->serializer);
        return $this->includeItem($payment->invoice, $transformer, 'invoice');
    }

    public function includeRelation(Payment $payment)
    {
        $transformer = new RelationTransformer($this->loginaccount, $this->serializer);
        return $this->includeItem($payment->relation, $transformer, 'relation');
    }

    public function transform(Payment $payment)
    {
        return array_merge($this->getDefaults($payment), [
            'id' => (int) $payment->public_id,
            'amount' => (float) $payment->amount,
            'transaction_reference' => $payment->transaction_reference,
            'payment_date' => $payment->payment_date,
            'updated_at' => $this->getTimestamp($payment->updated_at),
            'archived_at' => $this->getTimestamp($payment->deleted_at),
            'is_deleted' => (bool) $payment->is_deleted,
            'payment_type_id' => (int) $payment->payment_type_id,
            'invoice_id' => (int) ($this->invoice ? $this->invoice->public_id : $payment->invoice->public_id),
            'invoice_number' => $this->invoice ? $this->invoice->invoice_number : $payment->invoice->invoice_number,
        ]);
    }
}
