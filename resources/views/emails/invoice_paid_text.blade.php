{!! trans('texts.email_salutation', ['name' => $userName]) !!}

{!! trans("texts.notification_{$entityType}_paid", ['amount' => $paymentAmount, 'relation' => $relationName, 'invoice' => $invoiceNumber]) !!}

{!! trans("texts.{$entityType}_link_message") !!}
{!! $invoiceLink !!}

{!! trans('texts.email_signature') !!}
{!! trans('texts.email_from') !!}

{!! trans('texts.user_email_footer', ['link' => URL::to('/settings/notifications')]) !!}
