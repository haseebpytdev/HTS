<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentSecuritySettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalize = static function (mixed $value): array {
            if (is_array($value)) {
                return array_values(array_filter(array_map(static fn ($item) => strtolower(trim((string) $item)), $value)));
            }

            $parts = preg_split('/[\r\n,]+/', (string) $value) ?: [];

            return array_values(array_filter(array_map(static fn ($item) => strtolower(trim((string) $item)), $parts)));
        };

        $this->merge([
            'allowed_image_mimes' => $normalize($this->input('allowed_image_mimes')),
            'allowed_image_extensions' => $normalize($this->input('allowed_image_extensions')),
            'allowed_document_mimes' => $normalize($this->input('allowed_document_mimes')),
            'allowed_document_extensions' => $normalize($this->input('allowed_document_extensions')),
            'approval_required' => filter_var($this->input('approval_required', false), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-tenancy-settings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'allowed_image_mimes' => ['required', 'array', 'min:1'],
            'allowed_image_mimes.*' => ['required', 'string', 'max:120'],
            'allowed_image_extensions' => ['required', 'array', 'min:1'],
            'allowed_image_extensions.*' => ['required', 'string', 'max:20'],
            'allowed_document_mimes' => ['required', 'array', 'min:1'],
            'allowed_document_mimes.*' => ['required', 'string', 'max:120'],
            'allowed_document_extensions' => ['required', 'array', 'min:1'],
            'allowed_document_extensions.*' => ['required', 'string', 'max:20'],
            'max_file_size_bytes' => ['required', 'integer', 'min:1024', 'max:52428800'],
            'max_scan_file_size_bytes' => ['required', 'integer', 'min:1024', 'max:104857600'],
            'scan_mode' => ['required', Rule::in(['disabled', 'stub', 'real'])],
            'scan_provider' => ['required', Rule::in(['stub', 'clamav'])],
            'quarantine_behavior' => ['required', Rule::in(['block_all', 'admin_review_only', 'allow_all'])],
            'rescan_policy' => ['required', Rule::in(['manual_only', 'auto_on_failed', 'auto_on_failed_or_suspicious'])],
            'rescan_max_attempts' => ['required', 'integer', 'min:0', 'max:10'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'retention_purge_mode' => ['required', Rule::in(['archive_then_purge', 'hard_delete', 'keep_archived_only'])],
            'archive_default' => ['required', Rule::in(['soft_delete', 'archive'])],
            'download_restriction' => ['required', Rule::in(['scan_clean_only', 'validated_and_clean', 'admin_only_until_approved'])],
            'approval_requirement' => ['required', Rule::in(['none', 'customer_visible_only', 'all_documents'])],
            'approval_required' => ['required', 'boolean'],
        ];
    }
}
