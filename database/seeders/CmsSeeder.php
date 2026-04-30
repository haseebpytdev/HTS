<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use App\Models\SeoPage;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $seo = [
            [
                'page_key' => 'home',
                'title' => 'Home',
                'meta_title' => 'Hayat Travel Solutions — Flights & Umrah Packages',
                'meta_description' => 'Book flights, group tickets, and Umrah packages with a trusted Lahore-based travel partner.',
                'meta_keywords' => 'Umrah, flights, Lahore, travel, packages',
                'og_image' => null,
                'canonical_url' => null,
                'schema_markup' => null,
                'is_indexable' => true,
            ],
            [
                'page_key' => 'about',
                'title' => 'About',
                'meta_title' => 'About Us | Hayat Travel Solutions',
                'meta_description' => 'Learn about Hayat Travel Solutions travel services.',
                'meta_keywords' => 'about, travel, Hayat Travel Solutions',
                'og_image' => null,
                'canonical_url' => null,
                'schema_markup' => null,
                'is_indexable' => true,
            ],
            [
                'page_key' => 'packages_index',
                'title' => 'Packages',
                'meta_title' => 'Travel Packages | Hayat Travel Solutions',
                'meta_description' => 'Browse Umrah and holiday packages.',
                'meta_keywords' => 'packages, Umrah, travel',
                'og_image' => null,
                'canonical_url' => null,
                'schema_markup' => null,
                'is_indexable' => true,
            ],
            [
                'page_key' => 'groups_index',
                'title' => 'Groups',
                'meta_title' => 'Travel Groups | Hayat Travel Solutions',
                'meta_description' => 'Explore upcoming group departures.',
                'meta_keywords' => 'groups, departures, travel',
                'og_image' => null,
                'canonical_url' => null,
                'schema_markup' => null,
                'is_indexable' => true,
            ],
            [
                'page_key' => 'quote_inquiry',
                'title' => 'Quote inquiry',
                'meta_title' => 'Request a Quote | Hayat Travel Solutions',
                'meta_description' => 'Submit your travel requirements for a tailored quotation.',
                'meta_keywords' => 'quote, inquiry, travel',
                'og_image' => null,
                'canonical_url' => null,
                'schema_markup' => null,
                'is_indexable' => true,
            ],
            [
                'page_key' => 'package_detail',
                'title' => 'Package',
                'meta_title' => 'Package | Hayat Travel Solutions',
                'meta_description' => 'Package details and departures.',
                'meta_keywords' => 'package, Umrah',
                'og_image' => null,
                'canonical_url' => null,
                'schema_markup' => null,
                'is_indexable' => true,
            ],
            [
                'page_key' => 'group_detail',
                'title' => 'Group',
                'meta_title' => 'Group | Hayat Travel Solutions',
                'meta_description' => 'Group departure details.',
                'meta_keywords' => 'group, travel',
                'og_image' => null,
                'canonical_url' => null,
                'schema_markup' => null,
                'is_indexable' => true,
            ],
        ];

        foreach ($seo as $row) {
            SeoPage::query()->updateOrCreate(
                ['page_key' => $row['page_key']],
                $row
            );
        }

        $blocks = [
            [
                'block_key' => 'home.hero',
                'label' => 'Home — Hero / banner',
                'payload' => [
                    'headline' => '',
                    'subheadline' => '',
                    'background_image' => '',
                ],
                'is_active' => true,
            ],
            [
                'block_key' => 'home.why_us',
                'label' => 'Home — Why book with us',
                'payload' => [
                    'section_title' => 'Why Book With Us?',
                    'section_subtitle' => 'Discover the unmatched benefits and exclusive advantages of booking directly with us.',
                    'items' => [
                        ['title' => '24/7 Customer Support', 'description' => 'Get round-the-clock assistance for all your critical and travel needs.'],
                        ['title' => 'Central Location Lahore', 'description' => 'Conveniently located in Lahore for easy access to travel and ticketing solutions.'],
                        ['title' => 'Cheap Ticket Fares', 'description' => 'Find exclusive offers on air tickets, hotels, and quotation packages.'],
                        ['title' => '17+ Years of Experience', 'description' => 'A trusted partner for reliable booking and overseas travel services.'],
                    ],
                ],
                'is_active' => true,
            ],
            [
                'block_key' => 'home.group_tickets',
                'label' => 'Home — Group tickets row',
                'payload' => [
                    'section_title' => 'Explore Group Tickets',
                    'items' => [
                        ['title' => 'Qatar Groups', 'tag' => 'Explore', 'link' => ''],
                        ['title' => 'Bahrain Groups', 'tag' => 'Explore', 'link' => ''],
                        ['title' => 'All Groups', 'tag' => 'Explore', 'link' => ''],
                        ['title' => 'Umrah Groups', 'tag' => 'Explore', 'link' => ''],
                    ],
                ],
                'is_active' => true,
            ],
            [
                'block_key' => 'home.deals',
                'label' => 'Home — Deals & offers',
                'payload' => [
                    'section_title' => 'Our Deals & Offers',
                    'items' => [
                        ['title' => 'Testing Title 5', 'subtitle' => 'Testing Description', 'link' => ''],
                        ['title' => 'Testing Title 6', 'subtitle' => 'Testing Description 1', 'link' => ''],
                        ['title' => 'Return Ticket Offer', 'subtitle' => 'Cheap Fare', 'link' => ''],
                        ['title' => 'Testing Title 2', 'subtitle' => 'Testing Description 2', 'link' => ''],
                    ],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($blocks as $row) {
            ContentBlock::query()->updateOrCreate(
                ['block_key' => $row['block_key']],
                $row
            );
        }
    }
}
