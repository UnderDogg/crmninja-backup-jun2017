<?php namespace App\Ninja\Datatables;

use URL;

class BankRekeningDatatable extends EntityDatatable
{
    public $entityType = ENTITY_BANK_REKENING;

    public function columns()
    {
        return [
            [
                'bank_name',
                function ($model) {
                    return link_to("bankrekeningen/{$model->public_id}/edit", $model->bank_name)->toHtml();
                },
            ],
            [
                'bank_library_id',
                function ($model) {
                    return 'OFX';
                }
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                uctrans('texts.edit_bank_company'),
                function ($model) {
                    return URL::to("bankrekeningen/{$model->public_id}/edit");
                },
            ]
        ];
    }


}
