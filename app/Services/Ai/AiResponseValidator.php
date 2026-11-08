<?php

namespace App\Services\Ai;

final class AiResponseValidator
{
    /**
     * @return array{valid: bool, data: array<string, mixed>|null, errors: array<int, string>}
     */
    public function validate(string $raw): array
    {
        $errors = [];

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return ['valid' => false, 'data' => null, 'errors' => ['The response is not valid JSON.']];
        }

        $maxRecommendations = (int) config('cro.ai.max_recommendations', 12);

        if (! isset($decoded['summary']) || ! is_string($decoded['summary'])) {
            $errors[] = 'Missing or invalid "summary".';
        }

        if (! isset($decoded['recommendations']) || ! is_array($decoded['recommendations'])) {
            $errors[] = 'Missing or invalid "recommendations" array.';

            return ['valid' => false, 'data' => null, 'errors' => $errors];
        }

        $allowedPriorities = ['critical', 'high', 'medium', 'low'];
        $normalised = [];

        foreach (array_slice($decoded['recommendations'], 0, $maxRecommendations) as $index => $item) {
            if (! is_array($item)) {
                $errors[] = "Recommendation {$index} is not an object.";
                continue;
            }

            $title = $this->string($item['title'] ?? null);
            $description = $this->string($item['description'] ?? null);
            $rationale = $this->string($item['rationale'] ?? null);

            if ($title === null || $description === null || $rationale === null) {
                $errors[] = "Recommendation {$index} is missing title, description or rationale.";
                continue;
            }

            $priority = in_array($item['priority'] ?? null, $allowedPriorities, true)
                ? (string) $item['priority']
                : 'medium';

            $steps = [];

            foreach (($item['action_steps'] ?? []) as $step) {
                if (is_string($step) && trim($step) !== '') {
                    $steps[] = trim($step);
                }
            }

            if ($steps === []) {
                $steps[] = 'Review the evidence and implement the recommended change.';
            }

            $refs = [];

            foreach (($item['evidence_refs'] ?? []) as $ref) {
                if (is_string($ref) && trim($ref) !== '') {
                    $refs[] = trim($ref);
                }
            }

            $abTest = $this->normaliseAbTest($item['ab_test'] ?? null);

            $normalised[] = [
                'title' => $title,
                'description' => $description,
                'rationale' => $rationale,
                'action_steps' => array_slice($steps, 0, 8),
                'priority' => $priority,
                'evidence_refs' => array_slice($refs, 0, 12),
                'ab_test' => $abTest,
            ];
        }

        if ($normalised === [] && $decoded['recommendations'] !== []) {
            return ['valid' => false, 'data' => null, 'errors' => $errors !== [] ? $errors : ['No valid recommendations were returned.']];
        }

        $hypotheses = [];

        foreach (($decoded['hypotheses'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = $this->string($item['title'] ?? null);
            $hypothesis = $this->string($item['hypothesis'] ?? null);

            if ($title === null || $hypothesis === null) {
                continue;
            }

            $hypotheses[] = [
                'title' => $title,
                'hypothesis' => $hypothesis,
                'primary_metric' => $this->string($item['primary_metric'] ?? null) ?? 'conversion rate',
                'expected_signal' => $this->string($item['expected_signal'] ?? null) ?? 'Improvement in the primary metric.',
            ];
        }

        if ($errors !== [] && $normalised === []) {
            return ['valid' => false, 'data' => null, 'errors' => $errors];
        }

        return [
            'valid' => true,
            'data' => [
                'summary' => trim((string) $decoded['summary']),
                'recommendations' => $normalised,
                'hypotheses' => $hypotheses,
            ],
            'errors' => $errors,
        ];
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function normaliseAbTest(mixed $value): array
    {
        if (! is_array($value)) {
            $value = [];
        }

        $secondary = [];

        foreach (($value['secondary_metrics'] ?? []) as $metric) {
            if (is_string($metric) && trim($metric) !== '') {
                $secondary[] = trim($metric);
            }
        }

        return [
            'hypothesis' => $this->string($value['hypothesis'] ?? null) ?? 'Implementing the change should improve the primary metric.',
            'primary_metric' => $this->string($value['primary_metric'] ?? null) ?? 'conversion rate',
            'secondary_metrics' => array_slice($secondary, 0, 6),
            'minimum_runtime' => $this->string($value['minimum_runtime'] ?? null) ?? '2-4 weeks or 1,000 qualifying conversions',
            'note' => $this->string($value['note'] ?? null) ?? 'No uplift percentage is guaranteed; compare against the current baseline.',
        ];
    }
}
