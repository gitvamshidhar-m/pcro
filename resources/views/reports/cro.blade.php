<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CRO Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; color: #4f46e5; margin-bottom: 4px; }
        h2 { font-size: 14px; color: #111827; margin-top: 16px; margin-bottom: 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        h3 { font-size: 12px; color: #374151; margin-top: 10px; margin-bottom: 4px; }
        .muted { color: #6b7280; font-size: 10px; }
        .score { font-size: 26px; font-weight: bold; color: #4f46e5; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; }
        .pass { background: #ecfdf5; color: #047857; }
        .warning { background: #fffbeb; color: #b45309; }
        .fail { background: #fef2f2; color: #b91c1c; }
        .na { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>
    <h1>Predictive CRO Intelligence — Report</h1>
    <p class="muted">
        {{ $website?->domain }} — {{ data_get($crawl->settings, 'target_url') }}<br>
        Generated {{ now()->format('Y-m-d H:i') }} · Crawl {{ $crawl->ulid }} · Version {{ config('cro.version') }}
    </p>

    <table>
        <tr>
            <th>CRO score</th>
            <th>Friction index</th>
            <th>Pages inspected</th>
            <th>Findings</th>
            <th>Opportunities</th>
        </tr>
        <tr>
            <td class="score">{{ $crawl->cro_score !== null ? number_format((float) $crawl->cro_score, 0) : '—' }}</td>
            <td class="score">{{ $crawl->friction_score !== null ? number_format((float) $crawl->friction_score, 0) : '—' }}</td>
            <td>{{ $crawl->pages_crawled }}/{{ $crawl->pages_requested }}</td>
            <td>{{ $crawl->findings->count() }}</td>
            <td>{{ $crawl->opportunities->count() }}</td>
        </tr>
    </table>

    <h2>Category scores</h2>
    <table>
        <tr>
            <th>Category</th>
            <th>Score</th>
            <th>Weight</th>
        </tr>
        @foreach ($categoryScores as $score)
            <tr>
                <td>{{ $score->category->label() }}</td>
                <td>{{ number_format((float) $score->score, 1) }}</td>
                <td>{{ number_format((float) $score->weight, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Top opportunities</h2>
    <table>
        <tr>
            <th>#</th>
            <th>Title</th>
            <th>Category</th>
            <th>Score</th>
            <th>Impact</th>
            <th>Effort</th>
        </tr>
        @foreach ($opportunities as $index => $opportunity)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $opportunity->title }}</td>
                <td>{{ $opportunity->category->label() }}</td>
                <td>{{ number_format((float) $opportunity->score, 0) }}</td>
                <td>{{ $opportunity->impact }}</td>
                <td>{{ $opportunity->effort }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Recommendations</h2>
    @foreach ($recommendations as $recommendation)
        <h3>{{ $recommendation->title }} ({{ $recommendation->priority->label() }})</h3>
        <p>{{ $recommendation->description }}</p>
        <p class="muted">{{ $recommendation->rationale }}</p>
        @if (is_array($recommendation->action_steps))
            <ul>
                @foreach ($recommendation->action_steps as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ul>
        @endif
    @endforeach

    <h2>Findings</h2>
    <table>
        <tr>
            <th>Severity</th>
            <th>Category</th>
            <th>Title</th>
            <th>Confidence</th>
        </tr>
        @foreach ($findings->sortByDesc(fn ($finding) => $finding->severity->rank()) as $finding)
            <tr>
                <td><span class="badge {{ $finding->severity->value }}">{{ $finding->severity->label() }}</span></td>
                <td>{{ $finding->category->label() }}</td>
                <td>{{ $finding->title }}</td>
                <td>{{ $finding->confidence }}%</td>
            </tr>
        @endforeach
    </table>

    <p class="muted" style="margin-top: 16px;">
        Scores are heuristic and evidence-based. No visitor counts, conversion rates, uplift percentages, behaviour or competitor data is fabricated.
    </p>
</body>
</html>
