<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BankRekeningService;

/**
 * Class TestOFX
 */
class TestOFX extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:test-ofx';

    /**
     * @var string
     */
    protected $description = 'Test OFX';

    /**
     * @var BankRekeningService
     */
    protected $bankRekeningService;

    /**
     * TestOFX constructor.
     *
     * @param BankRekeningService $bankRekeningService
     */
    public function __construct(BankRekeningService $bankRekeningService)
    {
        parent::__construct();

        $this->bankRekeningService = $bankRekeningService;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' Running TestOFX...');
    }
}