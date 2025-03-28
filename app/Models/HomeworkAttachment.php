<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }
}