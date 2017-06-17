<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
  <meta charset="utf-8">
</head>
<body>
    @if ($company->enable_email_markup)
        @include('emails.partials.relation_view_action')
    @endif
    {!! $body !!}
</body>
</html>