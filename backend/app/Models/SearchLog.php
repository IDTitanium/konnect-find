<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchLog extends Model
{
    protected $guarded = [];

    public function results(): HasMany
    {
        return $this->hasMany(SearchResult::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(SearchClick::class);
    }
}
