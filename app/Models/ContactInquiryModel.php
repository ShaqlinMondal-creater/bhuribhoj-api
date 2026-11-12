<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactInquiryModel extends Model
{
    protected $table = 'contact_inquiries';

    protected $fillable = [
        'name',
        'mess',
        'members',
        'phone',
        'email',
        'message',
        'status',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'members' => 'integer',
        ];
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContactInquiryReplyModel::class, 'contact_inquiry_id')->orderBy('id');
    }
}
