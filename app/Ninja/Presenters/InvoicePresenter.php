<?php namespace App\Ninja\Presenters;

use Utils;
use App\Libraries\Skype\InvoiceCard;

class InvoicePresenter extends EntityPresenter
{

    public function relation()
    {
        return $this->entity->relation ? $this->entity->relation->getDisplayName() : '';
    }

    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    public function amount()
    {
        $invoice = $this->entity;
        $company = $invoice->loginaccount;

        return $company->formatMoney($invoice->amount, $invoice->relation);
    }

    public function requestedAmount()
    {
        $invoice = $this->entity;
        $company = $invoice->loginaccount;

        return $company->formatMoney($invoice->getRequestedAmount(), $invoice->relation);
    }

    public function balanceDueLabel()
    {
        if ($this->entity->partial > 0) {
            return 'partial_due';
        } elseif ($this->entity->isType(INVOICE_TYPE_QUOTE)) {
            return 'total';
        } else {
            return 'balance_due';
        }
    }

    public function dueDateLabel()
    {
        if ($this->entity->isType(INVOICE_TYPE_STANDARD)) {
            return trans('texts.due_date');
        } else {
            return trans('texts.valid_until');
        }
    }

    public function discount()
    {
        $invoice = $this->entity;

        if ($invoice->is_amount_discount) {
            return $invoice->loginaccount->formatMoney($invoice->discount);
        } else {
            return $invoice->discount . '%';
        }
    }

    // https://schema.org/PaymentStatusType
    public function paymentStatus()
    {
        if (!$this->entity->balance) {
            return 'PaymentComplete';
        } elseif ($this->entity->isOverdue()) {
            return 'PaymentPastDue';
        } else {
            return 'PaymentDue';
        }
    }

    public function status()
    {
        if ($this->entity->is_deleted) {
            return trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            return trans('texts.archived');
        } elseif ($this->entity->is_recurring) {
            return trans('texts.active');
        } else {
            $status = $this->entity->invoice_status ? $this->entity->invoice_status->name : 'draft';
            $status = strtolower($status);
            return trans("texts.status_{$status}");
        }
    }

    public function invoice_date()
    {
        return Utils::fromSqlDate($this->entity->invoice_date);
    }

    public function due_date()
    {
        return Utils::fromSqlDate($this->entity->due_date);
    }

    public function frequency()
    {
        $frequency = $this->entity->frequency ? $this->entity->frequency->name : '';
        $frequency = strtolower($frequency);
        return trans('texts.freq_' . $frequency);
    }

    public function email()
    {
        $relation = $this->entity->relation;
        return count($relation->contacts) ? $relation->contacts[0]->email : '';
    }

    public function autoBillEmailMessage()
    {
        $relation = $this->entity->relation;
        $paymentMethod = $relation->defaultPaymentMethod();

        if (!$paymentMethod) {
            return false;
        }

        if ($paymentMethod->payment_type_id == PAYMENT_TYPE_ACH) {
            $paymentMethodString = trans('texts.auto_bill_payment_method_bank_transfer');
        } elseif ($paymentMethod->payment_type_id == PAYMENT_TYPE_PAYPAL) {
            $paymentMethodString = trans('texts.auto_bill_payment_method_paypal');
        } else {
            $paymentMethodString = trans('texts.auto_bill_payment_method_credit_card');
        }

        $data = [
            'payment_method' => $paymentMethodString,
            'due_date' => $this->due_date(),
        ];

        return trans('texts.auto_bill_notification', $data);
    }

    public function skypeBot()
    {
        return new InvoiceCard($this->entity);
    }
}
