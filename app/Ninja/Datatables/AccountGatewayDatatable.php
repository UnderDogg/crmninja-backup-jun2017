<?php namespace App\Ninja\Datatables;

use URL;
use App\Models\AccountGateway;

class AccountGatewayDatatable extends EntityDatatable
{
    public $entityType = ENTITY_COMPANY_GATEWAY;

    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    if ($model->deleted_at) {
                        return $model->name;
                    } elseif ($model->gateway_id != GATEWAY_WEPAY) {
                        return link_to("gateways/{$model->public_id}/edit", $model->name)->toHtml();
                    } else {
                        $accountGateway = AccountGateway::find($model->id);
                        $config = $accountGateway->getConfig();
                        $endpoint = WEPAY_ENVIRONMENT == WEPAY_STAGE ? 'https://stage.wepay.com/' : 'https://www.wepay.com/';
                        $wepayCompanyId = $config->companyId;
                        $wepayState = isset($config->state) ? $config->state : null;
                        $linkText = $model->name;
                        $url = $endpoint . 'loginaccount/' . $wepayCompanyId;
                        $html = link_to($url, $linkText, ['target' => '_blank'])->toHtml();

                        try {
                            if ($wepayState == 'action_required') {
                                $updateUri = $endpoint . 'api/company_update/' . $wepayCompanyId . '?redirect_uri=' . urlencode(URL::to('gateways'));
                                $linkText .= ' <span style="color:#d9534f">(' . trans('texts.action_required') . ')</span>';
                                $url = $updateUri;
                                $html = "<a href=\"{$url}\">{$linkText}</a>";
                                $model->setupUrl = $url;
                            } elseif ($wepayState == 'pending') {
                                $linkText .= ' (' . trans('texts.resend_confirmation_email') . ')';
                                $model->resendConfirmationUrl = $url = URL::to("gateways/{$accountGateway->public_id}/resend_confirmation");
                                $html = link_to($url, $linkText)->toHtml();
                            }
                        } catch (\WePayException $ex) {
                        }

                        return $html;
                    }
                }
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                uctrans('texts.resend_confirmation_email'),
                function ($model) {
                    return $model->resendConfirmationUrl;
                },
                function ($model) {
                    return !$model->deleted_at && $model->gateway_id == GATEWAY_WEPAY && !empty($model->resendConfirmationUrl);
                }
            ], [
                uctrans('texts.edit_gateway'),
                function ($model) {
                    return URL::to("gateways/{$model->public_id}/edit");
                },
                function ($model) {
                    return !$model->deleted_at;
                }
            ], [
                uctrans('texts.finish_setup'),
                function ($model) {
                    return $model->setupUrl;
                },
                function ($model) {
                    return !$model->deleted_at && $model->gateway_id == GATEWAY_WEPAY && !empty($model->setupUrl);
                }
            ], [
                uctrans('texts.manage_company'),
                function ($model) {
                    $accountGateway = AccountGateway::find($model->id);
                    $endpoint = WEPAY_ENVIRONMENT == WEPAY_STAGE ? 'https://stage.wepay.com/' : 'https://www.wepay.com/';
                    return [
                        'url' => $endpoint . 'loginaccount/' . $accountGateway->getConfig()->companyId,
                        'attributes' => 'target="_blank"'
                    ];
                },
                function ($model) {
                    return !$model->deleted_at && $model->gateway_id == GATEWAY_WEPAY;
                }
            ], [
                uctrans('texts.terms_of_service'),
                function ($model) {
                    return 'https://go.wepay.com/terms-of-service-us';
                },
                function ($model) {
                    return $model->gateway_id == GATEWAY_WEPAY;
                }
            ]
        ];
    }

}
