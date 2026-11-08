<?php

namespace App\Http\Controllers;

use App\Models\Crawl;
use App\Support\WorkspaceContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $workspace = app(WorkspaceContext::class)->get();

        $latestCrawls = Crawl::query()
            ->when($workspace, fn ($query) => $query->where('workspace_id', $workspace->id))
            ->latest('id')
            ->with(['project.website'])
            ->limit(8)
            ->get();

        $completedCount = Crawl::query()
            ->when($workspace, fn ($query) => $query->where('workspace_id', $workspace->id))
            ->where('status', 'completed')
            ->count();

        return view('dashboard', [
            'latestCrawls' => $latestCrawls,
            'completedCount' => $completedCount,
        ]);
    }
}
