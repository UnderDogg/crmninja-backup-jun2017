<?php namespace App\Models;

use Utils;
use Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Invitation
 */
class Invitation extends EntityModel
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_INVITATION;
    }

    /**
     * @return mixed
     */
    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo('App\Models\Contact')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function loginaccount()
    {
        return $this->belongsTo('App\Models\Company');
    }

    // If we're getting the link for PhantomJS to generate the PDF
    // we need to make sure it's served from our site
    /**
     * @param string $type
     * @param bool $forceOnsite
     * @return string
     */
    public function getLink($type = 'view', $forceOnsite = false)
    {
        if (!$this->loginaccount) {
            $this->load('loginaccount');
        }

        $url = trim(SITE_URL, '/');
        $iframe_url = $this->loginaccount->iframe_url;

        if ($this->loginaccount->hasFeature(FEATURE_CUSTOM_URL)) {
            if ($iframe_url && !$forceOnsite) {
                return "{$iframe_url}?{$this->invitation_key}";
            } elseif ($this->loginaccount->subdomain) {
                $url = Utils::replaceSubdomain($url, $this->loginaccount->subdomain);
            }
        }

        return "{$url}/{$type}/{$this->invitation_key}";
    }

    /**
     * @return bool|string
     */
    public function getStatus()
    {
        $hasValue = false;
        $parts = [];
        $statuses = $this->message_id ? ['sent', 'opened', 'viewed'] : ['sent', 'viewed'];

        foreach ($statuses as $status) {
            $field = "{$status}_date";
            $date = '';
            if ($this->$field && $this->field != '0000-00-00 00:00:00') {
                $date = Utils::dateToString($this->$field);
                $hasValue = true;
            }
            $parts[] = trans('texts.invitation_status_' . $status) . ': ' . $date;
        }

        return $hasValue ? implode($parts, '<br/>') : false;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->invitation_key;
    }

    /**
     * @param null $messageId
     */
    public function markSent($messageId = null)
    {
        $this->message_id = $messageId;
        $this->email_error = null;
        $this->sent_date = Carbon::now()->toDateTimeString();
        $this->save();
    }

    public function markViewed()
    {
        $invoice = $this->invoice;
        $relation = $invoice->relation;

        $this->viewed_date = Carbon::now()->toDateTimeString();
        $this->save();

        $invoice->markViewed();
        $relation->markLoggedIn();
    }
}
