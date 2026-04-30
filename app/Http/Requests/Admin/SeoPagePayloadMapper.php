<?php

namespace App\Http\Requests\Admin;

/**
 * Normalizes validated SEO form input into model attributes (schema JSON + flags).
 */
final class SeoPagePayloadMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function attributes(StoreSeoPageRequest|UpdateSeoPageRequest $request): array
    {
        $data = $request->validated();
        $raw = $request->input('schema_markup_json');
        $decoded = null;
        if (is_string($raw) && trim($raw) !== '') {
            $tmp = json_decode($raw, true);
            $decoded = is_array($tmp) ? $tmp : null;
        }
        $data['schema_markup'] = $decoded;
        unset($data['schema_markup_json']);
        $data['is_indexable'] = $request->boolean('is_indexable', true);

        return $data;
    }
}
