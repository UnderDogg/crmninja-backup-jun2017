<?php namespace App\Ninja\Presenters;

/**
 * Class TaskPresenter
 */
class TaskPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function relation()
    {
        return $this->entity->relation ? $this->entity->relation->getDisplayName() : '';
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    /**
     * @param $company
     * @return mixed
     */
    public function times($company)
    {
        $parts = json_decode($this->entity->time_log) ?: [];
        $times = [];

        foreach ($parts as $part) {
            $start = $part[0];
            if (count($part) == 1 || !$part[1]) {
                $end = time();
            } else {
                $end = $part[1];
            }

            $start = $company->formatDateTime("@{$start}");
            $end = $company->formatTime("@{$end}");

            $times[] = "### {$start} - {$end}";
        }

        return implode("\n", $times);
    }

    /**
     * @return string
     */
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
