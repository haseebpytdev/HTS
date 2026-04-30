<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StorePublicDiskImageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGroupImageRequest;
use App\Http\Requests\Admin\UpdateGroupImageRequest;
use App\Models\GroupImage;
use App\Models\TravelGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GroupGalleryController extends Controller
{
    public function __construct(
        private readonly StorePublicDiskImageAction $storePublicDiskImageAction
    ) {
    }

    public function index(TravelGroup $group): View
    {
        $this->authorize('access-admin-area');
        $group->load(['images' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        return view('admin.groups.gallery.index', compact('group'));
    }

    public function store(StoreGroupImageRequest $request, TravelGroup $group): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $data = $request->validated();
        $path = $this->storePublicDiskImageAction->execute($data['image'], 'groups/gallery/'.$group->id);

        DB::transaction(function () use ($group, $path, $data, $request): void {
            if ($request->boolean('is_cover')) {
                GroupImage::query()->where('group_id', $group->id)->update(['is_cover' => false]);
            }

            $maxSort = (int) GroupImage::query()->where('group_id', $group->id)->max('sort_order');

            GroupImage::query()->create([
                'group_id' => $group->id,
                'image_path' => $path,
                'alt_text' => $data['alt_text'] ?? null,
                'sort_order' => $data['sort_order'] ?? ($maxSort + 1),
                'is_cover' => $request->boolean('is_cover'),
            ]);
        });

        return redirect()->route('admin.groups.gallery.index', $group)
            ->with('success', 'Image uploaded.');
    }

    public function update(UpdateGroupImageRequest $request, TravelGroup $group, GroupImage $groupImage): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->guardImageBelongsToGroup($group, $groupImage);

        $payload = $request->validated();

        DB::transaction(function () use ($groupImage, $payload, $request, $group): void {
            if ($request->boolean('is_cover')) {
                GroupImage::query()->where('group_id', $group->id)->whereKeyNot($groupImage->id)->update(['is_cover' => false]);
            }

            $groupImage->update($payload);
        });

        return redirect()->route('admin.groups.gallery.index', $group)
            ->with('success', 'Image updated.');
    }

    public function destroy(TravelGroup $group, GroupImage $groupImage): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->guardImageBelongsToGroup($group, $groupImage);
        $groupImage->delete();

        return redirect()->route('admin.groups.gallery.index', $group)
            ->with('success', 'Image removed.');
    }

    private function guardImageBelongsToGroup(TravelGroup $group, GroupImage $image): void
    {
        if ($image->group_id !== $group->id) {
            abort(404);
        }
    }
}
