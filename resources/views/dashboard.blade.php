<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-8 text-white shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-indigo-100">Predictive CRO Intelligence</p>
                        <h3 class="mt-1 text-2xl font-semibold">Analyse any public website in minutes</h3>
                        <p class="mt-2 max-w-2xl text-sm text-indigo-100">No tracking code, analytics access or visitor data is required. Heuristic, evidence-based scores only.</p>
                    </div>
                    <a href="{{ route('analyses.create') }}" class="inline-flex items-center rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-indigo-600 shadow-sm transition hover:bg-indigo-50">
                        New analysis
                    </a>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Completed analyses</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $completedCount }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Recent analyses</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $latestCrawls->count() }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Workspace</p>
                    <p class="mt-2 truncate text-lg font-semibold text-gray-900">{{ $currentWorkspace?->name ?? 'Personal' }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                    <h3 class="text-base font-semibold text-gray-900">Recent analyses</h3>
                    <a href="{{ route('analyses.history') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View all</a>
                </div>

                @if ($latestCrawls->isEmpty())
                    <div class="px-6 py-12 text-center">
                        <p class="text-sm text-gray-500">No analyses yet. Start your first one to see scores, opportunities and recommendations.</p>
                        <a href="{{ route('analyses.create') }}" class="mt-4 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                            Start analysis
                        </a>
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach ($latestCrawls as $crawl)
                            <a href="{{ $crawl->status === \App\Enums\CrawlStatus::Completed ? route('analyses.dashboard', $crawl) : route('analyses.show', $crawl) }}"
                               class="flex items-center justify-between gap-4 px-6 py-4 transition hover:bg-gray-50">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $crawl->project?->website?->domain ?? 'Website' }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ data_get($crawl->settings, 'target_url') }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    @if ($crawl->status === \App\Enums\CrawlStatus::Completed)
                                        <span class="text-sm font-semibold text-gray-900">{{ number_format((float) $crawl->cro_score, 0) }}/100</span>
                                    @endif
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $crawl->status->badgeClasses() }}">
                                        {{ $crawl->status->label() }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
