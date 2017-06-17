<?php namespace App\Http\Controllers;

use Cache;
use Auth;
use Input;
use Redirect;
use Session;
use View;
use Crypt;
use File;
use App\Models\Company;
use App\Models\BankRekening;
use App\Ninja\Repositories\BankRekeningRepository;
use App\Services\BankRekeningService;
use App\Http\Requests\CreateBankRekeningRequest;
use Illuminate\Http\Request;

class BankRekeningController extends BaseController
{
    protected $bankRekeningService;
    protected $bankRekeningRepo;

    public function __construct(BankRekeningService $bankRekeningService, BankRekeningRepository $bankRekeningRepo)
    {
        //parent::__construct();

        $this->bankRekeningService = $bankRekeningService;
        $this->bankRekeningRepo = $bankRekeningRepo;
    }

    public function index()
    {
        return Redirect::to('settings/' . COMPANY_BANKS);
    }

    public function getDatatable()
    {
        return $this->bankRekeningService->getDatatable(Auth::user()->company_id);
    }

    public function edit($publicId)
    {
        $bankRekening = BankRekening::scope($publicId)->firstOrFail();

        $data = [
            'title' => trans('texts.edit_bank_company'),
            'banks' => Cache::get('banks'),
            'bankRekening' => $bankRekening,
        ];

        return View::make('companies.bankrekening', $data);
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    /**
     * Displays the form for loginaccount creation
     *
     */
    public function create()
    {
        $data = [
            'banks' => Cache::get('banks'),
            'bankRekening' => null,
        ];

        return View::make('companies.bankrekening', $data);
    }

    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');
        $count = $this->bankRekeningService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_bank_company'));

        return Redirect::to('settings/' . COMPANY_BANKS);
    }

    public function validateCompany()
    {
        $publicId = Input::get('public_id');
        $username = trim(Input::get('bank_username'));
        $password = trim(Input::get('bank_password'));

        if ($publicId) {
            $bankRekening = BankRekening::scope($publicId)->firstOrFail();
            if ($username != $bankRekening->username) {
                // TODO update username
            }
            $username = Crypt::decrypt($username);
            $bankId = $bankRekening->bank_id;
        } else {
            $bankId = Input::get('bank_id');
        }

        return json_encode($this->bankRekeningService->loadBankRekeningen($bankId, $username, $password, $publicId));
    }

    public function store(CreateBankRekeningRequest $request)
    {
        $bankRekening = $this->bankRekeningRepo->save(Input::all());

        $bankId = Input::get('bank_id');
        $username = trim(Input::get('bank_username'));
        $password = trim(Input::get('bank_password'));

        return json_encode($this->bankRekeningService->loadBankRekeningen($bankId, $username, $password, true));
    }

    public function importExpenses($bankId)
    {
        return $this->bankRekeningService->importExpenses($bankId, Input::all());
    }

    public function showImportOFX()
    {
        return view('companies.import_ofx');
    }

    public function doImportOFX(Request $request)
    {
        $file = File::get($request->file('ofx_file'));

        try {
            $data = $this->bankRekeningService->parseOFX($file);
        } catch (\Exception $e) {
            Session::flash('error', trans('texts.ofx_parse_failed'));
            return view('companies.import_ofx');
        }

        $data = [
            'banks' => null,
            'bankRekening' => null,
            'transactions' => json_encode([$data])
        ];

        return View::make('companies.bankrekening', $data);
    }
}
