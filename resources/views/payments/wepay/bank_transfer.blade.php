@extends('payments.bank_transfer')

@section('payment_details')

    {!! Former::vertical_open($url) !!}

    <h3>{{ trans('texts.bankrekening') }}</h3>

    @if (!empty($details))
        <div>{{$details->bank_company_name}}</div>
        <div>&bull;&bull;&bull;&bull;&bull;{{$details->bank_company_last_four}}</div>
    @endif

    {!! Former::checkbox('authorize_ach')
            ->text(trans('texts.ach_authorization', ['corporation'=>$company->getDisplayName(), 'email' => $company->work_email]))
            ->label(' ') !!}

    {!! Former::checkbox('tos_agree')
            ->text(trans('texts.wepay_payment_tos_agree', [
            'terms' => '<a href="https://go.wepay.com/terms-of-service-us" target="_blank">'.trans('texts.terms_of_service').'</a>',
            'privacy_policy' => '<a href="https://go.wepay.com/privacy-policy-us" target="_blank">'.trans('texts.privacy_policy').'</a>',
            ]))
            ->help(trans('texts.payment_processed_through_wepay'))
            ->label(' ') !!}

    <input type="hidden" name="sourceToken" value="{{$sourceId}}">

    <p>&nbsp;</p>

    <center>
        @if(isset($amount) && empty($paymentMethodPending))
            {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . $company->formatMoney($amount, $relation, true)  ))
                            ->submit()
                            ->large() !!}
        @else
            {!! Button::success(strtoupper(trans('texts.add_bank_company') ))
                        ->submit()
                        ->large() !!}
        @endif
    </center>

    {!! Former::close() !!}

@stop
