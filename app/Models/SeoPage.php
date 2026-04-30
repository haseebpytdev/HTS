<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_key',
        'title',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'canonical_url',
        'schema_markup',
        'is_indexable',
    ];

    protected function casts(): array
    {
        return [
            'schema_markup' => 'array',
            'is_indexable' => 'boolean',
        ];
    }
}
