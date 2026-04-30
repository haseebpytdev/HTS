<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\BuildsSeoPageAttributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeoPageRequest extends FormRequest
{
    use BuildsSeoPageAttributes;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/', Rule::unique('seo_pages', 'page_key')],
            'title' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'string', 'max:500'],
            'schema_markup_json' => ['nullable', 'string', 'max:16000'],
            'is_indexable' => ['sometimes', 'boolean'],
        ];
    }
}
