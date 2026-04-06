<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('frontend.home', [
            'groupCards' => [
                ['title' => 'Qatar Groups', 'tag' => 'Explore'],
                ['title' => 'Bahrain Groups', 'tag' => 'Explore'],
                ['title' => 'All Groups', 'tag' => 'Explore'],
                ['title' => 'Umrah Groups', 'tag' => 'Explore'],
            ],
            'packageCards' => [
                ['title' => 'Testing Title 5', 'subtitle' => 'Testing Description'],
                ['title' => 'Testing Title 6', 'subtitle' => 'Testing Description 1'],
                ['title' => 'Return Ticket Offer', 'subtitle' => 'Cheap Fare'],
                ['title' => 'Testing Title 2', 'subtitle' => 'Testing Description 2'],
            ],
            'whyUsCards' => [
                ['title' => '24/7 Customer Support', 'description' => 'Get round-the-clock assistance for all your critical and travel needs.'],
                ['title' => 'Central Location Lahore', 'description' => 'Conveniently located in Lahore for easy access to travel and ticketing solutions.'],
                ['title' => 'Cheap Ticket Fares', 'description' => 'Find exclusive offers on air tickets, hotels, and quotation packages.'],
                ['title' => '17+ Years of Experience', 'description' => 'A trusted partner for reliable booking and overseas travel services.'],
            ],
        ]);
    }
}
