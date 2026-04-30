@extends('layouts.frontend')

@section('title', 'Bank Details')

@section('content')
    @php($bank = (array) data_get($cmsSettings ?? [], 'bank_page', []))
    <x-frontend.page-hero
        :title="data_get($bank, 'title', 'Bank Details')"
        :subtitle="data_get($bank, 'subtitle', 'Use these details for bank transfers. Share your payment proof and booking reference with our team so we can confirm receipt.')"
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Bank Details', 'url' => null],
        ]"
    />

    <section class="section-block">
        <div class="container">
            <div class="alert alert-light border rounded-3 small mb-4" role="note">
                <i class="bi bi-info-circle text-brand-green me-2"></i>
                {{ data_get($bank, 'notice', 'Please include booking reference and traveler name with every transfer so our finance team can verify quickly.') }}
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="content-shell p-4 h-100">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="rounded-2 d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;background:rgba(15,42,68,0.08);">
                                <i class="bi bi-bank2 text-brand-navy"></i>
                            </span>
                            <h2 class="h5 text-brand-navy fw-bold mb-0">{{ data_get($bank, 'local_title', 'PKR local transfer') }}</h2>
                        </div>
                        <dl class="row small mb-0">
                            <dt class="col-sm-4 text-secondary">Bank name</dt>
                            <dd class="col-sm-8 fw-medium text-brand-navy">{{ data_get($bank, 'local_bank_name', 'Your Bank Limited') }}</dd>
                            <dt class="col-sm-4 text-secondary">Account title</dt>
                            <dd class="col-sm-8 fw-medium text-brand-navy">{{ data_get($bank, 'local_account_title', config('brand.legal_name')) }}</dd>
                            <dt class="col-sm-4 text-secondary">Account #</dt>
                            <dd class="col-sm-8 font-monospace">{{ data_get($bank, 'local_account_number', '0123-4567890-01') }}</dd>
                            <dt class="col-sm-4 text-secondary">IBAN</dt>
                            <dd class="col-sm-8 font-monospace text-break">{{ data_get($bank, 'local_iban', 'PK00XXXX0000000000000000') }}</dd>
                            <dt class="col-sm-4 text-secondary">Branch</dt>
                            <dd class="col-sm-8">{{ data_get($bank, 'local_branch', 'Lahore') }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="content-shell p-4 h-100">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="rounded-2 d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;background:rgba(15,42,68,0.08);">
                                <i class="bi bi-currency-exchange text-brand-navy"></i>
                            </span>
                            <h2 class="h5 text-brand-navy fw-bold mb-0">{{ data_get($bank, 'usd_title', 'USD wire') }}</h2>
                        </div>
                        <dl class="row small mb-0">
                            <dt class="col-sm-4 text-secondary">Bank name</dt>
                            <dd class="col-sm-8 fw-medium text-brand-navy">{{ data_get($bank, 'usd_bank_name', 'Correspondent Bank') }}</dd>
                            <dt class="col-sm-4 text-secondary">Account title</dt>
                            <dd class="col-sm-8 fw-medium text-brand-navy">{{ data_get($bank, 'usd_account_title', config('brand.legal_name')) }}</dd>
                            <dt class="col-sm-4 text-secondary">SWIFT</dt>
                            <dd class="col-sm-8 font-monospace">{{ data_get($bank, 'usd_swift', 'XXXXXXXX') }}</dd>
                            <dt class="col-sm-4 text-secondary">IBAN</dt>
                            <dd class="col-sm-8 font-monospace text-break">{{ data_get($bank, 'usd_iban', 'PK00XXXX0000000000000000') }}</dd>
                            <dt class="col-sm-4 text-secondary">Note</dt>
                            <dd class="col-sm-8 text-secondary">{{ data_get($bank, 'usd_note', 'Include remitter name and booking reference.') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="content-shell p-4 mt-4">
                <h2 class="h6 text-brand-navy fw-bold mb-3">{{ data_get($bank, 'after_pay_heading', 'After you pay') }}</h2>
                <ol class="small text-secondary mb-0 ps-3">
                    <li class="mb-2">{{ data_get($bank, 'after_pay_step_1', 'Send a clear screenshot or PDF of the transfer receipt.') }}</li>
                    <li class="mb-2">{{ data_get($bank, 'after_pay_step_2', 'Mention passenger names, route, and travel dates.') }}</li>
                    <li>{{ data_get($bank, 'after_pay_step_3', 'Wait for finance to confirm; we update your booking in the system.') }}</li>
                </ol>
                <a href="{{ route('frontend.contact') }}" class="btn btn-brand-green text-white rounded-pill mt-4">{{ data_get($bank, 'cta_text', 'Contact us') }}</a>
            </div>
        </div>
    </section>
@endsection
