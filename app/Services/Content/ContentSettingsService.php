<?php

namespace App\Services\Content;

use App\Services\System\SystemSettingsService;

final class ContentSettingsService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function frontendSettings(): array
    {
        return [
            'homepage' => [
                'show_hero' => $this->settings->getBool('content.homepage.show_hero', true, ['scope' => 'platform', 'category' => 'content']),
                'show_why_us' => $this->settings->getBool('content.homepage.show_why_us', true, ['scope' => 'platform', 'category' => 'content']),
                'show_group_tickets' => $this->settings->getBool('content.homepage.show_group_tickets', true, ['scope' => 'platform', 'category' => 'content']),
                'show_deals' => $this->settings->getBool('content.homepage.show_deals', true, ['scope' => 'platform', 'category' => 'content']),
                'promo_banner_text' => $this->settings->getString('content.homepage.promo_banner_text', '', ['scope' => 'platform', 'category' => 'content']),
                'promo_banner_link' => $this->settings->getString('content.homepage.promo_banner_link', '', ['scope' => 'platform', 'category' => 'content']),
            ],
            'featured' => [
                'show_featured_packages' => $this->settings->getBool('content.featured.show_packages', true, ['scope' => 'platform', 'category' => 'content']),
                'show_featured_groups' => $this->settings->getBool('content.featured.show_groups', true, ['scope' => 'platform', 'category' => 'content']),
            ],
            'footer' => [
                'company_blurb' => $this->settings->getString('content.footer.company_blurb', 'Your trusted partner for flights, group tickets, and Umrah packages.', ['scope' => 'platform', 'category' => 'content']),
                'address' => $this->settings->getString('content.footer.address', 'Lahore, Pakistan', ['scope' => 'platform', 'category' => 'content']),
                'phone' => $this->settings->getString('content.footer.phone', '+92 300 0000000', ['scope' => 'platform', 'category' => 'content']),
                'email' => $this->settings->getString('content.footer.email', config('brand.support_email'), ['scope' => 'platform', 'category' => 'content']),
                'facebook_url' => $this->settings->getString('content.footer.facebook_url', '#', ['scope' => 'platform', 'category' => 'content']),
                'instagram_url' => $this->settings->getString('content.footer.instagram_url', '#', ['scope' => 'platform', 'category' => 'content']),
                'youtube_url' => $this->settings->getString('content.footer.youtube_url', '#', ['scope' => 'platform', 'category' => 'content']),
                'whatsapp_url' => $this->settings->getString('content.footer.whatsapp_url', '#', ['scope' => 'platform', 'category' => 'content']),
                'support_url' => $this->settings->getString('content.footer.support_url', '#', ['scope' => 'platform', 'category' => 'content']),
                'privacy_url' => $this->settings->getString('content.footer.privacy_url', '#', ['scope' => 'platform', 'category' => 'content']),
                'terms_url' => $this->settings->getString('content.footer.terms_url', '#', ['scope' => 'platform', 'category' => 'content']),
                'blog_url' => $this->settings->getString('content.footer.blog_url', '', ['scope' => 'platform', 'category' => 'content']),
            ],
            'top_nav' => [
                'show_blog_link' => $this->settings->getBool('content.top_nav.show_blog_link', true, ['scope' => 'platform', 'category' => 'content']),
                'show_bank_link' => $this->settings->getBool('content.top_nav.show_bank_link', true, ['scope' => 'platform', 'category' => 'content']),
                'show_about_link' => $this->settings->getBool('content.top_nav.show_about_link', true, ['scope' => 'platform', 'category' => 'content']),
                'show_contact_link' => $this->settings->getBool('content.top_nav.show_contact_link', true, ['scope' => 'platform', 'category' => 'content']),
            ],
            'about_page' => [
                'title' => $this->settings->getString('content.about.title', 'About '.config('brand.name'), ['scope' => 'platform', 'category' => 'content']),
                'subtitle' => $this->settings->getString('content.about.subtitle', 'We help travellers book smarter: competitive fares, transparent group ticketing, and curated Umrah packages — backed by a Lahore-based team you can reach by phone or WhatsApp.', ['scope' => 'platform', 'category' => 'content']),
                'who_we_are_heading' => $this->settings->getString('content.about.who_we_are_heading', 'Who we are', ['scope' => 'platform', 'category' => 'content']),
                'who_we_are_body' => $this->settings->getString('content.about.who_we_are_body', config('brand.name').' is built for Pakistani travellers who want clear pricing, honest advice, and reliable support for flights, groups, and religious travel. Our consultants combine years of airline and tour experience with tools that keep your options organised.', ['scope' => 'platform', 'category' => 'content']),
                'who_we_are_body_2' => $this->settings->getString('content.about.who_we_are_body_2', 'Whether you need a single sector, a multi-city itinerary, or seats on a departing group, we focus on what matters: correct fares, timely ticketing, and communication you can trust.', ['scope' => 'platform', 'category' => 'content']),
                'why_heading' => $this->settings->getString('content.about.why_heading', 'Why travellers choose us', ['scope' => 'platform', 'category' => 'content']),
                'highlight_1' => $this->settings->getString('content.about.highlight_1', 'Central Lahore presence and responsive customer care', ['scope' => 'platform', 'category' => 'content']),
                'highlight_2' => $this->settings->getString('content.about.highlight_2', 'Group departures and Umrah packages in one place', ['scope' => 'platform', 'category' => 'content']),
                'highlight_3' => $this->settings->getString('content.about.highlight_3', 'Straightforward bank transfer details and booking follow-up', ['scope' => 'platform', 'category' => 'content']),
                'highlight_4' => $this->settings->getString('content.about.highlight_4', 'Quote and inquiry flows designed for your itinerary, not generic forms', ['scope' => 'platform', 'category' => 'content']),
                'cta_text' => $this->settings->getString('content.about.cta_text', 'Contact our team', ['scope' => 'platform', 'category' => 'content']),
            ],
            'bank_page' => [
                'title' => $this->settings->getString('content.bank.title', 'Bank Details', ['scope' => 'platform', 'category' => 'content']),
                'subtitle' => $this->settings->getString('content.bank.subtitle', 'Use these details for bank transfers. Share your payment proof and booking reference with our team so we can confirm receipt.', ['scope' => 'platform', 'category' => 'content']),
                'notice' => $this->settings->getString('content.bank.notice', 'Account titles and IBANs are placeholders. Replace when finance confirms live details.', ['scope' => 'platform', 'category' => 'content']),
                'local_title' => $this->settings->getString('content.bank.local_title', 'PKR local transfer', ['scope' => 'platform', 'category' => 'content']),
                'local_bank_name' => $this->settings->getString('content.bank.local_bank_name', 'Your Bank Limited', ['scope' => 'platform', 'category' => 'content']),
                'local_account_title' => $this->settings->getString('content.bank.local_account_title', config('brand.legal_name'), ['scope' => 'platform', 'category' => 'content']),
                'local_account_number' => $this->settings->getString('content.bank.local_account_number', '0123-4567890-01', ['scope' => 'platform', 'category' => 'content']),
                'local_iban' => $this->settings->getString('content.bank.local_iban', 'PK00XXXX0000000000000000', ['scope' => 'platform', 'category' => 'content']),
                'local_branch' => $this->settings->getString('content.bank.local_branch', 'Lahore', ['scope' => 'platform', 'category' => 'content']),
                'usd_title' => $this->settings->getString('content.bank.usd_title', 'USD wire', ['scope' => 'platform', 'category' => 'content']),
                'usd_bank_name' => $this->settings->getString('content.bank.usd_bank_name', 'Correspondent Bank', ['scope' => 'platform', 'category' => 'content']),
                'usd_account_title' => $this->settings->getString('content.bank.usd_account_title', config('brand.legal_name'), ['scope' => 'platform', 'category' => 'content']),
                'usd_swift' => $this->settings->getString('content.bank.usd_swift', 'XXXXXXXX', ['scope' => 'platform', 'category' => 'content']),
                'usd_iban' => $this->settings->getString('content.bank.usd_iban', 'PK00XXXX0000000000000000', ['scope' => 'platform', 'category' => 'content']),
                'usd_note' => $this->settings->getString('content.bank.usd_note', 'Include remitter name and booking reference.', ['scope' => 'platform', 'category' => 'content']),
                'after_pay_heading' => $this->settings->getString('content.bank.after_pay_heading', 'After you pay', ['scope' => 'platform', 'category' => 'content']),
                'after_pay_step_1' => $this->settings->getString('content.bank.after_pay_step_1', 'Send a clear screenshot or PDF of the transfer receipt.', ['scope' => 'platform', 'category' => 'content']),
                'after_pay_step_2' => $this->settings->getString('content.bank.after_pay_step_2', 'Mention passenger names, route, and travel dates.', ['scope' => 'platform', 'category' => 'content']),
                'after_pay_step_3' => $this->settings->getString('content.bank.after_pay_step_3', 'Wait for finance to confirm; we update your booking in the system.', ['scope' => 'platform', 'category' => 'content']),
                'cta_text' => $this->settings->getString('content.bank.cta_text', 'Contact us', ['scope' => 'platform', 'category' => 'content']),
            ],
            'contact_page' => [
                'title' => $this->settings->getString('content.contact.title', 'Contact Us', ['scope' => 'platform', 'category' => 'content']),
                'subtitle' => $this->settings->getString('content.contact.subtitle', 'Reach our Lahore team for flights, group tickets, Umrah packages, or general support. We respond as quickly as we can during business hours.', ['scope' => 'platform', 'category' => 'content']),
                'office' => $this->settings->getString('content.contact.office', 'Lahore, Pakistan', ['scope' => 'platform', 'category' => 'content']),
                'phone' => $this->settings->getString('content.contact.phone', '+92 300 0000000', ['scope' => 'platform', 'category' => 'content']),
                'email' => $this->settings->getString('content.contact.email', config('brand.support_email'), ['scope' => 'platform', 'category' => 'content']),
                'whatsapp_hint' => $this->settings->getString('content.contact.whatsapp_hint', 'Message us for quick fare checks and group seat holds.', ['scope' => 'platform', 'category' => 'content']),
                'hours_heading' => $this->settings->getString('content.contact.hours_heading', 'Business hours', ['scope' => 'platform', 'category' => 'content']),
                'hours_subtitle' => $this->settings->getString('content.contact.hours_subtitle', 'Timings may vary on public holidays. For urgent departures, call or WhatsApp and mention your travel date.', ['scope' => 'platform', 'category' => 'content']),
                'weekday_label' => $this->settings->getString('content.contact.weekday_label', 'Monday – Saturday', ['scope' => 'platform', 'category' => 'content']),
                'weekday_hours' => $this->settings->getString('content.contact.weekday_hours', '10:00 – 19:00 (PKT)', ['scope' => 'platform', 'category' => 'content']),
                'sunday_label' => $this->settings->getString('content.contact.sunday_label', 'Sunday', ['scope' => 'platform', 'category' => 'content']),
                'sunday_hours' => $this->settings->getString('content.contact.sunday_hours', 'Limited desk — WhatsApp preferred', ['scope' => 'platform', 'category' => 'content']),
                'start_online_heading' => $this->settings->getString('content.contact.start_online_heading', 'Start online', ['scope' => 'platform', 'category' => 'content']),
                'start_online_text' => $this->settings->getString('content.contact.start_online_text', 'For flight quotes and package inquiries, use our forms so we have your route and dates on file.', ['scope' => 'platform', 'category' => 'content']),
                'quote_cta_text' => $this->settings->getString('content.contact.quote_cta_text', 'Quote inquiry', ['scope' => 'platform', 'category' => 'content']),
                'package_cta_text' => $this->settings->getString('content.contact.package_cta_text', 'Browse packages', ['scope' => 'platform', 'category' => 'content']),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function saveFrontendSettings(array $data): void
    {
        $flat = [
            'content.homepage.show_hero' => (bool) ($data['show_hero'] ?? true),
            'content.homepage.show_why_us' => (bool) ($data['show_why_us'] ?? true),
            'content.homepage.show_group_tickets' => (bool) ($data['show_group_tickets'] ?? true),
            'content.homepage.show_deals' => (bool) ($data['show_deals'] ?? true),
            'content.homepage.promo_banner_text' => (string) ($data['promo_banner_text'] ?? ''),
            'content.homepage.promo_banner_link' => (string) ($data['promo_banner_link'] ?? ''),
            'content.featured.show_packages' => (bool) ($data['show_featured_packages'] ?? true),
            'content.featured.show_groups' => (bool) ($data['show_featured_groups'] ?? true),
            'content.footer.company_blurb' => (string) ($data['footer_company_blurb'] ?? ''),
            'content.footer.address' => (string) ($data['footer_address'] ?? ''),
            'content.footer.phone' => (string) ($data['footer_phone'] ?? ''),
            'content.footer.email' => (string) ($data['footer_email'] ?? ''),
            'content.footer.facebook_url' => (string) ($data['footer_facebook_url'] ?? '#'),
            'content.footer.instagram_url' => (string) ($data['footer_instagram_url'] ?? '#'),
            'content.footer.youtube_url' => (string) ($data['footer_youtube_url'] ?? '#'),
            'content.footer.whatsapp_url' => (string) ($data['footer_whatsapp_url'] ?? '#'),
            'content.footer.support_url' => (string) ($data['footer_support_url'] ?? '#'),
            'content.footer.privacy_url' => (string) ($data['footer_privacy_url'] ?? '#'),
            'content.footer.terms_url' => (string) ($data['footer_terms_url'] ?? '#'),
            'content.footer.blog_url' => (string) ($data['footer_blog_url'] ?? ''),
            'content.top_nav.show_blog_link' => (bool) ($data['show_blog_link'] ?? true),
            'content.top_nav.show_bank_link' => (bool) ($data['show_bank_link'] ?? true),
            'content.top_nav.show_about_link' => (bool) ($data['show_about_link'] ?? true),
            'content.top_nav.show_contact_link' => (bool) ($data['show_contact_link'] ?? true),
            'content.about.title' => (string) ($data['about_title'] ?? ''),
            'content.about.subtitle' => (string) ($data['about_subtitle'] ?? ''),
            'content.about.who_we_are_heading' => (string) ($data['about_who_we_are_heading'] ?? ''),
            'content.about.who_we_are_body' => (string) ($data['about_who_we_are_body'] ?? ''),
            'content.about.who_we_are_body_2' => (string) ($data['about_who_we_are_body_2'] ?? ''),
            'content.about.why_heading' => (string) ($data['about_why_heading'] ?? ''),
            'content.about.highlight_1' => (string) ($data['about_highlight_1'] ?? ''),
            'content.about.highlight_2' => (string) ($data['about_highlight_2'] ?? ''),
            'content.about.highlight_3' => (string) ($data['about_highlight_3'] ?? ''),
            'content.about.highlight_4' => (string) ($data['about_highlight_4'] ?? ''),
            'content.about.cta_text' => (string) ($data['about_cta_text'] ?? ''),
            'content.bank.title' => (string) ($data['bank_title'] ?? ''),
            'content.bank.subtitle' => (string) ($data['bank_subtitle'] ?? ''),
            'content.bank.notice' => (string) ($data['bank_notice'] ?? ''),
            'content.bank.local_title' => (string) ($data['bank_local_title'] ?? ''),
            'content.bank.local_bank_name' => (string) ($data['bank_local_bank_name'] ?? ''),
            'content.bank.local_account_title' => (string) ($data['bank_local_account_title'] ?? ''),
            'content.bank.local_account_number' => (string) ($data['bank_local_account_number'] ?? ''),
            'content.bank.local_iban' => (string) ($data['bank_local_iban'] ?? ''),
            'content.bank.local_branch' => (string) ($data['bank_local_branch'] ?? ''),
            'content.bank.usd_title' => (string) ($data['bank_usd_title'] ?? ''),
            'content.bank.usd_bank_name' => (string) ($data['bank_usd_bank_name'] ?? ''),
            'content.bank.usd_account_title' => (string) ($data['bank_usd_account_title'] ?? ''),
            'content.bank.usd_swift' => (string) ($data['bank_usd_swift'] ?? ''),
            'content.bank.usd_iban' => (string) ($data['bank_usd_iban'] ?? ''),
            'content.bank.usd_note' => (string) ($data['bank_usd_note'] ?? ''),
            'content.bank.after_pay_heading' => (string) ($data['bank_after_pay_heading'] ?? ''),
            'content.bank.after_pay_step_1' => (string) ($data['bank_after_pay_step_1'] ?? ''),
            'content.bank.after_pay_step_2' => (string) ($data['bank_after_pay_step_2'] ?? ''),
            'content.bank.after_pay_step_3' => (string) ($data['bank_after_pay_step_3'] ?? ''),
            'content.bank.cta_text' => (string) ($data['bank_cta_text'] ?? ''),
            'content.contact.title' => (string) ($data['contact_title'] ?? ''),
            'content.contact.subtitle' => (string) ($data['contact_subtitle'] ?? ''),
            'content.contact.office' => (string) ($data['contact_office'] ?? ''),
            'content.contact.phone' => (string) ($data['contact_phone'] ?? ''),
            'content.contact.email' => (string) ($data['contact_email'] ?? ''),
            'content.contact.whatsapp_hint' => (string) ($data['contact_whatsapp_hint'] ?? ''),
            'content.contact.hours_heading' => (string) ($data['contact_hours_heading'] ?? ''),
            'content.contact.hours_subtitle' => (string) ($data['contact_hours_subtitle'] ?? ''),
            'content.contact.weekday_label' => (string) ($data['contact_weekday_label'] ?? ''),
            'content.contact.weekday_hours' => (string) ($data['contact_weekday_hours'] ?? ''),
            'content.contact.sunday_label' => (string) ($data['contact_sunday_label'] ?? ''),
            'content.contact.sunday_hours' => (string) ($data['contact_sunday_hours'] ?? ''),
            'content.contact.start_online_heading' => (string) ($data['contact_start_online_heading'] ?? ''),
            'content.contact.start_online_text' => (string) ($data['contact_start_online_text'] ?? ''),
            'content.contact.quote_cta_text' => (string) ($data['contact_quote_cta_text'] ?? ''),
            'content.contact.package_cta_text' => (string) ($data['contact_package_cta_text'] ?? ''),
        ];

        $this->settings->setMany($flat, ['scope' => 'platform', 'category' => 'content']);
    }
}
