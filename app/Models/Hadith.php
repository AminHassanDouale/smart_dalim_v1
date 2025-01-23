<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hadith extends Model
{
    protected $fillable = [
        'hadith_id',
        'source',
        'chapter_no',
        'hadith_no',
        'chapter',
        'chain_indx',
        'text_ar',
        'text_en'
    ];
}
