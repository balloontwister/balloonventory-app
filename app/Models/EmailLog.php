<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['to', 'subject', 'mailable', 'user_id', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
