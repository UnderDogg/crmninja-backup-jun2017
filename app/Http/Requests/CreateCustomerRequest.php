<?php namespace App\Http\Requests;

class CreateCustomerRequest extends CustomerRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_CUSTOMER);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'contacts' => 'valid_contacts',
        ];
    }
}
