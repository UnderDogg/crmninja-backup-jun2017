<?php namespace App\Models;

use Eloquent;
use Auth;
use Cache;

/**
 * Class InvoiceDesign
 */
class InvoiceDesign extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return mixed
     */
    public static function getDesigns()
    {
        $company = Auth::user()->loginaccount;
        $designs = Cache::get('invoiceDesigns');

        foreach ($designs as $design) {
            if ($design->id > Auth::user()->maxInvoiceDesignId()) {
                $designs->pull($design->id);
            }

            $design->javascript = $design->pdfmake;
            $design->pdfmake = null;

            if ($design->id == CUSTOM_DESIGN) {
                if ($company->custom_design) {
                    $design->javascript = $company->custom_design;
                } else {
                    $designs->pop();
                }
            }
        }

        return $designs;
    }
}