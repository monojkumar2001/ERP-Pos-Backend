<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    use HasFactory;

    protected $fillable = ['question_id', 'option_text'];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];


    public function responseDetails()
    {
        // Adjust the relationship type and foreign key as needed
        return $this->hasMany(\App\Models\ResponseDetail::class, 'option_id');
    }
}
