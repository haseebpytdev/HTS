<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogPostRequest;
use App\Http\Requests\Admin\UpdateBlogPostRequest;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::query()->latest('id')->paginate(20);

        return view('admin/blog-posts/index', compact('posts'));
    }

    public function create(): View
    {
        return view('admin/blog-posts/create');
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['author_user_id'] = auth()->id();
        $data['published_at'] = $data['status'] === 'published' ? now() : null;
        BlogPost::query()->create($data);

        return redirect()->route('admin.blog-posts.index')->with('success', 'Blog post created.');
    }

    public function edit(BlogPost $blog_post): View
    {
        return view('admin/blog-posts/edit', ['post' => $blog_post]);
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $blog_post): RedirectResponse
    {
        $data = $request->validated();
        if (($data['status'] ?? null) === 'published' && $blog_post->published_at === null) {
            $data['published_at'] = now();
        }
        $blog_post->update($data);

        return redirect()->route('admin.blog-posts.index')->with('success', 'Blog post updated.');
    }
}
