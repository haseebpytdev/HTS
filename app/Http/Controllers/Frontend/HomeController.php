<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Repositories\ContentBlockRepository;
use App\Services\Content\ContentSettingsService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly ContentBlockRepository $contentBlockRepository,
        private readonly ContentSettingsService $contentSettings,
    ) {
    }

    public function index(): View
    {
        $keys = ['home.hero', 'home.why_us', 'home.group_tickets', 'home.deals'];
        $blocks = $this->contentBlockRepository->keyedByBlockKey($keys);
        $defaults = self::defaultHomePayloads();

        $hero = $blocks->get('home.hero')?->payload ?? $defaults['home.hero'];
        $whyUs = $blocks->get('home.why_us')?->payload ?? $defaults['home.why_us'];
        $groupTickets = $blocks->get('home.group_tickets')?->payload ?? $defaults['home.group_tickets'];
        $deals = $blocks->get('home.deals')?->payload ?? $defaults['home.deals'];
        $cmsSettings = $this->contentSettings->frontendSettings();

        return view('frontend.home', [
            'hero' => $hero,
            'whyUs' => $whyUs,
            'groupTickets' => $groupTickets,
            'deals' => $deals,
            'cmsSettings' => $cmsSettings,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function defaultHomePayloads(): array
    {
        return [
            'home.hero' => [
                'headline' => '',
                'subheadline' => '',
                'background_image' => '',
            ],
            'home.why_us' => [
                'section_title' => 'Why Book With Us?',
                'section_subtitle' => 'Discover the unmatched benefits and exclusive advantages of booking directly with us.',
                'items' => [
                    ['icon' => 'bi-headset', 'title' => '24/7 Customer Support', 'description' => 'Round-the-clock assistance for bookings, changes, and urgent travel needs.'],
                    ['icon' => 'bi-geo-alt-fill', 'title' => 'Central Location Lahore', 'description' => 'Visit us in Lahore for in-person consultations and document support.'],
                    ['icon' => 'bi-tag-fill', 'title' => 'Competitive Fares', 'description' => 'Transparent pricing on flights, groups, and Umrah packages — no hidden fees.'],
                    ['icon' => 'bi-award-fill', 'title' => '17+ Years of Experience', 'description' => 'Trusted by families and agencies for reliable ticketing and pilgrimage travel.'],
                ],
            ],
            'home.group_tickets' => [
                'section_title' => 'Explore Group Tickets',
                'items' => [
                    ['title' => 'Qatar Groups', 'tag' => 'Explore', 'link' => ''],
                    ['title' => 'Bahrain Groups', 'tag' => 'Explore', 'link' => ''],
                    ['title' => 'All Groups', 'tag' => 'Explore', 'link' => ''],
                    ['title' => 'Umrah Groups', 'tag' => 'Explore', 'link' => ''],
                ],
            ],
            'home.deals' => [
                'section_title' => 'Our Deals & Offers',
                'items' => [
                    ['title' => 'LHE — DXB Economy', 'subtitle' => 'Popular Gulf route', 'price' => 'PKR 89,500', 'link' => ''],
                    ['title' => 'LHE — LHR Return', 'subtitle' => 'London special', 'price' => 'PKR 150,890', 'link' => ''],
                    ['title' => 'Umrah Express', 'subtitle' => '7 nights Madinah + Makkah', 'price' => 'From PKR 185,000', 'link' => ''],
                    ['title' => 'Student Fare', 'subtitle' => 'Selected routes', 'price' => 'Ask for quote', 'link' => ''],
                ],
            ],
        ];
    }
}
