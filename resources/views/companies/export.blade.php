@extends('header')

@section('content')
	@parent
    @include('companies.nav', ['selected' => COMPANY_IMPORT_EXPORT])

	{{ Former::open()->addClass('col-md-9 col-md-offset-1') }}
	{{ Former::legend('Export Relation Data') }}
	{{ Button::lg_primary_submit('Download') }}
	{{ Former::close() }}

@stop