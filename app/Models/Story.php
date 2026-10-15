<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    protected $guarded = ['id'];

    public function getEstimatedWordCountAttribute(): int
    {
        if ($this->relationLoaded('published_chapters')) {
            return $this->getRelation('published_chapters')->sum(fn ($c) => preg_match_all("/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/u", strip_tags($c->body)));
        }

        return preg_match_all("/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/u", strip_tags($this->body));
    }

    protected function casts(): array
    {
        return ['glossary' => 'array', 'published_at' => 'datetime', 'is_premium' => 'boolean'];
    }
}
