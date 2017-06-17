<?php namespace App\Ninja\Datatables;

use Utils;
use URL;
use Auth;

class CustomerDatatable extends EntityDatatable
{
    public $entityType = ENTITY_CUSTOMER;

    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("customers/{$model->public_id}", $model->name ?: '')->toHtml();
                }
            ],
            [
                'city',
                function ($model) {
                    return $model->city;
                }
            ],
            [
                'work_phone',
                function ($model) {
                    return $model->work_phone;
                }
            ],
            [
                'email',
                function ($model) {
                    return link_to("customers/{$model->public_id}", $model->email ?: '')->toHtml();
                }
            ],
            [
                'customers.created_at',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->created_at));
                }
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_customer'),
                function ($model) {
                    return URL::to("customers/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_CUSTOMER, $model->user_id]);
                }
            ],
            [
                '--divider--', function () {
                return false;
            },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_CUSTOMER, $model->user_id]) && Auth::user()->can('create', ENTITY_EXPENSE);
                }

            ],
            [
                trans('texts.enter_expense'),
                function ($model) {
                    return URL::to("invoices/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_EXPENSE);
                }
            ]
        ];
    }


}
