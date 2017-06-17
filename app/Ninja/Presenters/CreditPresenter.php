<?php namespace App\Ninja\Presenters;

use Utils;

/**
 * Class CreditPresenter
 */
class CreditPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function relation()
    {
        return $this->entity->relation ? $this->entity->relation->getDisplayName() : '';
    }

    /**
     * @return \DateTime|string
     */
    public function credit_date()
    {
        return Utils::fromSqlDate($this->entity->credit_date);
    }
}
