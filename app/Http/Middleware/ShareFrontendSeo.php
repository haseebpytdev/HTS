<?php

namespace App\Http\Middleware;

use App\Repositories\SeoPageRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShareFrontendSeo
{
    public function __construct(
        private readonly SeoPageRepository $seoPageRepository
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = $route?->getName();
        $map = config('seo.route_page_keys', []);

        view()->share('pageSeo', null);

        if ($name !== null && isset($map[$name])) {
            $page = $this->seoPageRepository->findByPageKey($map[$name]);
            view()->share('sharedSeo', $page);
        } else {
            view()->share('sharedSeo', null);
        }

        return $next($request);
    }
}
