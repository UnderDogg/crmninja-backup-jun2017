@extends('emails.master')

@section('markup')
    @if ($company->enable_email_markup)
        @include('emails.partials.relation_view_action')
    @endif
@stop

@section('content')
    <tr>
        <td bgcolor="#F4F5F5" style="border-collapse: collapse;">&nbsp;</td>
    </tr>
    <tr>
        <td style="border-collapse: collapse;">
            <table cellpadding="10" cellspacing="0" border="0" bgcolor="#F4F5F5" width="600" align="center" 
                class="header" style="border-top-width: 6px; border-top-color: {{ $company->primary_color ?: '#2E2B2B' }}; border-top-style: solid;">
                <tr>
                    <td class="logo" width="208" style="border-collapse: collapse; vertical-align: middle;" valign="middle">
                        @include('emails.partials.company_logo')
                    </td>
                    <td width="183" style="border-collapse: collapse; vertical-align: middle;" valign="middle">
                        <p class="left" style="line-height: 22px; margin: 0; padding: 2px 0 0;">
                            @if ($invoice->due_date)
                                <span style="font-size: 11px; color: #8f8d8e;">
                                    {{ strtoupper(trans('texts.due_by', ['date' => $company->formatDate($invoice->due_date)])) }}
                                </span><br />
                            @endif
                            <span style="font-size: 18px;">
                                {{ trans("texts.{$entityType}") }} {{ $invoice->invoice_number }}
                            </span>
                        </p>
                    </td>
                    <td style="border-collapse: collapse; vertical-align: middle;" valign="middle">
                        <p class="right" style="line-height: 14px; margin: 0; padding: 0;">
                            <span style="font-size: 15px; color: #231F20;">
                                {{ trans('texts.' . $invoice->present()->balanceDueLabel) }}:
                            </span><br />
                            <span class="total" style="font-size: 26px; display: block;margin-top: 5px;">
                                {{ $company->formatMoney($invoice->getRequestedAmount(), $relation) }}
                            </span>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="content" style="border-collapse: collapse;">
            <div style="font-size: 18px; margin: 42px 40px 42px; padding: 0; max-width: 520px;">{!! $body !!}</div>
        </td>
    </tr>
@stop

@section('footer')
    <p style="color: #A7A6A6; font-size: 13px; line-height: 18px; margin: 0 0 7px; padding: 0;">
        {{ $company->address1 }}
        @if ($company->address1 && $company->getCityState())
            -
        @endif
        {{ $company->getCityState() }}
        @if ($company->address1 || $company->getCityState())
            <br />
        @endif

        @if ($company->website)
            <strong><a href="{{ $company->present()->website }}" style="color: #A7A6A6; text-decoration: none; font-weight: bold; font-size: 10px;">{{ $company->website }}</a></strong>
        @endif
    </p>
@stop