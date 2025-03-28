<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'interval',
        'features',
        'is_active',
        'children_limit',
        'sessions_limit',
        'storage_limit',
    ];

    protected $casts = [
        'price' => 'float',
        'is_active' => 'boolean',
        'features' => 'json',
        'children_limit' => 'integer',
        'sessions_limit' => 'integer',
        'storage_limit' => 'integer',
    ];

    /**
     * Get all subscriptions for the plan.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
