<?php namespace App\Ninja\Import\Harvest;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class RelationTransformer
 */
class RelationTransformer extends BaseTransformer
{
    /**
     * @param $data
     * @return bool|Item
     */
    public function transform($data)
    {
        if ($this->hasRelation($data->relation_name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'relation_name'),
            ];
        });
    }
}
