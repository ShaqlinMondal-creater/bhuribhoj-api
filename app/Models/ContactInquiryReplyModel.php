<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactInquiryReplyModel extends Model
{
    protected $table = 'contact_inquiry_replies';

    protected $fillable = [
        'contact_inquiry_id',
        'sent_by',
        'body',
        'emailed_to',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(ContactInquiryModel::class, 'contact_inquiry_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'sent_by');
    }
}
