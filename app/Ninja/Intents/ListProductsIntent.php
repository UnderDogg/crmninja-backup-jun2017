<?php namespace App\Ninja\Intents;

use Auth;
use Exception;
use App\Models\Product;


class ListProductsIntent extends ProductIntent
{
    public function process()
    {
        $company = Auth::user()->loginaccount;
        $products = Product::scope()
            ->orderBy('product_key')
            ->limit(10)
            ->get()
            ->transform(function ($item, $key) use ($company) {
                $card = $item->present()->skypeBot($company);
                if ($this->stateEntity(ENTITY_INVOICE)) {
                    $card->addButton('imBack', trans('texts.add_to_invoice'), trans('texts.add_product_to_invoice', ['product' => $item->product_key]));
                }
                return $card;
            });

        return $this->createResponse(SKYPE_CARD_CAROUSEL, $products);
    }
}
