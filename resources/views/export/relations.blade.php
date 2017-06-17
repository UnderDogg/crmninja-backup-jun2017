<tr>
    <td>{{ trans('texts.name') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.balance') }}</td>
    <td>{{ trans('texts.paid_to_date') }}</td>
    <td>{{ trans('texts.address1') }}</td>
    <td>{{ trans('texts.address2') }}</td>
    <td>{{ trans('texts.city') }}</td>
    <td>{{ trans('texts.state') }}</td>
    <td>{{ trans('texts.postal_code') }}</td>
    <td>{{ trans('texts.country') }}</td>
    @if ($company->custom_relation_label1)
        <td>{{ $company->custom_relation_label1 }}</td>
    @endif
    @if ($company->custom_relation_label2)
        <td>{{ $company->custom_relation_label2 }}</td>
    @endif
</tr>

@foreach ($relations as $relation)
    <tr>
        <td>{{ $relation->getDisplayName() }}</td>
        @if ($multiUser)
            <td>{{ $relation->user->getDisplayName() }}</td>
        @endif
        <td>{{ $company->formatMoney($relation->balance, $relation) }}</td>
        <td>{{ $company->formatMoney($relation->paid_to_date, $relation) }}</td>
        <td>{{ $relation->address1 }}</td>
        <td>{{ $relation->address2 }}</td>
        <td>{{ $relation->city }}</td>
        <td>{{ $relation->state }}</td>
        <td>{{ $relation->postal_code }}</td>
        <td>{{ $relation->present()->country }}</td>
        @if ($company->custom_relation_label1)
            <td>{{ $relation->custom_value1 }}</td>
        @endif
        @if ($company->custom_relation_label2)
            <td>{{ $relation->custom_value2 }}</td>
        @endif
    </tr>
@endforeach

<tr>
    <td></td>
</tr>