<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StorePublicDiskImageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageImageRequest;
use App\Http\Requests\Admin\UpdatePackageImageRequest;
use App\Models\PackageImage;
use App\Models\TravelPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PackageGalleryController extends Controller
{
    public function __construct(
        private readonly StorePublicDiskImageAction $storePublicDiskImageAction
    ) {
    }

    public function index(TravelPackage $package): View
    {
        $this->authorize('access-admin-area');
        $package->load(['images' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        return view('admin.packages.gallery.index', compact('package'));
    }

    public function store(StorePackageImageRequest $request, TravelPackage $package): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $data = $request->validated();
        $path = $this->storePublicDiskImageAction->execute($data['image'], 'packages/gallery/'.$package->id);

        DB::transaction(function () use ($package, $path, $data, $request): void {
            if ($request->boolean('is_cover')) {
                PackageImage::query()->where('package_id', $package->id)->update(['is_cover' => false]);
            }

            $maxSort = (int) PackageImage::query()->where('package_id', $package->id)->max('sort_order');

            PackageImage::query()->create([
                'package_id' => $package->id,
                'image_path' => $path,
                'alt_text' => $data['alt_text'] ?? null,
                'sort_order' => $data['sort_order'] ?? ($maxSort + 1),
                'is_cover' => $request->boolean('is_cover'),
            ]);
        });

        return redirect()->route('admin.packages.gallery.index', $package)
            ->with('success', 'Image uploaded.');
    }

    public function update(UpdatePackageImageRequest $request, TravelPackage $package, PackageImage $packageImage): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->guardImageBelongsToPackage($package, $packageImage);

        $payload = $request->validated();

        DB::transaction(function () use ($packageImage, $payload, $request, $package): void {
            if ($request->boolean('is_cover')) {
                PackageImage::query()->where('package_id', $package->id)->whereKeyNot($packageImage->id)->update(['is_cover' => false]);
            }

            $packageImage->update($payload);
        });

        return redirect()->route('admin.packages.gallery.index', $package)
            ->with('success', 'Image updated.');
    }

    public function destroy(TravelPackage $package, PackageImage $packageImage): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->guardImageBelongsToPackage($package, $packageImage);
        $packageImage->delete();

        return redirect()->route('admin.packages.gallery.index', $package)
            ->with('success', 'Image removed.');
    }

    private function guardImageBelongsToPackage(TravelPackage $package, PackageImage $image): void
    {
        if ($image->package_id !== $package->id) {
            abort(404);
        }
    }
}
