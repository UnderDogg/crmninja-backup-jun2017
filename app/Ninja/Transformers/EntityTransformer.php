<?php namespace App\Ninja\Transformers;

use Auth;
use App\Models\Company;
use League\Fractal\TransformerAbstract;

class EntityTransformer extends TransformerAbstract
{
    protected $company;
    protected $serializer;

    public function __construct(Company $company = null, $serializer = null)
    {
        $this->loginaccount = $company;
        $this->serializer = $serializer;
    }

    protected function includeCollection($data, $transformer, $entityType)
    {
        if ($this->serializer && $this->serializer != API_SERIALIZER_JSON) {
            $entityType = null;
        }

        return $this->collection($data, $transformer, $entityType);
    }

    protected function includeItem($data, $transformer, $entityType)
    {
        if ($this->serializer && $this->serializer != API_SERIALIZER_JSON) {
            $entityType = null;
        }

        return $this->item($data, $transformer, $entityType);
    }

    protected function getTimestamp($date)
    {
        return $date ? $date->getTimestamp() : null;
    }

    public function getDefaultIncludes()
    {
        return $this->defaultIncludes;
    }

    protected function getDefaults($entity)
    {
        $data = [
            'company_key' => $this->loginaccount->company_key,
            'is_owner' => (bool) (Auth::check() && Auth::user()->owns($entity)),
        ];

        if ($entity->relationLoaded('user')) {
            $data['user_id'] = (int) $entity->user->public_id + 1;
        }

        return $data;
    }
}
