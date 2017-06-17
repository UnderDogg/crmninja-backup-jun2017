<tr>
    <td>{{ trans('texts.relation') }}</td>
    <td>{{ trans('texts.email') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.frequency') }}</td>
    <td>{{ trans('texts.balance') }}</td>
    <td>{{ trans('texts.amount') }}</td>
    <td>{{ trans('texts.po_number') }}</td>
    <td>{{ trans('texts.status') }}</td>
    @if ($company->custom_invoice_label1)
        <td>{{ $company->custom_invoice_label1 }}</td>
    @endif
    @if ($company->custom_invoice_label2)
        <td>{{ $company->custom_invoice_label2 }}</td>
    @endif
    @if ($company->custom_invoice_text_label1)
        <td>{{ $company->custom_invoice_text_label1 }}</td>
    @endif
    @if ($company->custom_invoice_text_label2)
        <td>{{ $company->custom_invoice_text_label2 }}</td>
    @endif
</tr>

@foreach ($recurringInvoices as $invoice)
    @if (!$invoice->relation->is_deleted)
        <tr>
            <td>{{ $invoice->present()->relation }}</td>
            <td>{{ $invoice->present()->email }}</td>
            @if ($multiUser)
                <td>{{ $invoice->present()->user }}</td>
            @endif
            <td>{{ $invoice->present()->frequency }}</td>
            <td>{{ $company->formatMoney($invoice->balance, $invoice->relation) }}</td>
            <td>{{ $company->formatMoney($invoice->amount, $invoice->relation) }}</td>
            <td>{{ $invoice->po_number }}</td>
            <td>{{ $invoice->present()->status }}</td>
            @if ($company->custom_invoice_label1)
                <td>{{ $invoice->custom_value1 }}</td>
            @endif
            @if ($company->custom_invoice_label2)
                <td>{{ $invoice->custom_value2 }}</td>
            @endif
            @if ($company->custom_invoice_label1)
                <td>{{ $invoice->custom_text_value1 }}</td>
            @endif
            @if ($company->custom_invoice_label2)
                <td>{{ $invoice->custom_text_value2 }}</td>
            @endif
        </tr>
    @endif
@endforeach

<tr>
    <td></td>
</tr>