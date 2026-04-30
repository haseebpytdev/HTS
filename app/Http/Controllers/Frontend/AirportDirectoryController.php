<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Travel\AirportDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirportDirectoryController extends Controller
{
    public function __construct(
        private readonly AirportDirectoryService $airportDirectoryService
    ) {
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = (string) ($validated['q'] ?? '');
        $limit = (int) ($validated['limit'] ?? 15);

        return response()->json([
            'data' => $this->airportDirectoryService->search($query, $limit),
        ]);
    }

    public function globalIndex(): JsonResponse
    {
        return response()->json(
            $this->airportDirectoryService->globalAutocompleteIndexPayload()
        );
    }
}
