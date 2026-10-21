<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotInquiry extends Model
{
    protected $fillable = [
        'matched_intent',
        'suggested_intent',
        'suggested_question',
        'locale',
        'confidence',
        'outcome',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];
}
