<?php namespace App\Ninja\Transformers;

use App\Models\UserAccountToken;
use League\Fractal\TransformerAbstract;

/**
 * Class UserAccountTokenTransformer
 */
class UserAccountTokenTransformer extends TransformerAbstract
{

    /**
     * @param UserAccountToken $company_token
     * @return array
     */
    public function transform(UserAccountToken $company_token)
    {
        return [
            'name' => $company_token->name,
            'token' => $company_token->token
        ];
    }
}