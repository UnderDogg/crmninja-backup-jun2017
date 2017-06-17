<?php namespace App\Ninja\Datatables;

class EntityDatatable
{
    public $entityType;
    public $isBulkEdit;
    public $hideRelation;

    public function __construct($isBulkEdit = true, $hideRelation = false)
    {
        $this->isBulkEdit = $isBulkEdit;
        $this->hideRelation = $hideRelation;
    }

    public function columns()
    {
        return [];
    }

    public function actions()
    {
        return [];
    }
}
