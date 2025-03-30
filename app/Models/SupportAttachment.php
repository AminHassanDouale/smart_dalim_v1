<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SupportAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'support_ticket_id',
        'support_message_id',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
    ];

    // Relationships
    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function message()
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }

    // Methods
    public function getUrl()
    {
        return Storage::url($this->file_path);
    }

    public function delete()
    {
        // Delete the file from storage when deleting the record
        Storage::delete($this->file_path);

        return parent::delete();
    }

    public function getFileSizeForHumans()
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } else {
            $bytes = $bytes . ' bytes';
        }

        return $bytes;
    }

    public function isImage()
    {
        return strpos($this->file_type, 'image/') === 0;
    }

    public function isPdf()
    {
        return $this->file_type === 'application/pdf';
    }

    public function isDocument()
    {
        $documentTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain'
        ];

        return in_array($this->file_type, $documentTypes);
    }
}
