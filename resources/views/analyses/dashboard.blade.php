<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-900 leading-tight">CRO dashboard</h2>
                <p class="truncate text-sm text-gray-500">{{ $website?->domain }} — {{ data_get($crawl->settings, 'target_url') }}</p>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-3 sm:mt-0">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1 {{ $crawl->status->badgeClasses() }}">
                    {{ $crawl->status->label() }}
                </span>
                <a href="{{ route('analyses.report', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Download PDF</a>
                <a href="{{ route('analyses.show', $crawl) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Crawl progress</a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">CRO score</p>
                    <p class="mt-2 text-4xl font-semibold text-gray-900">{{ $crawl->cro_score !== null ? number_format((float) $crawl->cro_score, 0) : '—' }}</p>
                    <p class="mt-1 text-xs text-gray-500">out of 100</p>
                </div>
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Friction index</p>
                    <p class="mt-2 text-4xl font-semibold text-gray-900">{{ $crawl->friction_score !== null ? number_format((float) $crawl->friction_score, 0) : '—' }}</p>
                    <p class="mt-1 text-xs text-gray-500">lower is better</p>
                </div>
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Opportunities</p>
                    <p class="mt-2 text-4xl font-semibold text-gray-900">{{ $crawl->opportunities->count() }}</p>
                    <p class="mt-1 text-xs text-gray-500">prioritised</p>
                </div>
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Findings</p>
                    <p class="mt-2 text-4xl font-semibold text-gray-900">{{ $crawl->findings->count() }}</p>
                    <p class="mt-1 text-xs text-gray-500">open issues</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Category scores</h3>
                    <canvas id="categoryChart" class="mt-4 h-64 w-full"></canvas>
                </div>
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Findings by category</h3>
                    <canvas id="findingsChart" class="mt-4 h-64 w-full"></canvas>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                        <h3 class="text-base font-semibold text-gray-900">Top opportunities</h3>
                        <a href="{{ route('analyses.opportunities', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View all</a>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse ($topOpportunities as $opportunity)
                            <div class="px-6 py-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ $opportunity->title }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $opportunity->category->label() }} · effort {{ $opportunity->effort }}/10</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-200">
                                        {{ number_format((float) $opportunity->score, 0) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-sm text-gray-500">No opportunities detected.</div>
                        @endforelse
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                        <h3 class="text-base font-semibold text-gray-900">Recommendations</h3>
                        <a href="{{ route('analyses.recommendations', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View all</a>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse ($topRecommendations as $recommendation)
                            <a href="{{ route('analyses.recommendation', [$crawl, $recommendation]) }}" class="block px-6 py-4 transition hover:bg-gray-50">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ $recommendation->title }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $recommendation->description }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $recommendation->priority->badgeClasses() }}">
                                        {{ $recommendation->priority->label() }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <div class="px-6 py-8 text-center text-sm text-gray-500">No recommendations generated.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('analyses.opportunities', $crawl) }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Opportunity centre</a>
                <a href="{{ route('analyses.pages', $crawl) }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50">Page analysis</a>
                <a href="{{ route('analyses.friction', $crawl) }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50">Friction</a>
                <a href="{{ route('analyses.health', $crawl) }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50">Technical health</a>
                <a href="{{ route('analyses.categories', $crawl) }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50">Category audit</a>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script>
            const categoryLabels = @json($categoryScores->map(fn ($score) => $score->category->label())->values());
            const categoryData = @json($categoryScores->map(fn ($score) => (float) $score->score)->values());
            const categoryColors = @json($categoryScores->map(fn ($score) => $score->category->color())->values());

            new Chart(document.getElementById('categoryChart'), {
                type: 'bar',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        label: 'Score',
                        data: categoryData,
                        backgroundColor: categoryColors,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, max: 100 } },
                    plugins: { legend: { display: false } }
                }
            });

            const findingLabels = @json($findingCounts->keys()->values());
            const findingData = @json($findingCounts->values());

            new Chart(document.getElementById('findingsChart'), {
                type: 'doughnut',
                data: {
                    labels: findingLabels,
                    datasets: [{
                        data: findingData,
                        backgroundColor: ['#6366f1','#8b5cf6','#0ea5e9','#10b981','#f59e0b'],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        </script>
    @endpush
</x-app-layout>
