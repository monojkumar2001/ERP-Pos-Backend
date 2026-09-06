<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = ['survey_id', 'question_text', 'question_type', 'is_required'];

    public function options()
    {
        return $this->hasMany(Option::class);
    }

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }
}
