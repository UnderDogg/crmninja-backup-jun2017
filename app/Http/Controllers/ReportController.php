<?php namespace App\Http\Controllers;

use Auth;
use Config;
use Input;
use Utils;
use DB;
use DateInterval;
use DatePeriod;
use Session;
use View;
use App\Models\Company;
use App\Models\Relation;
use App\Models\Payment;
use App\Models\Expense;

/**
 * Class ReportController
 */
class ReportController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function d3()
    {
        $message = '';
        $fileName = storage_path().'/dataviz_sample.txt';

        if (Auth::user()->loginaccount->hasFeature(FEATURE_REPORTS)) {
            $company = Company::where('id', '=', Auth::user()->loginaccount->id)
                            ->with(['relations.invoices.invoice_items', 'relations.contacts'])
                            ->first();
            $company = $company->hideFieldsForViz();
            $relations = $company->relations->toJson();
        } elseif (file_exists($fileName)) {
            $relations = file_get_contents($fileName);
            $message = trans('texts.sample_data');
        } else {
            $relations = '[]';
        }

        $data = [
            'relations' => $relations,
            'message' => $message,
        ];

        return View::make('reports.d3', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function showReports()
    {
        $action = Input::get('action');

        if (Input::all()) {
            $groupBy = Input::get('group_by');
            $chartType = Input::get('chart_type');
            $reportType = Input::get('report_type');
            $dateField = Input::get('date_field');
            $startDate = Utils::toSqlDate(Input::get('start_date'), false);
            $endDate = Utils::toSqlDate(Input::get('end_date'), false);
            $enableReport = boolval(Input::get('enable_report'));
            $enableChart = boolval(Input::get('enable_chart'));
        } else {
            $groupBy = 'MONTH';
            $chartType = 'Bar';
            $reportType = ENTITY_INVOICE;
            $dateField = FILTER_INVOICE_DATE;
            $startDate = Utils::today(false)->modify('-3 month');
            $endDate = Utils::today(false);
            $enableReport = true;
            $enableChart = true;
        }

        $dateTypes = [
            'DAYOFYEAR' => 'Daily',
            'WEEK' => 'Weekly',
            'MONTH' => 'Monthly',
        ];

        $chartTypes = [
            'Bar' => 'Bar',
            'Line' => 'Line',
        ];

        $reportTypes = [
            ENTITY_RELATION => trans('texts.relation'),
            ENTITY_INVOICE => trans('texts.invoice'),
            ENTITY_PAYMENT => trans('texts.payment'),
            ENTITY_EXPENSE => trans('texts.expenses'),
            ENTITY_TAX_RATE => trans('texts.taxes'),
        ];

        $params = [
            'dateTypes' => $dateTypes,
            'chartTypes' => $chartTypes,
            'chartType' => $chartType,
            'startDate' => $startDate->format(Session::get(SESSION_DATE_FORMAT)),
            'endDate' => $endDate->format(Session::get(SESSION_DATE_FORMAT)),
            'groupBy' => $groupBy,
            'reportTypes' => $reportTypes,
            'reportType' => $reportType,
            'enableChart' => $enableChart,
            'enableReport' => $enableReport,
            'title' => trans('texts.charts_and_reports'),
        ];

        if (Auth::user()->loginaccount->hasFeature(FEATURE_REPORTS)) {
            if ($enableReport) {
                $isExport = $action == 'export';
                $params = array_merge($params, self::generateReport($reportType, $startDate, $endDate, $dateField, $isExport));

                if ($isExport) {
                    self::export($reportType, $params['displayData'], $params['columns'], $params['reportTotals']);
                }
            }
            if ($enableChart) {
                $params = array_merge($params, self::generateChart($groupBy, $startDate, $endDate));
            }
        } else {
            $params['columns'] = [];
            $params['displayData'] = [];
            $params['reportTotals'] = [];
            $params['labels'] = [];
            $params['datasets'] = [];
            $params['scaleStepWidth'] = 100;
        }

        return View::make('reports.chart_builder', $params);
    }

    /**
     * @param $groupBy
     * @param $startDate
     * @param $endDate
     * @return array
     */
    private function generateChart($groupBy, $startDate, $endDate)
    {
        $width = 10;
        $datasets = [];
        $labels = [];
        $maxTotals = 0;

        foreach ([ENTITY_INVOICE, ENTITY_PAYMENT, ENTITY_CREDIT] as $entityType) {
            // SQLite does not support the YEAR(), MONTH(), WEEK() and similar functions.
            // Let's see if SQLite is being used.
            if (Config::get('database.connections.'.Config::get('database.default').'.driver') == 'sqlite') {
                // Replace the unsupported function with it's date format counterpart
                switch ($groupBy) {
                    case 'MONTH':
                        $dateFormat = '%m';     // returns 01-12
                        break;
                    case 'WEEK':
                        $dateFormat = '%W';     // returns 00-53
                        break;
                    case 'DAYOFYEAR':
                        $dateFormat = '%j';     // returns 001-366
                        break;
                    default:
                        $dateFormat = '%m';     // MONTH by default
                        break;
                }

                // Concatenate the year and the chosen timeframe (Month, Week or Day)
                $timeframe = 'strftime("%Y", '.$entityType.'_date) || strftime("'.$dateFormat.'", '.$entityType.'_date)';
            } else {
                // Supported by Laravel's other DBMS drivers (MySQL, MSSQL and PostgreSQL)
                $timeframe = 'concat(YEAR('.$entityType.'_date), '.$groupBy.'('.$entityType.'_date))';
            }

            $records = DB::table($entityType.'s')
                ->select(DB::raw('sum('.$entityType.'s.amount) as total, '.$timeframe.' as '.$groupBy))
                ->join('customers', 'customers.id', '=', $entityType.'s.customer_id')
                ->join('relations', 'relations.id', '=', 'customers.relation_id')
                ->where('customers.is_deleted', '=', false)
                ->where($entityType.'s.company_id', '=', Auth::user()->company_id)
                ->where($entityType.'s.is_deleted', '=', false)
                ->where($entityType.'s.'.$entityType.'_date', '>=', $startDate->format('Y-m-d'))
                ->where($entityType.'s.'.$entityType.'_date', '<=', $endDate->format('Y-m-d'))
                ->groupBy($groupBy);

            if ($entityType == ENTITY_INVOICE) {
                $records->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                        ->where('is_recurring', '=', false);
            } elseif ($entityType == ENTITY_PAYMENT) {
                $records->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                        ->where('invoices.is_deleted', '=', false);
            }

            $totals = $records->lists('total');
            $dates  = $records->lists($groupBy);
            $data   = array_combine($dates, $totals);

            $padding = $groupBy == 'DAYOFYEAR' ? 'day' : ($groupBy == 'WEEK' ? 'week' : 'month');
            $endDate->modify('+1 '.$padding);
            $interval = new DateInterval('P1'.substr($groupBy, 0, 1));
            $period   = new DatePeriod($startDate, $interval, $endDate);
            $endDate->modify('-1 '.$padding);

            $totals = [];

            foreach ($period as $d) {
                $dateFormat = $groupBy == 'DAYOFYEAR' ? 'z' : ($groupBy == 'WEEK' ? 'W' : 'n');
                // MySQL returns 1-366 for DAYOFYEAR, whereas PHP returns 0-365
                $date = $groupBy == 'DAYOFYEAR' ? $d->format('Y').($d->format($dateFormat) + 1) : $d->format('Y'.$dateFormat);
                $totals[] = isset($data[$date]) ? $data[$date] : 0;

                if ($entityType == ENTITY_INVOICE) {
                    $labelFormat = $groupBy == 'DAYOFYEAR' ? 'j' : ($groupBy == 'WEEK' ? 'W' : 'F');
                    $label = $d->format($labelFormat);
                    $labels[] = $label;
                }
            }

            $max = max($totals);

            if ($max > 0) {
                $datasets[] = [
                    'totals' => $totals,
                    'colors' => $entityType == ENTITY_INVOICE ? '78,205,196' : ($entityType == ENTITY_CREDIT ? '199,244,100' : '255,107,107'),
                ];
                $maxTotals = max($max, $maxTotals);
            }
        }

        $width = (ceil($maxTotals / 100) * 100) / 10;
        $width = max($width, 10);

        return [
            'datasets' => $datasets,
            'scaleStepWidth' => $width,
            'labels' => $labels,
        ];
    }

    /**
     * @param $reportType
     * @param $startDate
     * @param $endDate
     * @param $dateField
     * @param $isExport
     * @return array
     */
    private function generateReport($reportType, $startDate, $endDate, $dateField, $isExport)
    {
        if ($reportType == ENTITY_RELATION) {
            return $this->generateRelationReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_INVOICE) {
            return $this->generateInvoiceReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_PAYMENT) {
            return $this->generatePaymentReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_TAX_RATE) {
            return $this->generateTaxRateReport($startDate, $endDate, $dateField, $isExport);
        } elseif ($reportType == ENTITY_EXPENSE) {
            return $this->generateExpenseReport($startDate, $endDate, $isExport);
        }
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $dateField
     * @param $isExport
     * @return array
     */
    private function generateTaxRateReport($startDate, $endDate, $dateField, $isExport)
    {
        $columns = ['tax_name', 'tax_rate', 'amount', 'paid'];

        $company = Auth::user()->loginaccount;
        $displayData = [];
        $reportTotals = [];

        $relations = Relation::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function($query) use ($startDate, $endDate, $dateField) {
                            $query->with('invoice_items')->withArchived();
                            if ($dateField == FILTER_INVOICE_DATE) {
                                $query->where('invoice_date', '>=', $startDate)
                                      ->where('invoice_date', '<=', $endDate)
                                      ->with('payments');
                            } else {
                                $query->whereHas('payments', function($query) use ($startDate, $endDate) {
                                            $query->where('payment_date', '>=', $startDate)
                                                  ->where('payment_date', '<=', $endDate)
                                                  ->withArchived();
                                        })
                                        ->with(['payments' => function($query) use ($startDate, $endDate) {
                                            $query->where('payment_date', '>=', $startDate)
                                                  ->where('payment_date', '<=', $endDate)
                                                  ->withArchived();
                                        }]);
                            }
                        }]);

        foreach ($relations->get() as $relation) {
            $currencyId = $relation->currency_id ?: Auth::user()->loginaccount->getCurrencyId();
            $amount = 0;
            $paid = 0;
            $taxTotals = [];

            foreach ($relation->invoices as $invoice) {
                foreach ($invoice->getTaxes(true) as $key => $tax) {
                    if ( ! isset($taxTotals[$currencyId])) {
                        $taxTotals[$currencyId] = [];
                    }
                    if (isset($taxTotals[$currencyId][$key])) {
                        $taxTotals[$currencyId][$key]['amount'] += $tax['amount'];
                        $taxTotals[$currencyId][$key]['paid'] += $tax['paid'];
                    } else {
                        $taxTotals[$currencyId][$key] = $tax;
                    }
                }

                $amount += $invoice->amount;
                $paid += $invoice->getAmountPaid();
            }

            foreach ($taxTotals as $currencyId => $taxes) {
                foreach ($taxes as $tax) {
                    $displayData[] = [
                        $tax['name'],
                        $tax['rate'] . '%',
                        $company->formatMoney($tax['amount'], $relation),
                        $company->formatMoney($tax['paid'], $relation)
                    ];
                }

                $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'amount', $tax['amount']);
                $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'paid', $tax['paid']);
            }
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];

    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $isExport
     * @return array
     */
    private function generatePaymentReport($startDate, $endDate, $isExport)
    {
        $columns = ['relation', 'invoice_number', 'invoice_date', 'amount', 'payment_date', 'paid', 'method'];

        $company = Auth::user()->loginaccount;
        $displayData = [];
        $reportTotals = [];

        $payments = Payment::scope()
                        ->withTrashed()
                        ->where('is_deleted', '=', false)
                        ->whereHas('relation', function($query) {
                            $query->where('is_deleted', '=', false);
                        })
                        ->whereHas('invoice', function($query) {
                            $query->where('is_deleted', '=', false);
                        })
                        ->with('relation.contacts', 'invoice', 'payment_type', 'account_gateway.gateway')
                        ->where('payment_date', '>=', $startDate)
                        ->where('payment_date', '<=', $endDate);

        foreach ($payments->get() as $payment) {
            $invoice = $payment->invoice;
            $relation = $payment->relation;
            $displayData[] = [
                $isExport ? $relation->getDisplayName() : $relation->present()->link,
                $isExport ? $invoice->invoice_number : $invoice->present()->link,
                $invoice->present()->invoice_date,
                $company->formatMoney($invoice->amount, $relation),
                $payment->present()->payment_date,
                $company->formatMoney($payment->amount, $relation),
                $payment->present()->method,
            ];

            $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'amount', $invoice->amount);
            $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'paid', $payment->amount);
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $isExport
     * @return array
     */
    private function generateInvoiceReport($startDate, $endDate, $isExport)
    {
        $columns = ['relation', 'invoice_number', 'invoice_date', 'amount', 'payment_date', 'paid', 'method'];

        $company = Auth::user()->loginaccount;
        $displayData = [];
        $reportTotals = [];

        $relations = Relation::scope()
                        ->withTrashed()
                        ->with('contacts')
                        ->where('is_deleted', '=', false)
                        ->with(['invoices' => function($query) use ($startDate, $endDate) {
                            $query->where('invoice_date', '>=', $startDate)
                                  ->where('invoice_date', '<=', $endDate)
                                  ->where('is_deleted', '=', false)
                                  ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                                  ->where('is_recurring', '=', false)
                                  ->with(['payments' => function($query) {
                                        $query->withTrashed()
                                              ->with('payment_type', 'account_gateway.gateway')
                                              ->where('is_deleted', '=', false);
                                  }, 'invoice_items'])
                                  ->withTrashed();
                        }]);

        foreach ($relations->get() as $relation) {
            foreach ($relation->invoices as $invoice) {

                $payments = count($invoice->payments) ? $invoice->payments : [false];
                foreach ($payments as $payment) {
                    $displayData[] = [
                        $isExport ? $relation->getDisplayName() : $relation->present()->link,
                        $isExport ? $invoice->invoice_number : $invoice->present()->link,
                        $invoice->present()->invoice_date,
                        $company->formatMoney($invoice->amount, $relation),
                        $payment ? $payment->present()->payment_date : '',
                        $payment ? $company->formatMoney($payment->amount, $relation) : '',
                        $payment ? $payment->present()->method : '',
                    ];
                    $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'paid', $payment ? $payment->amount : 0);
                }

                $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'amount', $invoice->amount);
                $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'balance', $invoice->balance);
            }
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $isExport
     * @return array
     */
    private function generateRelationReport($startDate, $endDate, $isExport)
    {
        $columns = ['relation', 'amount', 'paid', 'balance'];

        $company = Auth::user()->loginaccount;
        $displayData = [];
        $reportTotals = [];

        $relations = Relation::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function($query) use ($startDate, $endDate) {
                            $query->where('invoice_date', '>=', $startDate)
                                  ->where('invoice_date', '<=', $endDate)
                                  ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                                  ->where('is_recurring', '=', false)
                                  ->withArchived();
                        }]);

        foreach ($relations->get() as $relation) {
            $amount = 0;
            $paid = 0;

            foreach ($relation->invoices as $invoice) {
                $amount += $invoice->amount;
                $paid += $invoice->getAmountPaid();
            }

            $displayData[] = [
                $isExport ? $relation->getDisplayName() : $relation->present()->link,
                $company->formatMoney($amount, $relation),
                $company->formatMoney($paid, $relation),
                $company->formatMoney($amount - $paid, $relation)
            ];

            $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'amount', $amount);
            $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'paid', $paid);
            $reportTotals = $this->addToTotals($reportTotals, $relation->currency_id, 'balance', $amount - $paid);
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $isExport
     * @return array
     */
    private function generateExpenseReport($startDate, $endDate, $isExport)
    {
        $columns = ['vendor', 'relation', 'date', 'expense_amount', 'invoiced_amount'];

        $company = Auth::user()->loginaccount;
        $displayData = [];
        $reportTotals = [];

        $expenses = Expense::scope()
                        ->withTrashed()
                        ->with('relation.contacts', 'vendor')
                        ->where('expense_date', '>=', $startDate)
                        ->where('expense_date', '<=', $endDate);


        foreach ($expenses->get() as $expense) {
            $amount = $expense->amount;
            $invoiced = $expense->present()->invoiced_amount;

            $displayData[] = [
                $expense->vendor ? ($isExport ? $expense->vendor->name : $expense->vendor->present()->link) : '',
                $expense->relation ? ($isExport ? $expense->relation->getDisplayName() : $expense->relation->present()->link) : '',
                $expense->present()->expense_date,
                Utils::formatMoney($amount, $expense->currency_id),
                Utils::formatMoney($invoiced, $expense->invoice_currency_id),
            ];

            $reportTotals = $this->addToTotals($reportTotals, $expense->expense_currency_id, 'amount', $amount);
            $reportTotals = $this->addToTotals($reportTotals, $expense->invoice_currency_id, 'amount', 0);

            $reportTotals = $this->addToTotals($reportTotals, $expense->invoice_currency_id, 'invoiced', $invoiced);
            $reportTotals = $this->addToTotals($reportTotals, $expense->expense_currency_id, 'invoiced', 0);
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    /**
     * @param $data
     * @param $currencyId
     * @param $field
     * @param $value
     * @return mixed
     */
    private function addToTotals($data, $currencyId, $field, $value) {
        $currencyId = $currencyId ?: Auth::user()->loginaccount->getCurrencyId();

        if (!isset($data[$currencyId][$field])) {
            $data[$currencyId][$field] = 0;
        }

        $data[$currencyId][$field] += $value;

        return $data;
    }

    /**
     * @param $reportType
     * @param $data
     * @param $columns
     * @param $totals
     */
    private function export($reportType, $data, $columns, $totals)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        $reportType = trans("texts.{$reportType}s");
        $date = date('Y-m-d');

        header('Content-Type:application/csv');
        header("Content-Disposition:attachment;filename={$date}_Ninja_{$reportType}.csv");

        Utils::exportData($output, $data, Utils::trans($columns));

        fwrite($output, trans('texts.totals'));
        foreach ($totals as $currencyId => $fields) {
            foreach ($fields as $key => $value) {
                fwrite($output, ',' . trans("texts.{$key}"));
            }
            fwrite($output, "\n");
            break;
        }

        foreach ($totals as $currencyId => $fields) {
            $csv = Utils::getFromCache($currencyId, 'currencies')->name . ',';
            foreach ($fields as $key => $value) {
                $csv .= '"' . Utils::formatMoney($value, $currencyId).'",';
            }
            fwrite($output, $csv."\n");
        }

        fclose($output);
        exit;
    }
}
