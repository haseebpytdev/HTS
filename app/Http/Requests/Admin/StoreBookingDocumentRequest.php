<?php

namespace App\Http\Requests\Admin;

use App\Services\Documents\DocumentUploadSecurityService;
use App\Services\Documents\DocumentSecuritySettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', Rule::in([
                'passport',
                'visa',
                'ticket',
                'payment_proof',
                'voucher',
                'invoice',
                'other',
            ])],
            'file' => ['required', 'file', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof \Illuminate\Http\UploadedFile) {
                    $fail('Invalid file payload.');
                    return;
                }

                $result = app(DocumentUploadSecurityService::class)->inspect($value);
                if ($result['category'] === 'blocked') {
                    $fail('Executable or script-like files are not allowed.');
                    return;
                }
                if ($result['category'] === 'oversize') {
                    $maxBytes = (int) data_get(app(DocumentSecuritySettingsService::class)->policy(), 'max_file_size_bytes', 10485760);
                    $maxMb = max(1, (int) floor($maxBytes / 1024 / 1024));
                    $fail("File exceeds max upload size of {$maxMb} MB.");
                    return;
                }
                if ($result['category'] === 'unsupported') {
                    $fail('Unsupported file type. Only PDF/JPG/JPEG/PNG/WEBP are allowed.');
                }
            }],
            'is_customer_visible' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
