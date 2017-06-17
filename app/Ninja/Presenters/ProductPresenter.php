<?php namespace App\Ninja\Presenters;

use App\Libraries\Skype\HeroCard;

class ProductPresenter extends EntityPresenter
{

    public function skypeBot($company)
    {
        $product = $this->entity;

        $card = new HeroCard();
        $card->setTitle($product->product_key);
        $card->setSubitle($company->formatMoney($product->cost));
        $card->setText($product->notes);

        return $card;
    }

}
