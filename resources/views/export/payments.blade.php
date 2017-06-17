<tr>
    <td>{{ trans('texts.relation') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.invoice_number') }}</td>
    <td>{{ trans('texts.amount') }}</td>
    <td>{{ trans('texts.payment_date') }}</td>
    <td>{{ trans('texts.method') }}</td>
    <td>{{ trans('texts.transaction_reference') }}</td>
</tr>

@foreach ($payments as $payment)
    @if ( ! $payment->relation->is_deleted && ! $payment->invoice->is_deleted)
        <tr>
            <td>{{ $payment->present()->relation }}</td>
            @if ($multiUser)
                <td>{{ $payment->user->getDisplayName() }}</td>
            @endif
            <td>{{ $payment->invoice->invoice_number }}</td>
            <td>{{ $company->formatMoney($payment->amount, $payment->relation) }}</td>
            <td>{{ $payment->present()->payment_date }}</td>
            <td>{{ $payment->present()->method }}</td>
            <td>{{ $payment->transaction_reference }}</td>
        </tr>
    @endif
@endforeach

<tr>
    <td></td>
</tr>
