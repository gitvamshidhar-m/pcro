<?php

namespace App\Http\Controllers;

use App\Enums\AuditCategory;
use App\Models\AuditFinding;
use App\Models\Crawl;
use App\Models\Page;
use App\Models\Recommendation;
use App\Services\Analysis\CrawlComparisonService;
use App\Support\WorkspaceContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CrawlDashboardController extends Controller
{
    public function show(Crawl $crawl): View
    {
        $this->authorize('view', $crawl);

        $crawl->load([
            'project.website',
            'categoryScores',
            'opportunities' => fn ($query) => $query->limit(5),
            'recommendations' => fn ($query) => $query->limit(6),
            'findings',
        ]);

        return view('analyses.dashboard', [
            'crawl' => $crawl,
            'website' => $crawl->project?->website,
            'categoryScores' => $crawl->categoryScores->keyBy(fn ($score) => $score->category->value),
            'topOpportunities' => $crawl->opportunities,
            'topRecommendations' => $crawl->recommendations,
            'findingCounts' => $crawl->findings
                ->groupBy(fn (AuditFinding $finding) => $finding->category->value)
                ->map->count(),
        ]);
    }

    public function opportunities(Crawl $crawl): View
    {
        $this->authorize('view', $crawl);

        return view('analyses.opportunities', [
            'crawl' => $crawl,
            'opportunities' => $crawl->opportunities()->with(['page', 'finding'])->get(),
        ]);
    }

    public function recommendations(Crawl $crawl): View
    {
        $this->authorize('view', $crawl);

        return view('analyses.recommendations', [
            'crawl' => $crawl,
            'recommendations' => $crawl->recommendations()->with(['opportunity', 'aiAnalysis'])->get(),
        ]);
    }

    public function recommendation(Crawl $crawl, Recommendation $recommendation): View
    {
        $this->authorize('view', $crawl);

        abort_unless($recommendation->crawl_id === $crawl->id, 404);

        return view('analyses.recommendation', [
            'crawl' => $crawl,
            'recommendation' => $recommendation->load(['opportunity', 'aiAnalysis']),
        ]);
    }

    public function pages(Crawl $crawl): View
    {
        $this->authorize('view', $crawl);

        return view('analyses.pages', [
            'crawl' => $crawl,
            'pages' => $crawl->pages()->with('metrics')->orderBy('depth')->orderBy('id')->get(),
        ]);
    }

    public function page(Crawl $crawl, Page $page): View
    {
        $this->authorize('view', $crawl);

        abort_unless($page->crawl_id === $crawl->id, 404);

        return view('analyses.page', [
            'crawl' => $crawl,
            'page' => $page->load('elements', 'metrics'),
        ]);
    }

    public function friction(Crawl $crawl): View
    {
        $this->authorize('view', $crawl);

        return view('analyses.friction', [
            'crawl' => $crawl,
            'factors' => data_get($crawl->summary, 'friction.factors', []),
        ]);
    }

    public function health(Crawl $crawl): View
    {
        $this->authorize('view', $crawl);

        $technicalScore = $crawl->categoryScores->firstWhere('category', AuditCategory::Technical);

        return view('analyses.health', [
            'crawl' => $crawl,
            'technicalScore' => $technicalScore,
            'lighthouse' => data_get($crawl->summary, 'lighthouse_categories'),
            'mobile' => data_get($crawl->summary, 'mobile'),
            'brokenLinks' => $crawl->summary['broken_links'] ?? [],
        ]);
    }

    public function categories(Crawl $crawl, ?string $category = null): View
    {
        $this->authorize('view', $crawl);

        $selected = $category !== null ? AuditCategory::tryFrom($category) : null;

        return view('analyses.categories', [
            'crawl' => $crawl,
            'selected' => $selected,
            'scores' => $crawl->categoryScores->keyBy(fn ($score) => $score->category->value),
            'findings' => $selected
                ? $crawl->findings()->where('category', $selected->value)->get()
                : $crawl->findings()->get(),
        ]);
    }

    public function history(): View
    {
        $workspace = app(WorkspaceContext::class)->require();

        return view('analyses.history', [
            'crawls' => Crawl::query()
                ->where('workspace_id', $workspace->id)
                ->latest('id')
                ->with(['project.website'])
                ->paginate(15),
        ]);
    }

    public function compare(Crawl $crawl, Crawl $other): View
    {
        $this->authorize('view', $crawl);
        $this->authorize('view', $other);

        $comparison = app(CrawlComparisonService::class)->compare($crawl, $other);

        return view('analyses.compare', [
            'comparison' => $comparison,
        ]);
    }

    public function report(Crawl $crawl): Response
    {
        $this->authorize('view', $crawl);

        $crawl->load([
            'project.website',
            'categoryScores',
            'opportunities' => fn ($query) => $query->limit(10),
            'recommendations' => fn ($query) => $query->limit(10),
            'findings',
            'pages',
        ]);

        $pdf = Pdf::loadView('reports.cro', [
            'crawl' => $crawl,
            'website' => $crawl->project?->website,
            'categoryScores' => $crawl->categoryScores->keyBy(fn ($score) => $score->category->value),
            'opportunities' => $crawl->opportunities,
            'recommendations' => $crawl->recommendations,
            'findings' => $crawl->findings,
        ])->setPaper('a4');

        return $pdf->download('cro-report-'.$crawl->ulid.'.pdf');
    }
}
