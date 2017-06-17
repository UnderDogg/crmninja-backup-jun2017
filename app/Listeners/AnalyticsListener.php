<?php namespace App\Listeners;

use Utils;
use App\Events\PaymentWasCreated;

/**
 * Class AnalyticsListener
 */
class AnalyticsListener
{
    /**
     * @param PaymentWasCreated $event
     */
    public function trackRevenue(PaymentWasCreated $event)
    {
        if (!Utils::isNinja() || !env('ANALYTICS_KEY')) {
            return;
        }

        $payment = $event->payment;
        $invoice = $payment->invoice;
        $company = $payment->loginaccount;

        if ($company->company_key != NINJA_COMPANY_KEY) {
            return;
        }

        $analyticsId = env('ANALYTICS_KEY');
        $relation = $payment->relation;
        $amount = $payment->amount;
        $item = $invoice->invoice_items->last()->product_key;

        $base = "v=1&tid={$analyticsId}&cid={$relation->public_id}&cu=USD&ti={$invoice->invoice_number}";

        $url = $base . "&t=transaction&ta=ninja&tr={$amount}";
        $this->sendAnalytics($url);

        $url = $base . "&t=item&in={$item}&ip={$amount}&iq=1";
        $this->sendAnalytics($url);
    }

    /**
     * @param $data
     */
    private function sendAnalytics($data)
    {
        $data = utf8_encode($data);
        $curl = curl_init();

        $opts = [
            CURLOPT_URL => GOOGLE_ANALYITCS_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => 'POST',
            CURLOPT_POSTFIELDS => $data,
        ];

        curl_setopt_array($curl, $opts);
        curl_exec($curl);
        curl_close($curl);
    }
}
