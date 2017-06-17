<?php namespace App\Ninja\Repositories;

use DB;
use Session;
use App\Models\Token;

class TokenRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\UserAccountToken';
    }

    public function find($userId)
    {
        $query = DB::table('useraccount_tokens')
                  ->where('useraccount_tokens.user_id', '=', $userId);

        if (!Session::get('show_trash:token')) {
            $query->where('useraccount_tokens.deleted_at', '=', null);
        }

        return $query->select('useraccount_tokens.public_id', 'useraccount_tokens.name', 'useraccount_tokens.token', 'useraccount_tokens.public_id', 'useraccount_tokens.deleted_at');
    }
}
