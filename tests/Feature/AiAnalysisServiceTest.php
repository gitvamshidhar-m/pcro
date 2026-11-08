<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\Crawl;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Ai\AiAnalysisService;
use App\Services\Ai\AiResponseValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crawl(): Crawl
    {
        $workspace = Workspace::factory()->create();
        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create([
            'target_url' => 'https://example.com/',
        ]);

        return Crawl::factory()->for($workspace)->for($project)->completed()->create([
            'settings' => ['target_url' => 'https://example.com/'],
            'summary' => ['requested_url' => 'https://example.com/'],
        ]);
    }

    public function test_it_skips_gracefully_without_an_api_key(): void
    {
        $crawl = $this->crawl();

        $result = app(AiAnalysisService::class)->run($crawl);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame(0, $result['recommendations']);
        $this->assertSame('skipped', data_get($crawl->fresh()->summary, 'ai.status'));
        $this->assertSame(0, AiAnalysis::count());
    }

    public function test_it_persists_ai_recommendations_for_a_valid_response(): void
    {
        config(['cro.ai.api_key' => 'test-key']);
        config(['cro.ai.base_url' => 'https://api.example.com/v1']);

        Http::fake([
            'api.example.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'summary' => 'The homepage lacks a clear CTA.',
                        'recommendations' => [[
                            'title' => 'Add a primary CTA',
                            'description' => 'Place a dominant CTA in the hero.',
                            'rationale' => 'No primary CTA was detected on the homepage.',
                            'action_steps' => ['Design the button', 'Place it above the fold'],
                            'priority' => 'high',
                            'evidence_refs' => ['finding-1'],
                            'ab_test' => [
                                'hypothesis' => 'Adding the CTA will increase clicks.',
                                'primary_metric' => 'CTA click-through rate',
                                'secondary_metrics' => ['bounce rate'],
                                'minimum_runtime' => '2 weeks',
                                'note' => 'No uplift guaranteed.',
                            ],
                        ]],
                        'hypotheses' => [[
                            'title' => 'Hero CTA',
                            'hypothesis' => 'A visible CTA increases engagement.',
                            'primary_metric' => 'CTA click-through rate',
                            'expected_signal' => 'More clicks.',
                        ]],
                    ])],
                ]],
                'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 80],
            ], 200),
        ]);

        $crawl = $this->crawl();

        $result = app(AiAnalysisService::class)->run($crawl);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['recommendations']);
        $this->assertDatabaseHas('ai_analyses', ['crawl_id' => $crawl->id, 'status' => 'completed']);
        $this->assertDatabaseHas('recommendations', ['crawl_id' => $crawl->id, 'source' => 'ai']);

        $recommendation = Recommendation::where('crawl_id', $crawl->id)->where('source', 'ai')->first();
        $this->assertNotNull($recommendation);
        $this->assertSame('ai', $recommendation->source);
        $this->assertSame('high', $recommendation->priority->value);
        $this->assertNotNull($recommendation->ab_test);
    }

    public function test_it_marks_the_analysis_as_failed_for_invalid_json(): void
    {
        config(['cro.ai.api_key' => 'test-key']);
        config(['cro.ai.base_url' => 'https://api.example.com/v1']);

        Http::fake([
            'api.example.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'not json at all'],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        $crawl = $this->crawl();

        $result = app(AiAnalysisService::class)->run($crawl);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $result['recommendations']);
        $this->assertDatabaseHas('ai_analyses', ['crawl_id' => $crawl->id, 'status' => 'failed']);
    }
}
