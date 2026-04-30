<?php

namespace App\Http\Requests\Admin\Concerns;

trait BuildsSeoPageAttributes
{
    /**
     * Normalized attributes for SeoPage create/update (schema JSON + is_indexable).
     *
     * @return array<string, mixed>
     */
    public function toSeoPageAttributes(): array
    {
        $data = $this->validated();
        $raw = $this->input('schema_markup_json');
        $decoded = null;
        if (is_string($raw) && trim($raw) !== '') {
            $tmp = json_decode($raw, true);
            $decoded = is_array($tmp) ? $tmp : null;
        }
        $data['schema_markup'] = $decoded;
        unset($data['schema_markup_json']);
        $data['is_indexable'] = $this->boolean('is_indexable', true);

        return $data;
    }
}
