<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SiemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    protected SiemService $siemService;

    public function __construct(SiemService $siemService)
    {
        $this->siemService = $siemService;
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Show security dashboard
     */
    public function dashboard(): View
    {
        return view('admin.security');
    }

    /**
     * Get security statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);
        $stats = $this->siemService->getDashboardStats($days);

        return response()->json($stats);
    }

    /**
     * Get security events
     */
    public function events(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 100);
        $events = $this->siemService->getRecentEvents($limit);

        return response()->json($events);
    }

    /**
     * Get login patterns for a specific user
     */
    public function userPatterns(Request $request, string $userId): JsonResponse
    {
        $days = $request->get('days', 30);
        $patterns = $this->siemService->getLoginPatterns($userId, $days);

        return response()->json($patterns);
    }

    /**
     * Check if IP is blocked
     */
    public function checkIp(Request $request): JsonResponse
    {
        $ip = $request->get('ip', $request->ip());
        $blocked = $this->siemService->isIpBlocked($ip);

        return response()->json(['blocked' => $blocked, 'ip' => $ip]);
    }
}