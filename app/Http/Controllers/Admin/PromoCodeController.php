<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromoCodeRequest;
use App\Http\Requests\Admin\UpdatePromoCodeRequest;
use App\Models\PromoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PromoCodeController extends Controller
{
    public function index(): View
    {
        $codes = PromoCode::query()->latest('id')->paginate(20);

        return view('admin/promo-codes/index', compact('codes'));
    }

    public function create(): View
    {
        return view('admin/promo-codes/create');
    }

    public function store(StorePromoCodeRequest $request): RedirectResponse
    {
        PromoCode::query()->create($request->validated());

        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo code created.');
    }

    public function edit(PromoCode $promo_code): View
    {
        return view('admin/promo-codes/edit', ['promoCode' => $promo_code]);
    }

    public function update(UpdatePromoCodeRequest $request, PromoCode $promo_code): RedirectResponse
    {
        $promo_code->update($request->validated());

        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo code updated.');
    }
}
