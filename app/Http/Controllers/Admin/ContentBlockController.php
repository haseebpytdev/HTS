<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StorePublicDiskImageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateContentBlockRequest;
use App\Models\ContentBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContentBlockController extends Controller
{
    public function __construct(
        private readonly StorePublicDiskImageAction $storePublicDiskImageAction
    ) {
    }

    public function index(): View
    {
        $this->authorize('access-admin-area');

        $blocks = ContentBlock::query()->orderBy('block_key')->get();

        return view('admin.content-blocks.index', compact('blocks'));
    }

    public function edit(ContentBlock $content_block): View
    {
        $this->authorize('access-admin-area');

        return view('admin.content-blocks.edit', ['block' => $content_block]);
    }

    public function update(UpdateContentBlockRequest $request, ContentBlock $content_block): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $newBackground = null;
        if ($request->hasFile('background_upload')) {
            $newBackground = $this->storePublicDiskImageAction->execute(
                $request->file('background_upload'),
                'cms/home'
            );
        }

        $payload = $request->payloadForBlock($content_block, $newBackground);

        $content_block->update([
            'payload' => $payload,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.content-blocks.index')
            ->with('success', 'Content block saved.');
    }
}
