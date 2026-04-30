<?php

namespace App\Http\Requests\Admin;

use App\Models\ContentBlock;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContentBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ContentBlock $block */
        $block = $this->route('content_block');

        return match ($block->block_key) {
            'home.hero' => [
                'headline' => ['nullable', 'string', 'max:255'],
                'subheadline' => ['nullable', 'string', 'max:500'],
                'background_image' => ['nullable', 'string', 'max:500'],
                'background_upload' => ['nullable', 'image', 'max:5120'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'home.why_us' => [
                'section_title' => ['nullable', 'string', 'max:255'],
                'section_subtitle' => ['nullable', 'string', 'max:500'],
                'items' => ['nullable', 'array'],
                'items.*.title' => ['nullable', 'string', 'max:255'],
                'items.*.description' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'home.group_tickets' => [
                'section_title' => ['nullable', 'string', 'max:255'],
                'items' => ['nullable', 'array'],
                'items.*.title' => ['nullable', 'string', 'max:255'],
                'items.*.tag' => ['nullable', 'string', 'max:120'],
                'items.*.link' => ['nullable', 'string', 'max:500'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'home.deals' => [
                'section_title' => ['nullable', 'string', 'max:255'],
                'items' => ['nullable', 'array'],
                'items.*.title' => ['nullable', 'string', 'max:255'],
                'items.*.subtitle' => ['nullable', 'string', 'max:500'],
                'items.*.link' => ['nullable', 'string', 'max:500'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            default => [
                'payload_json' => ['required', 'json', 'max:32000'],
                'is_active' => ['sometimes', 'boolean'],
            ],
        };
    }

    public function payloadForBlock(ContentBlock $block, ?string $newBackgroundPath = null): array
    {
        $data = $this->validated();

        return match ($block->block_key) {
            'home.hero' => [
                'headline' => $data['headline'] ?? '',
                'subheadline' => $data['subheadline'] ?? '',
                'background_image' => $newBackgroundPath ?? ($data['background_image'] ?? ''),
            ],
            'home.why_us' => [
                'section_title' => $data['section_title'] ?? '',
                'section_subtitle' => $data['section_subtitle'] ?? '',
                'items' => array_values(array_filter(
                    $data['items'] ?? [],
                    static fn (array $row): bool => ! empty(trim((string) ($row['title'] ?? '')))
                )),
            ],
            'home.group_tickets' => [
                'section_title' => $data['section_title'] ?? '',
                'items' => array_values(array_filter(
                    $data['items'] ?? [],
                    static fn (array $row): bool => ! empty(trim((string) ($row['title'] ?? '')))
                )),
            ],
            'home.deals' => [
                'section_title' => $data['section_title'] ?? '',
                'items' => array_values(array_filter(
                    $data['items'] ?? [],
                    static fn (array $row): bool => ! empty(trim((string) ($row['title'] ?? '')))
                )),
            ],
            default => json_decode((string) ($data['payload_json'] ?? '{}'), true) ?: [],
        };
    }
}
