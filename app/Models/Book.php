<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'description',
        'cover_image',
        'file_path',
        'audio_url',
        'total_pages',
        'pdf_file'
    ];
}
