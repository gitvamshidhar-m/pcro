<?php

namespace App\Http\Controllers;

use App\Enums\CrawlStatus;
use App\Http\Requests\StoreCrawlRequest;
use App\Jobs\CrawlWebsiteJob;
use App\Models\Crawl;
use App\Models\Project;
use App\Models\Website;
use App\Services\Url\UnsafeUrlException;
use App\Services\Url\WebsiteUrlValidator;
use App\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrawlController extends Controller
{
    public function create(WorkspaceContext $workspaces): View
    {
        $workspace = $workspaces->require();

        return view('analyses.create', [
            'workspace' => $workspace,
            'recentCrawls' => Crawl::query()
                ->where('workspace_id', $workspace->id)
                ->latest('id')
                ->with('project.website')
                ->limit(5)
                ->get(),
            'maxPages' => (int) config('cro.crawl.max_pages'),
            'maxDepth' => (int) config('cro.crawl.max_depth'),
        ]);
    }

    public function store(
        StoreCrawlRequest $request,
        WebsiteUrlValidator $validator,
        WorkspaceContext $workspaces,
    ): RedirectResponse {
        $workspace = $workspaces->require();
        $role = $workspace->roleFor($request->user());

        if ($role === null || ! $role->canAnalyze()) {
            abort(403, 'Your workspace role cannot start analyses.');
        }

        try {
            $validated = $validator->validate((string) $request->validated('url'));
        } catch (UnsafeUrlException $exception) {
            throw ValidationException::withMessages([
                'url' => $exception->publicMessage,
            ]);
        }

        $crawl = DB::transaction(function () use ($request, $workspace, $validated): Crawl {
            $website = Website::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'domain' => $validated->domain,
                ],
                [
                    'url' => $validated->url,
                    'label' => $validated->domain,
                    'metadata' => [],
                ],
            );

            $website->forceFill(['url' => $validated->url])->save();

            $project = Project::firstOrCreate(
                [
                    'website_id' => $website->id,
                    'target_url' => $validated->url,
                ],
                [
                    'workspace_id' => $workspace->id,
                    'name' => $website->label ?: $validated->domain,
                    'status' => 'active',
                    'settings' => [],
                ],
            );

            $pages = (int) ($request->validated('pages') ?? config('cro.crawl.max_pages'));
            $depth = (int) ($request->validated('depth') ?? config('cro.crawl.max_depth'));

            return Crawl::create([
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'created_by' => $request->user()->id,
                'status' => CrawlStatus::Queued,
                'stage' => 'queued',
                'progress' => 0,
                'pages_requested' => max(5, min($pages, (int) config('cro.crawl.max_pages'))),
                'max_depth' => max(1, min($depth, (int) config('cro.crawl.max_depth'))),
                'queued_at' => now(),
                'settings' => [
                    'target_url' => $validated->url,
                    'max_pages' => $pages,
                    'max_depth' => $depth,
                    'lighthouse' => (bool) ($request->validated('lighthouse') ?? true),
                ],
                'summary' => ['requested_url' => $validated->url],
            ]);
        });

        CrawlWebsiteJob::dispatch($crawl);

        return redirect()
            ->route('analyses.show', $crawl)
            ->with('status', 'Analysis queued. The crawler will begin shortly.');
    }

    public function show(Crawl $crawl): View
    {
        $this->authorize('view', $crawl);

        $crawl->load(['project.website', 'pages']);

        return view('analyses.show', [
            'crawl' => $crawl,
            'website' => $crawl->project?->website,
        ]);
    }

    public function status(Crawl $crawl): JsonResponse
    {
        $this->authorize('view', $crawl);

        return response()->json([
            'id' => $crawl->ulid,
            'status' => $crawl->status->value,
            'stage' => $crawl->stage,
            'stage_label' => $crawl->progressLabel(),
            'progress' => $crawl->progress,
            'pages_discovered' => $crawl->pages_discovered,
            'pages_crawled' => $crawl->pages_crawled,
            'pages_failed' => $crawl->pages_failed,
            'error' => $crawl->error_message,
            'is_terminal' => $crawl->status->isTerminal(),
            'is_failed' => $crawl->status === CrawlStatus::Failed,
            'started_at' => $crawl->started_at?->toIso8601String(),
            'completed_at' => $crawl->completed_at?->toIso8601String(),
            'updated_at' => $crawl->updated_at?->toIso8601String(),
            'retry_url' => route('analyses.retry', $crawl),
            'current_url' => data_get($crawl->summary, 'current_url'),
            'results_url' => $crawl->status === CrawlStatus::Completed ? route('analyses.show', $crawl) : null,
        ]);
    }

    public function retry(Crawl $crawl): RedirectResponse
    {
        $this->authorize('update', $crawl);

        if (! $crawl->status->isTerminal()) {
            return back()->withErrors(['crawl' => 'This analysis is still running.']);
        }

        $retry = Crawl::create([
            'workspace_id' => $crawl->workspace_id,
            'project_id' => $crawl->project_id,
            'created_by' => request()->user()->id,
            'status' => CrawlStatus::Queued,
            'stage' => 'queued',
            'progress' => 0,
            'pages_requested' => $crawl->pages_requested,
            'max_depth' => $crawl->max_depth,
            'queued_at' => now(),
            'settings' => $crawl->settings,
            'summary' => [
                'requested_url' => data_get($crawl->settings, 'target_url'),
                'retry_of' => $crawl->ulid,
            ],
        ]);

        CrawlWebsiteJob::dispatch($retry);

        return redirect()
            ->route('analyses.show', $retry)
            ->with('status', 'A new analysis has been queued.');
    }
}
