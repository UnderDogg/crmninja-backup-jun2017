@extends('payments.bank_transfer')

@section('head')
    @parent

    <script type="text/javascript" src="https://js.stripe.com/v2/"></script>
    <script type="text/javascript">
        Stripe.setPublishableKey('{{ $accountGateway->getPublishableStripeKey() }}');
        $(function() {
            var countries = {!! Cache::get('countries')->pluck('iso_3166_2','id') !!};
            $('.payment-form').submit(function(event) {
                if($('[name=plaidCompanyId]').length)return;

                var $form = $(this);

                var data = {
                    company_holder_name: $('#company_holder_name').val(),
                    company_holder_type: $('[name=company_holder_type]:checked').val(),
                    currency: $("#currency_id").val(),
                    country: countries[$("#country_id").val()],
                    routing_number: $('#routing_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, ''),
                    company_number: $('#company_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '')
                };

                // Validate the loginaccount details
                if (!data.company_holder_type) {
                    $('#js-error-message').html('{{ trans('texts.missing_company_holder_type') }}').fadeIn();
                    return false;
                }
                if (!data.company_holder_name) {
                    $('#js-error-message').html('{{ trans('texts.missing_company_holder_name') }}').fadeIn();
                    return false;
                }
                if (!data.routing_number || !Stripe.bankRekening.validateRoutingNumber(data.routing_number, data.country)) {
                    $('#js-error-message').html('{{ trans('texts.invalid_routing_number') }}').fadeIn();
                    return false;
                }
                if (data.company_number != $('#confirm_company_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '')) {
                    $('#js-error-message').html('{{ trans('texts.company_number_mismatch') }}').fadeIn();
                    return false;
                }
                if (!data.company_number || !Stripe.bankRekening.validateCompanyNumber(data.company_number, data.country)) {
                    $('#js-error-message').html('{{ trans('texts.invalid_company_number') }}').fadeIn();
                    return false;
                }

                // Disable the submit button to prevent repeated clicks
                $form.find('button').prop('disabled', true);
                $('#js-error-message').hide();

                Stripe.bankRekening.createToken(data, stripeResponseHandler);

                // Prevent the form from submitting with the default action
                return false;
            });

            @if ($accountGateway->getPlaidEnabled())
                var plaidHandler = Plaid.create({
                    selectCompany: true,
                    env: '{{ $accountGateway->getPlaidEnvironment() }}',
                    relationName: {!! json_encode($company->getDisplayName()) !!},
                    key: '{{ $accountGateway->getPlaidPublicKey() }}',
                    product: 'auth',
                    onSuccess: plaidSuccessHandler,
                    onExit : function(){$('#secured_by_plaid').hide()}
                });

                $('#plaid_link_button').click(function(){plaidHandler.open();$('#secured_by_plaid').fadeIn()});
                $('#plaid_unlink').click(function(e){
                    e.preventDefault();
                    $('#manual_container').fadeIn();
                    $('#plaid_linked').hide();
                    $('#plaid_link_button').show();
                    $('#pay_now_button').hide();
                    $('#add_company_button').show();
                    $('[name=plaidPublicToken]').remove();
                    $('[name=plaidCompanyId]').remove();
                    $('[name=company_holder_type],#company_holder_name').attr('required','required');
                })
            @endif
        });

        function stripeResponseHandler(status, response) {
            var $form = $('.payment-form');

            if (response.error) {
                // Show the errors on the form
                var error = response.error.message;
                if(response.error.param == 'bankrekening[country]') {
                    error = "{{trans('texts.country_not_supported')}}";
                }
                $form.find('button').prop('disabled', false);
                $('#js-error-message').html(error).fadeIn();
            } else {
                // response contains id and card, which contains additional card details
                var token = response.id;
                // Insert the token into the form so it gets submitted to the server
                $form.append($('<input type="hidden" name="sourceToken"/>').val(token));
                // and submit
                $form.get(0).submit();
            }
        };

        function plaidSuccessHandler(public_token, metadata) {
            $('#secured_by_plaid').hide()
            var $form = $('.payment-form');

            $form.append($('<input type="hidden" name="plaidPublicToken"/>').val(public_token));
            $form.append($('<input type="hidden" name="plaidCompanyId"/>').val(metadata.company_id));
            $('#plaid_linked_status').text('{{ trans('texts.plaid_linked_status') }}'.replace(':bank', metadata.institution.name));
            $('#manual_container').fadeOut();
            $('#plaid_linked').show();
            $('#plaid_link_button').hide();
            $('[name=company_holder_type],#company_holder_name').removeAttr('required');


            var payNowBtn = $('#pay_now_button');
            if(payNowBtn.length) {
                payNowBtn.show();
                $('#add_company_button').hide();
            }
        };
    </script>

@stop


@section('payment_details')

    {!! Former::open($url)
            ->autocomplete('on')
            ->addClass('payment-form')
            ->id('payment-form')
            ->rules(array(
                'country_id' => 'required',
                'currency_id' => 'required',
                'company_number' => 'required',
                'routing_number' => 'required',
                'company_holder_name' => 'required',
                'company_holder_type' => 'required',
                'authorize_ach' => 'required',
            )) !!}

    {!! Former::populateField('company_holder_type', 'individual') !!}
    {!! Former::populateField('country_id', $relation->country_id) !!}
    {!! Former::populateField('currency_id', $relation->getCurrencyCode()) !!}

    @if (Utils::isNinjaDev())
        {!! Former::populateField('company_holder_name', 'Test Relation') !!}
        <script>
            $(function() {
                $('#routing_number').val('110000000');
                $('#company_number').val('000123456789');
                $('#confirm_company_number').val('000123456789');
                $('#authorize_ach').prop('checked', true);
            })
        </script>
    @endif

    @if ($accountGateway->getPlaidEnabled())
        <div id="plaid_container">
            <a class="btn btn-default btn-lg" id="plaid_link_button">
                <img src="{{ URL::to('images/plaid-logo.svg') }}">
                <img src="{{ URL::to('images/plaid-logowhite.svg') }}" class="hoverimg">
                {{ trans('texts.link_with_plaid') }}
            </a>
            <div id="plaid_linked">
                <div id="plaid_linked_status"></div>
                <a href="#" id="plaid_unlink">{{ trans('texts.unlink') }}</a>
            </div>
        </div>
    @endif

    <div id="manual_container">
        @if($accountGateway->getPlaidEnabled())
            <div id="plaid_or"><span>{{ trans('texts.or') }}</span></div>
            <h4>{{ trans('texts.link_manually') }}</h4>
        @endif

        <p>{{ trans('texts.ach_verification_delay_help') }}</p><br/>


        {!! Former::radios('company_holder_type')->radios(array(
                trans('texts.individual_company') => array('value' => 'individual'),
                trans('texts.corporation_company') => array('value' => 'corporation'),
            ))->inline()->label(trans('texts.company_holder_type'));  !!}

        {!! Former::text('company_holder_name')
               ->label(trans('texts.company_holder_name')) !!}

        {!! Former::select('country_id')
                ->label(trans('texts.country_id'))
                ->addOption('','')
                ->fromQuery(Cache::get('countries'), 'name', 'id')
                ->addGroupClass('country-select') !!}

        {!! Former::select('currency_id')
                ->label(trans('texts.currency_id'))
                ->addOption('','')
                ->fromQuery(Cache::get('currencies'), 'name', 'code')
                ->addGroupClass('currency-select') !!}

        {!! Former::text('')
                ->id('routing_number')
                ->label(trans('texts.routing_number')) !!}

        <div class="form-group" style="margin-top:-15px">
            <div class="col-md-8 col-md-offset-4">
                <div id="bank_name"></div>
            </div>
        </div>

        {!! Former::text('')
                ->id('company_number')
                ->label(trans('texts.company_number')) !!}
        {!! Former::text('')
                ->id('confirm_company_number')
                ->label(trans('texts.confirm_company_number')) !!}
    </div>

    {!! Former::checkbox('authorize_ach')
            ->text(trans('texts.ach_authorization', ['corporation'=>$company->getDisplayName(), 'email' => $company->work_email]))
            ->label(' ') !!}


    <div class="col-md-12">
        <div id="js-error-message" style="display:none" class="alert alert-danger"></div>
    </div>

    <p>&nbsp;</p>

    <div class="col-md-8 col-md-offset-4">

        {!! Button::success(strtoupper(trans('texts.add_company')))
                        ->submit()
                        ->withAttributes(['id'=>'add_company_button'])
                        ->large() !!}

        @if ($accountGateway->getPlaidEnabled() && !empty($amount))
            {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . $company->formatMoney($amount, $relation, true)  ))
                        ->submit()
                        ->withAttributes(['style'=>'display:none', 'id'=>'pay_now_button'])
                        ->large() !!}
        @endif

    </div>

    {!! Former::close() !!}


    <script type="text/javascript">
        var routingNumberCache = {};
        $('#routing_number, #country').on('change keypress keyup keydown paste', function(){setTimeout(function () {
            var routingNumber = $('#routing_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '');
            if (routingNumber.length != 9 || $("#country_id").val() != 840 || routingNumberCache[routingNumber] === false) {
                $('#bank_name').hide();
            } else if (routingNumberCache[routingNumber]) {
                $('#bank_name').empty().append(routingNumberCache[routingNumber]).show();
            } else {
                routingNumberCache[routingNumber] = false;
                $('#bank_name').hide();
                $.ajax({
                    url:"{{ URL::to('/bank') }}/" + routingNumber,
                    success:function(data) {
                        var els = $().add(document.createTextNode(data.name + ", " + data.city + ", " + data.state));
                        routingNumberCache[routingNumber] = els;

                        // Still the same number?
                        if (routingNumber == $('#routing_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '')) {
                            $('#bank_name').empty().append(els).show();
                        }
                    },
                    error:function(xhr) {
                        if (xhr.status == 404) {
                            var els = $(document.createTextNode('{{trans('texts.unknown_bank')}}'));
                            routingNumberCache[routingNumber] = els;

                            // Still the same number?
                            if (routingNumber == $('#routing_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '')) {
                                $('#bank_name').empty().append(els).show();
                            }
                        }
                    }
                })
            }
        },10)})
    </script>

@stop
