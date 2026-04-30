<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCmsSettingsRequest extends FormRequest
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
            'show_hero' => ['sometimes', 'boolean'],
            'show_why_us' => ['sometimes', 'boolean'],
            'show_group_tickets' => ['sometimes', 'boolean'],
            'show_deals' => ['sometimes', 'boolean'],
            'promo_banner_text' => ['nullable', 'string', 'max:500'],
            'promo_banner_link' => ['nullable', 'string', 'max:2048'],
            'show_featured_packages' => ['sometimes', 'boolean'],
            'show_featured_groups' => ['sometimes', 'boolean'],
            'footer_company_blurb' => ['nullable', 'string', 'max:2000'],
            'footer_address' => ['nullable', 'string', 'max:500'],
            'footer_phone' => ['nullable', 'string', 'max:120'],
            'footer_email' => ['nullable', 'string', 'max:255'],
            'footer_facebook_url' => ['nullable', 'string', 'max:2048'],
            'footer_instagram_url' => ['nullable', 'string', 'max:2048'],
            'footer_youtube_url' => ['nullable', 'string', 'max:2048'],
            'footer_whatsapp_url' => ['nullable', 'string', 'max:2048'],
            'footer_support_url' => ['nullable', 'string', 'max:2048'],
            'footer_privacy_url' => ['nullable', 'string', 'max:2048'],
            'footer_terms_url' => ['nullable', 'string', 'max:2048'],
            'footer_blog_url' => ['nullable', 'string', 'max:2048'],
            'show_blog_link' => ['sometimes', 'boolean'],
            'show_bank_link' => ['sometimes', 'boolean'],
            'show_about_link' => ['sometimes', 'boolean'],
            'show_contact_link' => ['sometimes', 'boolean'],
            'about_title' => ['nullable', 'string', 'max:255'],
            'about_subtitle' => ['nullable', 'string', 'max:1200'],
            'about_who_we_are_heading' => ['nullable', 'string', 'max:255'],
            'about_who_we_are_body' => ['nullable', 'string', 'max:3000'],
            'about_who_we_are_body_2' => ['nullable', 'string', 'max:3000'],
            'about_why_heading' => ['nullable', 'string', 'max:255'],
            'about_highlight_1' => ['nullable', 'string', 'max:500'],
            'about_highlight_2' => ['nullable', 'string', 'max:500'],
            'about_highlight_3' => ['nullable', 'string', 'max:500'],
            'about_highlight_4' => ['nullable', 'string', 'max:500'],
            'about_cta_text' => ['nullable', 'string', 'max:120'],
            'bank_title' => ['nullable', 'string', 'max:255'],
            'bank_subtitle' => ['nullable', 'string', 'max:1200'],
            'bank_notice' => ['nullable', 'string', 'max:1200'],
            'bank_local_title' => ['nullable', 'string', 'max:255'],
            'bank_local_bank_name' => ['nullable', 'string', 'max:255'],
            'bank_local_account_title' => ['nullable', 'string', 'max:255'],
            'bank_local_account_number' => ['nullable', 'string', 'max:255'],
            'bank_local_iban' => ['nullable', 'string', 'max:255'],
            'bank_local_branch' => ['nullable', 'string', 'max:255'],
            'bank_usd_title' => ['nullable', 'string', 'max:255'],
            'bank_usd_bank_name' => ['nullable', 'string', 'max:255'],
            'bank_usd_account_title' => ['nullable', 'string', 'max:255'],
            'bank_usd_swift' => ['nullable', 'string', 'max:255'],
            'bank_usd_iban' => ['nullable', 'string', 'max:255'],
            'bank_usd_note' => ['nullable', 'string', 'max:700'],
            'bank_after_pay_heading' => ['nullable', 'string', 'max:255'],
            'bank_after_pay_step_1' => ['nullable', 'string', 'max:500'],
            'bank_after_pay_step_2' => ['nullable', 'string', 'max:500'],
            'bank_after_pay_step_3' => ['nullable', 'string', 'max:500'],
            'bank_cta_text' => ['nullable', 'string', 'max:120'],
            'contact_title' => ['nullable', 'string', 'max:255'],
            'contact_subtitle' => ['nullable', 'string', 'max:1200'],
            'contact_office' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'contact_whatsapp_hint' => ['nullable', 'string', 'max:500'],
            'contact_hours_heading' => ['nullable', 'string', 'max:255'],
            'contact_hours_subtitle' => ['nullable', 'string', 'max:1200'],
            'contact_weekday_label' => ['nullable', 'string', 'max:255'],
            'contact_weekday_hours' => ['nullable', 'string', 'max:255'],
            'contact_sunday_label' => ['nullable', 'string', 'max:255'],
            'contact_sunday_hours' => ['nullable', 'string', 'max:255'],
            'contact_start_online_heading' => ['nullable', 'string', 'max:255'],
            'contact_start_online_text' => ['nullable', 'string', 'max:1000'],
            'contact_quote_cta_text' => ['nullable', 'string', 'max:120'],
            'contact_package_cta_text' => ['nullable', 'string', 'max:120'],
        ];
    }
}
