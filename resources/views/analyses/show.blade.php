<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-900 leading-tight">
                    {{ $website?->domain ?? 'Website analysis' }}
                </h2>
                <p class="truncate text-sm text-gray-500">{{ data_get($crawl->settings, 'target_url') }}</p>
            </div>
            <div class="mt-2 flex items-center gap-3 sm:mt-0">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1 {{ $crawl->status->badgeClasses() }}">
                    {{ $crawl->status->label() }}
                </span>
                <a href="{{ route('analyses.create') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">New analysis</a>
            </div>
        </div>
    </x-slot>

    @php
        $summary = $crawl->summary ?? [];
        $lighthouseCategories = data_get($summary, 'lighthouse_categories', []);
        $initial = [
            'status' => $crawl->status->value,
            'stage' => $crawl->stage,
            'stage_label' => $crawl->progressLabel(),
            'progress' => (int) $crawl->progress,
            'pages_discovered' => (int) $crawl->pages_discovered,
            'pages_crawled' => (int) $crawl->pages_crawled,
            'pages_failed' => (int) $crawl->pages_failed,
            'error' => $crawl->error_message,
            'is_terminal' => $crawl->status->isTerminal(),
            'is_failed' => $crawl->status === \App\Enums\CrawlStatus::Failed,
            'current_url' => data_get($summary, 'current_url'),
            'status_url' => route('analyses.status', $crawl),
        ];
    @endphp

    <script type="application/json" id="analysis-progress-state">{!! json_encode($initial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    <div class="py-10" x-data="analysisProgress()" x-init="start()">
        <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="space-y-6 px-6 py-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Progress</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900" x-text="state.stage_label">{{ $crawl->progressLabel() }}</p>
                        </div>
                        <p class="text-3xl font-semibold tabular-nums text-gray-900"><span x-text="state.progress">{{ (int) $crawl->progress }}</span>%</p>
                    </div>

                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-indigo-600 transition-all duration-500"
                             :style="'width: ' + Math.max(2, state.progress) + '%'"
                             style="width: {{ max(2, (int) $crawl->progress) }}%"></div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pages discovered</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900" x-text="state.pages_discovered">{{ (int) $crawl->pages_discovered }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pages crawled</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900" x-text="state.pages_crawled">{{ (int) $crawl->pages_crawled }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pages failed</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900" x-text="state.pages_failed">{{ (int) $crawl->pages_failed }}</p>
                        </div>
                    </div>

                    <div x-show="state.current_url" x-cloak class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Currently crawling</p>
                        <p class="mt-1 truncate text-sm text-gray-700" x-text="state.current_url"></p>
                    </div>

                    <div x-show="!state.is_terminal" x-cloak class="flex items-center gap-2 text-sm text-gray-500">
                        <svg class="h-4 w-4 animate-spin text-indigo-500" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                        </svg>
                        <span>This page updates itself. You can leave it open while the crawl runs.</span>
                    </div>
                </div>
            </div>

            <div x-show="state.is_failed" x-cloak class="overflow-hidden rounded-2xl border border-red-200 bg-red-50 shadow-sm">
                <div class="space-y-4 px-6 py-6">
                    <div>
                        <h3 class="text-base font-semibold text-red-900">This analysis could not be completed</h3>
                        <p class="mt-1 text-sm text-red-800" x-text="state.error"></p>
                    </div>
                    <form method="POST" action="{{ route('analyses.retry', $crawl) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                            Retry analysis
                        </button>
                    </form>
                </div>
            </div>

            @if ($crawl->status === \App\Enums\CrawlStatus::Completed)
                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="border-b border-gray-100 px-6 py-5">
                        <h3 class="text-base font-semibold text-gray-900">Crawl summary</h3>
                        <p class="mt-1 text-sm text-gray-500">Evidence captured directly from the public website. No visitor, conversion or competitor data is inferred.</p>
                    </div>
                    <div class="grid gap-4 px-6 py-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Domain</p>
                            <p class="mt-1 truncate text-sm font-semibold text-gray-900">{{ data_get($summary, 'domain', $website?->domain) }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pages inspected</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ (int) data_get($summary, 'inspected_pages', $crawl->pages_crawled) }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">HTTPS</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ data_get($summary, 'has_ssl') ? 'Enabled' : 'Not detected' }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Lighthouse performance</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ data_get($lighthouseCategories, 'performance') !== null ? data_get($lighthouseCategories, 'performance') : 'Not available' }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Accessibility (axe)</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ data_get($summary, 'axe_available') ? data_get($summary, 'axe_pages').' page(s) audited' : 'Not available' }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Broken links</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ (int) data_get($summary, 'broken_links', 0) }}</p>
                        </div>
                    </div>

                    @if (! empty($summary['failures']))
                        <div class="border-t border-gray-100 px-6 py-5">
                            <h4 class="text-sm font-semibold text-gray-900">Pages that could not be inspected</h4>
                            <p class="mt-1 text-xs text-gray-500">Findings are never raised for these pages because no evidence was collected.</p>
                            <ul class="mt-3 space-y-1 text-sm text-gray-600">
                                @foreach (array_slice($summary['failures'], 0, 10) as $failure)
                                    <li class="truncate">{{ data_get($failure, 'url', 'Unknown URL') }} — {{ data_get($failure, 'error', data_get($failure, 'reason', 'Inspection failed')) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                @if ($crawl->pages->isNotEmpty())
                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                        <div class="border-b border-gray-100 px-6 py-5">
                            <h3 class="text-base font-semibold text-gray-900">Inspected pages</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $crawl->pages->count() }} page(s) captured during this crawl.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Page</th>
                                        <th class="px-4 py-3">Type</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Words</th>
                                        <th class="px-4 py-3">Depth</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($crawl->pages->sortBy([['depth', 'asc'], ['id', 'asc']]) as $page)
                                        <tr class="hover:bg-gray-50">
                                            <td class="max-w-md px-6 py-3">
                                                <p class="truncate font-medium text-gray-900">{{ $page->title ?: $page->path }}</p>
                                                <p class="truncate text-xs text-gray-500">{{ $page->url }}</p>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $page->page_type->label() }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $page->status_code ?? '—' }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ number_format($page->word_count) }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $page->depth }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif

            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Back to dashboard</a>
                <form method="POST" action="{{ route('analyses.retry', $crawl) }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Re-run crawl</button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.analysisProgress = function () {
                return {
                    state: JSON.parse(document.getElementById('analysis-progress-state').textContent),
                    timer: null,
                    reloading: false,
                    start() {
                        if (this.state.is_terminal) {
                            return;
                        }

                        this.schedule();
                    },
                    schedule() {
                        if (this.reloading) {
                            return;
                        }

                        this.timer = window.setTimeout(() => this.poll(), 2000);
                    },
                    async poll() {
                        try {
                            const response = await fetch(this.state.status_url, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            });

                            if (!response.ok) {
                                throw new Error('Status request failed with ' + response.status);
                            }

                            const next = await response.json();
                            const wasTerminal = this.state.is_terminal;
                            this.state = { ...this.state, ...next };

                            if (next.is_terminal) {
                                window.clearTimeout(this.timer);

                                if (!next.is_failed && !wasTerminal) {
                                    this.reloading = true;
                                    window.location.reload();
                                }

                                return;
                            }

                            this.schedule();
                        } catch (error) {
                            this.schedule();
                        }
                    },
                };
            };
        </script>
    @endpush
</x-app-layout>
