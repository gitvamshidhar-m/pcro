<?php

namespace App\Providers;

use App\Contracts\CrawlRunner;
use App\Services\Analysis\AnalysisPipeline;
use App\Services\Analysis\AuditRuleBook;
use App\Services\Analysis\CategoryScorer;
use App\Services\Analysis\ConversionAuditEngine;
use App\Services\Analysis\DeterministicRecommendationBuilder;
use App\Services\Analysis\FrictionAnalyzer;
use App\Services\Analysis\MessagingAuditEngine;
use App\Services\Analysis\OpportunityBuilder;
use App\Services\Analysis\TechnicalAuditEngine;
use App\Services\Analysis\TrustAuditEngine;
use App\Services\Analysis\UxAuditEngine;
use App\Services\Ai\AiAnalysisService;
use App\Services\Ai\AiContextBuilder;
use App\Services\Ai\AiResponseValidator;
use App\Services\Crawler\HttpCrawlRunner;
use App\Services\Crawler\ProcessCrawlRunner;
use App\Services\Url\DnsHostResolver;
use App\Services\Url\HostResolver;
use App\Support\WorkspaceContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkspaceContext::class);
        $this->app->bind(HostResolver::class, DnsHostResolver::class);

        $this->app->bind(CrawlRunner::class, function (): CrawlRunner {
            return config('cro.worker.mode') === 'http'
                ? $this->app->make(HttpCrawlRunner::class)
                : $this->app->make(ProcessCrawlRunner::class);
        });

        $this->app->singleton(AuditRuleBook::class);
        $this->app->singleton(CategoryScorer::class);
        $this->app->singleton(FrictionAnalyzer::class);
        $this->app->singleton(OpportunityBuilder::class);
        $this->app->singleton(DeterministicRecommendationBuilder::class);

        $this->app->singleton(AiContextBuilder::class);
        $this->app->singleton(AiResponseValidator::class);
        $this->app->singleton(AiAnalysisService::class);

        $this->app->singleton(AnalysisPipeline::class, function ($app): AnalysisPipeline {
            return new AnalysisPipeline(
                engines: [
                    $app->make(UxAuditEngine::class),
                    $app->make(ConversionAuditEngine::class),
                    $app->make(TrustAuditEngine::class),
                    $app->make(TechnicalAuditEngine::class),
                    $app->make(MessagingAuditEngine::class),
                ],
                ruleBook: $app->make(AuditRuleBook::class),
                scorer: $app->make(CategoryScorer::class),
                friction: $app->make(FrictionAnalyzer::class),
                opportunities: $app->make(OpportunityBuilder::class),
                recommendations: $app->make(DeterministicRecommendationBuilder::class),
                ai: $app->make(AiAnalysisService::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
