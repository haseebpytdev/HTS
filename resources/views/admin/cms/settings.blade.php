@extends('layouts.admin')

@section('title', 'CMS Control Settings')

@section('admin-content')
    <h1 class="h4 mb-3">CMS Control Settings</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Content Modules</strong></div>
        <div class="card-body d-flex flex-wrap gap-2">
            <a href="{{ route('admin.content-blocks.index') }}" class="btn btn-sm btn-outline-primary">Homepage Sections</a>
            <a href="{{ route('admin.landing-pages.index') }}" class="btn btn-sm btn-outline-primary">Landing Pages</a>
            <a href="{{ route('admin.seo-pages.index') }}" class="btn btn-sm btn-outline-primary">SEO Metadata</a>
            <a href="{{ route('admin.blog-posts.index') }}" class="btn btn-sm btn-outline-primary">Blogs</a>
            <a href="{{ route('admin.promo-codes.index') }}" class="btn btn-sm btn-outline-primary">Promo Content</a>
            <a href="{{ route('admin.packages.index') }}" class="btn btn-sm btn-outline-primary">Package Visibility</a>
            <a href="{{ route('admin.groups.index') }}" class="btn btn-sm btn-outline-primary">Group Visibility</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.cms.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Homepage Sections and Featured Visibility</strong></div>
            <div class="card-body">
                @foreach(['show_hero' => 'Hero section','show_why_us' => 'Why Us','show_group_tickets' => 'Group Tickets','show_deals' => 'Deals','show_featured_packages' => 'Featured Packages','show_featured_groups' => 'Featured Groups'] as $field => $label)
                    <input type="hidden" name="{{ $field }}" value="0">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" id="{{ $field }}" @checked(data_get($settings, str_contains($field, 'featured') ? 'featured.'.str_replace(['show_featured_'], ['show_'], $field) : 'homepage.'.$field))>
                        <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                    </div>
                @endforeach
                <label class="form-label mt-2">Promo banner text</label>
                <input type="text" class="form-control" name="promo_banner_text" value="{{ data_get($settings, 'homepage.promo_banner_text') }}">
                <label class="form-label mt-2">Promo banner link (optional)</label>
                <input type="text" class="form-control" name="promo_banner_link" value="{{ data_get($settings, 'homepage.promo_banner_link') }}" placeholder="https://... or internal URL">
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Footer and Social Content</strong></div>
            <div class="card-body row g-2">
                <div class="col-md-6"><label class="form-label">Company blurb</label><textarea class="form-control" rows="3" name="footer_company_blurb">{{ data_get($settings, 'footer.company_blurb') }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="footer_address" value="{{ data_get($settings, 'footer.address') }}"></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="footer_phone" value="{{ data_get($settings, 'footer.phone') }}"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" name="footer_email" value="{{ data_get($settings, 'footer.email') }}"></div>
                <div class="col-md-4"><label class="form-label">Facebook URL</label><input class="form-control" name="footer_facebook_url" value="{{ data_get($settings, 'footer.facebook_url') }}"></div>
                <div class="col-md-4"><label class="form-label">Instagram URL</label><input class="form-control" name="footer_instagram_url" value="{{ data_get($settings, 'footer.instagram_url') }}"></div>
                <div class="col-md-4"><label class="form-label">YouTube URL</label><input class="form-control" name="footer_youtube_url" value="{{ data_get($settings, 'footer.youtube_url') }}"></div>
                <div class="col-md-4"><label class="form-label">WhatsApp URL</label><input class="form-control" name="footer_whatsapp_url" value="{{ data_get($settings, 'footer.whatsapp_url') }}"></div>
                <div class="col-md-4"><label class="form-label">Support URL</label><input class="form-control" name="footer_support_url" value="{{ data_get($settings, 'footer.support_url') }}"></div>
                <div class="col-md-4"><label class="form-label">Privacy URL</label><input class="form-control" name="footer_privacy_url" value="{{ data_get($settings, 'footer.privacy_url') }}"></div>
                <div class="col-md-4"><label class="form-label">Terms URL</label><input class="form-control" name="footer_terms_url" value="{{ data_get($settings, 'footer.terms_url') }}"></div>
                <div class="col-md-4"><label class="form-label">Footer Blog URL</label><input class="form-control" name="footer_blog_url" value="{{ data_get($settings, 'footer.blog_url') }}"></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Top Navigation Static Pages</strong></div>
            <div class="card-body">
                @foreach(['show_blog_link' => 'Show Blog','show_bank_link' => 'Show Bank Details','show_about_link' => 'Show About Us','show_contact_link' => 'Show Contact Us'] as $field => $label)
                    <input type="hidden" name="{{ $field }}" value="0">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" id="{{ $field }}" @checked(data_get($settings, 'top_nav.'.$field))
                        >
                        <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>About Page Static Content</strong></div>
            <div class="card-body row g-2">
                <div class="col-md-4"><label class="form-label">Page title</label><input class="form-control" name="about_title" value="{{ data_get($settings, 'about_page.title') }}"></div>
                <div class="col-md-8"><label class="form-label">Hero subtitle</label><input class="form-control" name="about_subtitle" value="{{ data_get($settings, 'about_page.subtitle') }}"></div>
                <div class="col-md-4"><label class="form-label">Who we are heading</label><input class="form-control" name="about_who_we_are_heading" value="{{ data_get($settings, 'about_page.who_we_are_heading') }}"></div>
                <div class="col-md-8"><label class="form-label">Why heading</label><input class="form-control" name="about_why_heading" value="{{ data_get($settings, 'about_page.why_heading') }}"></div>
                <div class="col-12"><label class="form-label">Who we are body</label><textarea class="form-control" rows="3" name="about_who_we_are_body">{{ data_get($settings, 'about_page.who_we_are_body') }}</textarea></div>
                <div class="col-12"><label class="form-label">Who we are body (second paragraph)</label><textarea class="form-control" rows="3" name="about_who_we_are_body_2">{{ data_get($settings, 'about_page.who_we_are_body_2') }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Highlight 1</label><input class="form-control" name="about_highlight_1" value="{{ data_get($settings, 'about_page.highlight_1') }}"></div>
                <div class="col-md-6"><label class="form-label">Highlight 2</label><input class="form-control" name="about_highlight_2" value="{{ data_get($settings, 'about_page.highlight_2') }}"></div>
                <div class="col-md-6"><label class="form-label">Highlight 3</label><input class="form-control" name="about_highlight_3" value="{{ data_get($settings, 'about_page.highlight_3') }}"></div>
                <div class="col-md-6"><label class="form-label">Highlight 4</label><input class="form-control" name="about_highlight_4" value="{{ data_get($settings, 'about_page.highlight_4') }}"></div>
                <div class="col-md-4"><label class="form-label">CTA text</label><input class="form-control" name="about_cta_text" value="{{ data_get($settings, 'about_page.cta_text') }}"></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Bank Details Page Content</strong></div>
            <div class="card-body row g-2">
                <div class="col-md-4"><label class="form-label">Page title</label><input class="form-control" name="bank_title" value="{{ data_get($settings, 'bank_page.title') }}"></div>
                <div class="col-md-8"><label class="form-label">Hero subtitle</label><input class="form-control" name="bank_subtitle" value="{{ data_get($settings, 'bank_page.subtitle') }}"></div>
                <div class="col-12"><label class="form-label">Notice text</label><input class="form-control" name="bank_notice" value="{{ data_get($settings, 'bank_page.notice') }}"></div>
                <div class="col-md-3"><label class="form-label">PKR card title</label><input class="form-control" name="bank_local_title" value="{{ data_get($settings, 'bank_page.local_title') }}"></div>
                <div class="col-md-3"><label class="form-label">Local bank name</label><input class="form-control" name="bank_local_bank_name" value="{{ data_get($settings, 'bank_page.local_bank_name') }}"></div>
                <div class="col-md-3"><label class="form-label">Local account title</label><input class="form-control" name="bank_local_account_title" value="{{ data_get($settings, 'bank_page.local_account_title') }}"></div>
                <div class="col-md-3"><label class="form-label">Local account #</label><input class="form-control" name="bank_local_account_number" value="{{ data_get($settings, 'bank_page.local_account_number') }}"></div>
                <div class="col-md-3"><label class="form-label">Local IBAN</label><input class="form-control" name="bank_local_iban" value="{{ data_get($settings, 'bank_page.local_iban') }}"></div>
                <div class="col-md-3"><label class="form-label">Local branch</label><input class="form-control" name="bank_local_branch" value="{{ data_get($settings, 'bank_page.local_branch') }}"></div>
                <div class="col-md-3"><label class="form-label">USD card title</label><input class="form-control" name="bank_usd_title" value="{{ data_get($settings, 'bank_page.usd_title') }}"></div>
                <div class="col-md-3"><label class="form-label">USD bank name</label><input class="form-control" name="bank_usd_bank_name" value="{{ data_get($settings, 'bank_page.usd_bank_name') }}"></div>
                <div class="col-md-3"><label class="form-label">USD account title</label><input class="form-control" name="bank_usd_account_title" value="{{ data_get($settings, 'bank_page.usd_account_title') }}"></div>
                <div class="col-md-3"><label class="form-label">SWIFT</label><input class="form-control" name="bank_usd_swift" value="{{ data_get($settings, 'bank_page.usd_swift') }}"></div>
                <div class="col-md-6"><label class="form-label">USD IBAN</label><input class="form-control" name="bank_usd_iban" value="{{ data_get($settings, 'bank_page.usd_iban') }}"></div>
                <div class="col-md-6"><label class="form-label">USD note</label><input class="form-control" name="bank_usd_note" value="{{ data_get($settings, 'bank_page.usd_note') }}"></div>
                <div class="col-md-4"><label class="form-label">After pay heading</label><input class="form-control" name="bank_after_pay_heading" value="{{ data_get($settings, 'bank_page.after_pay_heading') }}"></div>
                <div class="col-md-4"><label class="form-label">After pay step 1</label><input class="form-control" name="bank_after_pay_step_1" value="{{ data_get($settings, 'bank_page.after_pay_step_1') }}"></div>
                <div class="col-md-4"><label class="form-label">After pay step 2</label><input class="form-control" name="bank_after_pay_step_2" value="{{ data_get($settings, 'bank_page.after_pay_step_2') }}"></div>
                <div class="col-md-8"><label class="form-label">After pay step 3</label><input class="form-control" name="bank_after_pay_step_3" value="{{ data_get($settings, 'bank_page.after_pay_step_3') }}"></div>
                <div class="col-md-4"><label class="form-label">CTA text</label><input class="form-control" name="bank_cta_text" value="{{ data_get($settings, 'bank_page.cta_text') }}"></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Contact Page Content</strong></div>
            <div class="card-body row g-2">
                <div class="col-md-4"><label class="form-label">Page title</label><input class="form-control" name="contact_title" value="{{ data_get($settings, 'contact_page.title') }}"></div>
                <div class="col-md-8"><label class="form-label">Hero subtitle</label><input class="form-control" name="contact_subtitle" value="{{ data_get($settings, 'contact_page.subtitle') }}"></div>
                <div class="col-md-4"><label class="form-label">Office</label><input class="form-control" name="contact_office" value="{{ data_get($settings, 'contact_page.office') }}"></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="contact_phone" value="{{ data_get($settings, 'contact_page.phone') }}"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" name="contact_email" value="{{ data_get($settings, 'contact_page.email') }}"></div>
                <div class="col-12"><label class="form-label">WhatsApp hint</label><input class="form-control" name="contact_whatsapp_hint" value="{{ data_get($settings, 'contact_page.whatsapp_hint') }}"></div>
                <div class="col-md-4"><label class="form-label">Hours heading</label><input class="form-control" name="contact_hours_heading" value="{{ data_get($settings, 'contact_page.hours_heading') }}"></div>
                <div class="col-md-8"><label class="form-label">Hours subtitle</label><input class="form-control" name="contact_hours_subtitle" value="{{ data_get($settings, 'contact_page.hours_subtitle') }}"></div>
                <div class="col-md-3"><label class="form-label">Weekday label</label><input class="form-control" name="contact_weekday_label" value="{{ data_get($settings, 'contact_page.weekday_label') }}"></div>
                <div class="col-md-3"><label class="form-label">Weekday hours</label><input class="form-control" name="contact_weekday_hours" value="{{ data_get($settings, 'contact_page.weekday_hours') }}"></div>
                <div class="col-md-3"><label class="form-label">Sunday label</label><input class="form-control" name="contact_sunday_label" value="{{ data_get($settings, 'contact_page.sunday_label') }}"></div>
                <div class="col-md-3"><label class="form-label">Sunday hours</label><input class="form-control" name="contact_sunday_hours" value="{{ data_get($settings, 'contact_page.sunday_hours') }}"></div>
                <div class="col-md-4"><label class="form-label">Start online heading</label><input class="form-control" name="contact_start_online_heading" value="{{ data_get($settings, 'contact_page.start_online_heading') }}"></div>
                <div class="col-md-8"><label class="form-label">Start online text</label><input class="form-control" name="contact_start_online_text" value="{{ data_get($settings, 'contact_page.start_online_text') }}"></div>
                <div class="col-md-4"><label class="form-label">Quote CTA text</label><input class="form-control" name="contact_quote_cta_text" value="{{ data_get($settings, 'contact_page.quote_cta_text') }}"></div>
                <div class="col-md-4"><label class="form-label">Package CTA text</label><input class="form-control" name="contact_package_cta_text" value="{{ data_get($settings, 'contact_page.package_cta_text') }}"></div>
            </div>
        </div>

        <button class="btn btn-primary">Save CMS Settings</button>
    </form>
@endsection
