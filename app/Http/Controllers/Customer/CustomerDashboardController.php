<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CustomerDashboardController extends Controller
{
    public function show(): View
    {
        return view('customer.dashboard', [
            'customer' => auth('customer')->user(),
        ]);
    }
}
