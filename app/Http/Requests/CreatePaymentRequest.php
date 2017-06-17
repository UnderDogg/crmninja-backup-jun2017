<?php namespace App\Http\Requests;

use App\Models\Invoice;

class CreatePaymentRequest extends PaymentRequest
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
        $input = $this->input();
        $invoice = Invoice::scope($input['invoice'])
            ->invoices()
            ->firstOrFail();

        $rules = [
            'relation' => 'required', // TODO: change to relation_id once views are updated
            'invoice' => 'required', // TODO: change to invoice_id once views are updated
            'amount' => "required|less_than:{$invoice->balance}|positive",
        ];

        if ( ! empty($input['payment_type_id']) && $input['payment_type_id'] == PAYMENT_TYPE_CREDIT) {
            $rules['payment_type_id'] = 'has_credit:'.$input['relation'].','.$input['amount'];
        }

        return $rules;
    }
}
