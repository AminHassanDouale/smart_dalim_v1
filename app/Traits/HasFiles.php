<?php

namespace App\Traits;

use App\Models\File;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasFiles
{
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function getFilesByCollection(string $collection)
    {
        return $this->files()->where('collection', $collection)->get();
    }
}
