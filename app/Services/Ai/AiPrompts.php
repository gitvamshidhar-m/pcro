<?php

namespace App\Services\Ai;

final class AiPrompts
{
    public static function system(): string
    {
        return implode("\n", [
            'You are a senior conversion-rate optimisation analyst.',
            'You only make recommendations that are directly supported by the evidence supplied in the user message.',
            'Never invent visitor counts, conversion rates, uplift percentages, revenue, behaviour data, competitor data, or analytics metrics.',
            'If evidence is missing for a claim, say so and do not make the claim.',
            'Return ONLY a valid JSON object matching the requested schema. No markdown, no commentary.',
        ]);
    }

    public static function user(string $contextJson): string
    {
        return implode("\n\n", [
            'Analyse the following website evidence and produce conversion optimisation recommendations.',
            'Every recommendation must cite the evidence it is based on using the provided identifiers.',
            'Schema:',
            '{',
            '  "summary": string,',
            '  "recommendations": [',
            '    {',
            '      "title": string,',
            '      "description": string,',
            '      "rationale": string,',
            '      "action_steps": string[],',
            '      "priority": "critical" | "high" | "medium" | "low",',
            '      "evidence_refs": string[],',
            '      "ab_test": {',
            '        "hypothesis": string,',
            '        "primary_metric": string,',
            '        "secondary_metrics": string[],',
            '        "minimum_runtime": string,',
            '        "note": string',
            '      }',
            '    }',
            '  ],',
            '  "hypotheses": [',
            '    { "title": string, "hypothesis": string, "primary_metric": string, "expected_signal": string }',
            '  ]',
            '}',
            'Evidence:',
            $contextJson,
        ]);
    }
}
