<?php namespace App\Ninja\Repositories;

use DB;
use Crypt;
use App\Models\BankRekening;
use App\Models\BankSubRekening;

class BankRekeningRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\BankRekening';
    }

    public function find($companyId)
    {
        return DB::table('bankrekeningen')
            ->join('banks', 'banks.id', '=', 'bankrekeningen.bank_id')
            ->where('bankrekeningen.deleted_at', '=', null)
            ->where('bankrekeningen.company_id', '=', $companyId)
            ->select(
                'bankrekeningen.public_id',
                'banks.name as bank_name',
                'bankrekeningen.deleted_at',
                'banks.bank_library_id'
            );
    }

    public function save($input)
    {
        $bankRekening = BankRekening::createNew();
        $bankRekening->bank_id = $input['bank_id'];
        $bankRekening->username = Crypt::encrypt(trim($input['bank_username']));

        $company = \Auth::user()->loginaccount;
        $company->bankrekeningen()->save($bankRekening);

        foreach ($input['bankrekeningen'] as $data) {
            if (!isset($data['include']) || !filter_var($data['include'], FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $subbankrekening = BankSubRekening::createNew();
            $subbankrekening->company_name = trim($data['company_name']);
            $subbankrekening->company_number = trim($data['hashed_company_number']);
            $bankRekening->bank_subrekeningen()->save($subbankrekening);
        }

        return $bankRekening;
    }
}
