<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Response extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'user_identifier',
        'ip_address',
        'browser',
        'os',
        'isp',
        'country',
        'city',

        // Demographic fields
        'age',
        'gender',
        'occupation',
        'marital_status',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }
}
