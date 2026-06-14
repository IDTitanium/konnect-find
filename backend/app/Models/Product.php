<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'text_embedding',
        'image_embedding',
        'text_embedding_vector',
        'image_embedding_vector',
    ];

    protected function casts(): array
    {
        return [
            'search_terms' => 'array',
            'price_kobo' => 'integer',
            'text_embedding' => 'array',
            'image_embedding' => 'array',
            'embeddings_indexed_at' => 'datetime',
            'is_active' => 'boolean',
            'inventory_count' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function searchResults(): HasMany
    {
        return $this->hasMany(SearchResult::class);
    }
}
