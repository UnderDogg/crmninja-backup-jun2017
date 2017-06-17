<?php namespace App\Http\Requests;

use App\Models\Invoice;

class CreatePaymentAPIRequest extends PaymentRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_PAYMENT);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ( ! $this->invoice_id || ! $this->amount) {
            return [
                'invoice_id' => 'required',
                'amount' => 'required',
            ];
        }

        $invoice = Invoice::scope($this->invoice_id)
            ->invoices()
            ->firstOrFail();

        $this->merge([
            'invoice_id' => $invoice->id,
            'relation_id' => $invoice->relation->id,
        ]);

        $rules = [
            'amount' => "required|less_than:{$invoice->balance}|positive",
        ];

        if ($this->payment_type_id == PAYMENT_TYPE_CREDIT) {
            $rules['payment_type_id'] = 'has_credit:' . $invoice->relation->public_id . ',' . $this->amount;
        }

        return $rules;
    }
}