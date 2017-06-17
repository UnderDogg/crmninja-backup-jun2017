<?php namespace App\Ninja\Presenters;


class RelationPresenter extends EntityPresenter
{

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }

    public function balance()
    {
        $relation = $this->entity;
        $company = $relation->loginaccount;

        return $company->formatMoney($relation->balance, $relation);
    }

    public function paid_to_date()
    {
        $relation = $this->entity;
        $company = $relation->loginaccount;

        return $company->formatMoney($relation->paid_to_date, $relation);
    }

    public function status()
    {
        $class = $text = '';

        if ($this->entity->is_deleted) {
            $class = 'danger';
            $text = trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            $class = 'warning';
            $text = trans('texts.archived');
        } else {
            $class = 'success';
            $text = trans('texts.active');
        }

        return "<span class=\"label label-{$class}\">{$text}</span>";
    }
}
