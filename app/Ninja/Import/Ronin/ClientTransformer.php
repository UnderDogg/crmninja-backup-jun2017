<?php namespace App\Ninja\Import\Ronin;

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
        if ($this->hasRelation($data->corporation)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'corporation'),
                'work_phone' => $this->getString($data, 'phone'),
                'contacts' => [
                    [
                        'first_name' => $this->getFirstName($data->name),
                        'last_name' => $this->getLastName($data->name),
                        'email' => $this->getString($data, 'email'),
                    ],
                ],
            ];
        });
    }
}
