<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">New CRO analysis</h2>
            <p class="text-sm text-gray-500">Enter a public website address. No tracking code or analytics access is required.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-100 px-6 py-5">
                    <h3 class="text-base font-semibold text-gray-900">Website details</h3>
                    <p class="mt-1 text-sm text-gray-500">We crawl up to {{ $maxPages }} public pages and run five evidence-based CRO audits.</p>
                </div>

                <form method="POST" action="{{ route('analyses.store') }}" class="space-y-6 px-6 py-6">
                    @csrf

                    <div>
                        <label for="url" class="block text-sm font-medium text-gray-700">Website URL</label>
                        <input id="url" name="url" type="text" value="{{ old('url') }}" required
                               placeholder="https://example.com"
                               class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('url')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-gray-500">Only public http and https sites on ports 80/443 are supported. Internal and private addresses are blocked.</p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label for="pages" class="block text-sm font-medium text-gray-700">Pages to crawl</label>
                            <input id="pages" name="pages" type="number" min="5" max="{{ $maxPages }}" value="{{ old('pages', min(30, $maxPages)) }}"
                                   class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('pages')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="depth" class="block text-sm font-medium text-gray-700">Maximum link depth</label>
                            <input id="depth" name="depth" type="number" min="1" max="{{ $maxDepth }}" value="{{ old('depth', min(3, $maxDepth)) }}"
                                   class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('depth')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <input type="hidden" name="lighthouse" value="0">
                        <input type="checkbox" name="lighthouse" value="1" @checked(old('lighthouse', true))
                               class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">Run Lighthouse performance audit</span>
                            <span class="block text-xs text-gray-500">Uses a mobile Lighthouse run for the homepage. Adds roughly one minute to the analysis.</span>
                        </span>
                    </label>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                        <a href="{{ route('dashboard') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                        <button type="submit"
                                class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Start analysis
                        </button>
                    </div>
                </form>
            </div>

            @if ($recentCrawls->isNotEmpty())
                <div class="mt-8">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Recent analyses</h3>
                    <div class="mt-3 divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                        @foreach ($recentCrawls as $recent)
                            <a href="{{ route('analyses.show', $recent) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-gray-50">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $recent->project?->website?->domain ?? 'Website' }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ data_get($recent->settings, 'target_url') }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $recent->status->badgeClasses() }}">
                                    {{ $recent->status->label() }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
