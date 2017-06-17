<?php namespace App\Ninja\Transformers;

use App\Models\User;
use App\Models\Company;

class LoginAccountTransformer extends EntityTransformer
{
    protected $defaultIncludes = [
        'user'
    ];

    protected $tokenName;
    
    public function __construct(Company $company, $serializer, $tokenName)
    {
        parent::__construct($company, $serializer);

        $this->tokenName = $tokenName;
    }

    public function includeUser(User $user)
    {
        $transformer = new UserTransformer($this->loginaccount, $this->serializer);
        return $this->includeItem($user, $transformer, 'user');
    }

    public function transform(User $user)
    {
        return [
            'company_key' => $user->loginaccount->company_key,
            'name' => $user->loginaccount->present()->name,
            'token' => $user->loginaccount->getToken($user->id, $this->tokenName),
            'default_url' => SITE_URL,
            'logo' => $user->loginaccount->logo,
            'logo_url' => $user->loginaccount->getLogoURL(),
        ];
    }
}