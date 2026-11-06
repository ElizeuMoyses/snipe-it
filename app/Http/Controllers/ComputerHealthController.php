<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\ComputerHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class ComputerHealthController extends Controller
{
    public function index(): View
    {
        $this->authorize('index', Asset::class);

        return view('computer-health.index');
    }

    public function data(ComputerHealthService $service): JsonResponse
    {
        $this->authorize('index', Asset::class);

        return response()->json($service->snapshot())->header('Cache-Control', 'private, no-store');
    }

    public function history(int $assetId, ComputerHealthService $service): JsonResponse
    {
        $this->authorize('index', Asset::class);

        return response()->json($service->history($assetId))->header('Cache-Control', 'private, no-store');
    }
}
