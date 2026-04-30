@php
    $cms = app(\App\Services\Content\ContentSettingsService::class)->frontendSettings();
    $groupTicketingEnabled = (bool) data_get($frontendFeatureFlags ?? [], 'group_ticketing_enabled', true);
    $umrahPackagesEnabled = (bool) data_get($frontendFeatureFlags ?? [], 'umrah_packages_enabled', true);
@endphp
<footer class="site-footer mt-auto">
    <div class="footer-main py-5">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-4">
                    <div class="mb-3">
                        <x-frontend.brand-wordmark context="footer" />
                    </div>
                    <p class="text-footer-muted small mb-3">{{ data_get($cms, 'footer.company_blurb') }}</p>
                    <p class="small mb-1"><i class="bi bi-geo-alt text-brand-green me-2"></i>{{ data_get($cms, 'footer.address') }}</p>
                    <p class="small mb-1"><i class="bi bi-telephone text-brand-green me-2"></i>{{ data_get($cms, 'footer.phone') }}</p>
                    <p class="small mb-3"><i class="bi bi-envelope text-brand-green me-2"></i>{{ data_get($cms, 'footer.email') }}</p>
                    <div class="d-flex gap-2">
                        <a href="{{ data_get($cms, 'footer.facebook_url') }}" class="btn btn-social rounded-circle" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="{{ data_get($cms, 'footer.instagram_url') }}" class="btn btn-social rounded-circle" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="{{ data_get($cms, 'footer.youtube_url') }}" class="btn btn-social rounded-circle" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                        <a href="{{ data_get($cms, 'footer.whatsapp_url') }}" class="btn btn-social rounded-circle" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="text-brand-navy fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled small footer-links">
                        <li><a href="{{ route('frontend.home') }}">Home</a></li>
                        <li><a href="{{ route('frontend.about') }}">About Us</a></li>
                        <li><a href="{{ route('frontend.contact') }}">Contact Us</a></li>
                        <li><a href="{{ data_get($cms, 'footer.support_url', '#') }}">Support</a></li>
                        <li><a href="{{ data_get($cms, 'footer.privacy_url', '#') }}">Privacy Policy</a></li>
                        <li><a href="{{ data_get($cms, 'footer.terms_url', '#') }}">Terms &amp; Conditions</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-3">
                    <h6 class="text-brand-navy fw-bold mb-3">Customer Care</h6>
                    <ul class="list-unstyled small footer-links">
                        <li><a href="{{ route('frontend.inquiries.quote') }}">Flights / Quote</a></li>
                        <li><a href="{{ route('customer.login') }}">Find Booking</a></li>
                        @if($groupTicketingEnabled)
                            <li><a href="{{ route('frontend.groups.index') }}">Group Tickets</a></li>
                        @endif
                        @if($umrahPackagesEnabled)
                            <li><a href="{{ route('frontend.packages.index') }}">Umrah Packages</a></li>
                        @endif
                        <li><a href="{{ route('frontend.bank') }}">Bank Details</a></li>
                        @if(Route::has('frontend.blog.index'))
                            <li><a href="{{ data_get($cms, 'footer.blog_url') ?: route('frontend.blog.index') }}">Blog</a></li>
                        @endif
                    </ul>
                </div>
                <div class="col-lg-3">
                    <h6 class="text-brand-navy fw-bold mb-3">Newsletter</h6>
                    <p class="small text-footer-muted">Get fare alerts and Umrah group updates.</p>
                    <form class="d-flex flex-column gap-2" action="#" method="get" onsubmit="return false;">
                        <input type="email" class="form-control form-control-sm rounded-pill" placeholder="Your email" aria-label="Email">
                        <button type="button" class="btn btn-brand-green btn-sm rounded-pill text-white">Subscribe</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bar py-3">
        <div class="container d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 small">
            <span class="text-footer-muted mb-0">&copy; {{ date('Y') }} {{ config('brand.name', 'Hayat Travel Solutions') }}. All rights reserved.</span>
            <span class="text-footer-muted">
                <a href="{{ data_get($cms, 'footer.terms_url', '#') }}" class="text-decoration-none text-footer-muted">Terms &amp; Conditions</a>
                <span class="mx-2">|</span>
                <a href="{{ data_get($cms, 'footer.privacy_url', '#') }}" class="text-decoration-none text-footer-muted">Privacy Policy</a>
            </span>
        </div>
    </div>
</footer>
