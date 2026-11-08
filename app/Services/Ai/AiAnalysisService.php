<?php

namespace App\Services\Ai;

use App\Models\AiAnalysis;
use App\Models\Crawl;
use App\Models\Recommendation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AiAnalysisService
{
    public function __construct(
        private readonly AiContextBuilder $contextBuilder,
        private readonly AiResponseValidator $validator,
    ) {}

    /**
     * @return array{status: string, ai_analysis_id: ?string, recommendations: int, error: ?string}
     */
    public function run(Crawl $crawl): array
    {
        $crawl->loadMissing(['project.website']);

        if (! (bool) config('cro.ai.enabled', true)) {
            return $this->skip($crawl, 'AI is disabled by configuration.');
        }

        $apiKey = config('cro.ai.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return $this->skip($crawl, 'No AI API key is configured.');
        }

        $context = $this->contextBuilder->build($crawl);

        $estimatedInputTokens = (int) ceil(strlen($context['json']) / 4);
        $estimatedCost = ($estimatedInputTokens / 1_000_000) * (float) config('cro.ai.input_cost_per_million', 0.15);

        if ($estimatedCost > (float) config('cro.ai.max_cost_usd_per_run', 0.50)) {
            return $this->skip($crawl, 'Estimated AI cost exceeds the configured per-run limit.');
        }

        $analysis = AiAnalysis::create([
            'workspace_id' => $crawl->workspace_id,
            'crawl_id' => $crawl->id,
            'provider' => (string) config('cro.ai.provider', 'openai'),
            'model' => (string) config('cro.ai.model', 'gpt-4o-mini'),
            'prompt_version' => (string) config('cro.ai.prompt_version', 'cro-analysis-v1'),
            'input_hash' => $context['hash'],
            'status' => 'pending',
            'request_meta' => [
                'truncated' => $context['truncated'],
                'estimated_input_tokens' => $estimatedInputTokens,
                'estimated_cost_usd' => round($estimatedCost, 6),
            ],
        ]);

        $startedAt = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('cro.ai.timeout', 90))
                ->post(rtrim((string) config('cro.ai.base_url'), '/').'/chat/completions', [
                    'model' => config('cro.ai.model'),
                    'temperature' => (float) config('cro.ai.temperature', 0.2),
                    'max_tokens' => (int) config('cro.ai.max_tokens', 3000),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => AiPrompts::system()],
                        ['role' => 'user', 'content' => AiPrompts::user($context['json'])],
                    ],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('AI request failed with HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500));
            }

            $payload = $response->json();
            $rawContent = data_get($payload, 'choices.0.message.content');

            if (! is_string($rawContent) || trim($rawContent) === '') {
                throw new \RuntimeException('The AI response did not contain message content.');
            }

            $validation = $this->validator->validate($rawContent);

            $promptTokens = (int) data_get($payload, 'usage.prompt_tokens', 0);
            $completionTokens = (int) data_get($payload, 'usage.completion_tokens', 0);
            $cost = ($promptTokens / 1_000_000) * (float) config('cro.ai.input_cost_per_million', 0.15)
                + ($completionTokens / 1_000_000) * (float) config('cro.ai.output_cost_per_million', 0.60);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if (! $validation['valid']) {
                $analysis->forceFill([
                    'status' => 'failed',
                    'response' => ['raw' => $rawContent],
                    'error' => implode(' ', $validation['errors']),
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'cost_usd' => round($cost, 6),
                    'duration_ms' => $durationMs,
                ])->save();

                Log::warning('AI response validation failed', [
                    'crawl_id' => $crawl->id,
                    'errors' => $validation['errors'],
                ]);

                return [
                    'status' => 'failed',
                    'ai_analysis_id' => $analysis->ulid,
                    'recommendations' => 0,
                    'error' => implode(' ', $validation['errors']),
                ];
            }

            $data = $validation['data'];

            $analysis->forceFill([
                'status' => 'completed',
                'response' => $data,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'cost_usd' => round($cost, 6),
                'duration_ms' => $durationMs,
            ])->save();

            $recommendationCount = $this->createRecommendations($crawl, $analysis, $data['recommendations']);

            $summary = $crawl->summary ?? [];

            if (! is_array($summary)) {
                $summary = [];
            }

            $summary['ai'] = [
                'status' => 'completed',
                'analysis_id' => $analysis->ulid,
                'recommendations' => $recommendationCount,
                'completed_at' => now()->toIso8601String(),
            ];

            $crawl->forceFill(['summary' => $summary])->save();

            return [
                'status' => 'completed',
                'ai_analysis_id' => $analysis->ulid,
                'recommendations' => $recommendationCount,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $analysis->forceFill([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 2000),
                'duration_ms' => $durationMs,
            ])->save();

            Log::error('AI analysis failed', [
                'crawl_id' => $crawl->id,
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'ai_analysis_id' => $analysis->ulid,
                'recommendations' => 0,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $recommendations
     */
    private function createRecommendations(Crawl $crawl, AiAnalysis $analysis, array $recommendations): int
    {
        $created = 0;

        foreach ($recommendations as $item) {
            Recommendation::create([
                'workspace_id' => $crawl->workspace_id,
                'crawl_id' => $crawl->id,
                'ai_analysis_id' => $analysis->id,
                'source' => 'ai',
                'title' => $item['title'],
                'description' => $item['description'],
                'rationale' => $item['rationale'],
                'action_steps' => $item['action_steps'],
                'ab_test' => $item['ab_test'],
                'priority' => $item['priority'],
                'evidence_refs' => $item['evidence_refs'],
                'is_validated' => false,
            ]);

            $created++;
        }

        return $created;
    }

    private function skip(Crawl $crawl, string $reason): array
    {
        $summary = $crawl->summary ?? [];

        if (! is_array($summary)) {
            $summary = [];
        }

        $summary['ai'] = [
            'status' => 'skipped',
            'reason' => $reason,
            'completed_at' => now()->toIso8601String(),
        ];

        $crawl->forceFill(['summary' => $summary])->save();

        return [
            'status' => 'skipped',
            'ai_analysis_id' => null,
            'recommendations' => 0,
            'error' => $reason,
        ];
    }
}
