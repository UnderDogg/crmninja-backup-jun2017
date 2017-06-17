<?php namespace App\Console\Commands;

use Auth;
use Utils;
use Illuminate\Console\Command;
use Faker\Factory;
use App\Ninja\Repositories\RelationRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\VendorRepository;
use App\Ninja\Repositories\ExpenseRepository;

/**
 * Class CreateTestData
 */
class CreateTestData extends Command
{
    //protected $name = 'ninja:create-test-data';
    /**
     * @var string
     */
    protected $description = 'Create Test Data';
    /**
     * @var string
     */
    protected $signature = 'ninja:create-test-data {count=1}';

    /**
     * @var
     */
    protected $token;

    /**
     * CreateTestData constructor.
     * @param RelationRepository $relationRepo
     * @param InvoiceRepository $invoiceRepo
     * @param PaymentRepository $paymentRepo
     * @param VendorRepository $vendorRepo
     * @param ExpenseRepository $expenseRepo
     */
    public function __construct(
        RelationRepository $relationRepo,
        InvoiceRepository $invoiceRepo,
        PaymentRepository $paymentRepo,
        VendorRepository $vendorRepo,
        ExpenseRepository $expenseRepo)
    {
        parent::__construct();

        $this->faker = Factory::create();

        $this->relationRepo = $relationRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->vendorRepo = $vendorRepo;
        $this->expenseRepo = $expenseRepo;
    }

    /**
     * @return bool
     */
    public function fire()
    {
        if (Utils::isNinjaProd()) {
            return false;
        }

        $this->info(date('Y-m-d') . ' Running CreateTestData...');

        Auth::loginUsingId(1);
        $this->count = $this->argument('count');

        $this->createRelations();
        $this->createVendors();

        $this->info('Done');
    }

    private function createRelations()
    {
        for ($i = 0; $i < $this->count; $i++) {
            $data = [
                'name' => $this->faker->name,
                'address1' => $this->faker->streetAddress,
                'address2' => $this->faker->secondaryAddress,
                'city' => $this->faker->city,
                'state' => $this->faker->state,
                'postal_code' => $this->faker->postcode,
                'contacts' => [[
                    'first_name' => $this->faker->firstName,
                    'last_name' => $this->faker->lastName,
                    'email' => $this->faker->safeEmail,
                    'phone' => $this->faker->phoneNumber,
                ]]
            ];

            $relation = $this->relationRepo->save($data);
            $this->info('Relation: ' . $relation->name);

            $this->createInvoices($relation);
        }
    }

    /**
     * @param $relation
     */
    private function createInvoices($relation)
    {
        for ($i = 0; $i < $this->count; $i++) {
            $data = [
                'relation_id' => $relation->id,
                'invoice_items' => [[
                    'product_key' => $this->faker->word,
                    'qty' => $this->faker->randomDigit + 1,
                    'cost' => $this->faker->randomFloat(2, 1, 10),
                    'notes' => $this->faker->text($this->faker->numberBetween(50, 300))
                ]]
            ];

            $invoice = $this->invoiceRepo->save($data);
            $this->info('Invoice: ' . $invoice->invoice_number);

            $this->createPayment($relation, $invoice);
        }
    }

    /**
     * @param $relation
     * @param $invoice
     */
    private function createPayment($relation, $invoice)
    {
        $data = [
            'invoice_id' => $invoice->id,
            'relation_id' => $relation->id,
            'amount' => $this->faker->randomFloat(2, 0, $invoice->amount)
        ];

        $payment = $this->paymentRepo->save($data);

        $this->info('Payment: ' . $payment->amount);
    }

    private function createVendors()
    {
        for ($i = 0; $i < $this->count; $i++) {
            $data = [
                'name' => $this->faker->name,
                'address1' => $this->faker->streetAddress,
                'address2' => $this->faker->secondaryAddress,
                'city' => $this->faker->city,
                'state' => $this->faker->state,
                'postal_code' => $this->faker->postcode,
                'vendor_contacts' => [[
                    'first_name' => $this->faker->firstName,
                    'last_name' => $this->faker->lastName,
                    'email' => $this->faker->safeEmail,
                    'phone' => $this->faker->phoneNumber,
                ]]
            ];

            $vendor = $this->vendorRepo->save($data);
            $this->info('Vendor: ' . $vendor->name);

            $this->createExpense($vendor);
        }
    }

    /**
     * @param $vendor
     */
    private function createExpense($vendor)
    {
        for ($i = 0; $i < $this->count; $i++) {
            $data = [
                'vendor_id' => $vendor->id,
                'amount' => $this->faker->randomFloat(2, 1, 10),
                'expense_date' => null,
                'public_notes' => null,
            ];

            $expense = $this->expenseRepo->save($data);
            $this->info('Expense: ' . $expense->amount);
        }
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
