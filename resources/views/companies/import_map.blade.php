@extends('header')

@section('content')
	@parent

    @include('companies.nav', ['selected' => COMPANY_IMPORT_EXPORT])

	{!! Former::open('/import_csv')->addClass('warn-on-exit') !!}

    @foreach (App\Services\ImportService::$entityTypes as $entityType)
        @if (isset($data[$entityType]))
            @include('companies.partials.map', $data[$entityType])
        @endif
    @endforeach

    {!! Former::actions(
        Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/settings/import_export'))->appendIcon(Icon::create('remove-circle')),
        Button::success(trans('texts.import'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))) !!}

    {!! Former::close() !!}

@stop
