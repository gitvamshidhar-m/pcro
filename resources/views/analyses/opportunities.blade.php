<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">Opportunity centre</h2>
            <a href="{{ route('analyses.dashboard', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            @forelse ($opportunities as $opportunity)
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $opportunity->category->label() }}</p>
                            <h3 class="mt-1 text-base font-semibold text-gray-900">{{ $opportunity->title }}</h3>
                            <p class="mt-2 text-sm text-gray-600">{{ $opportunity->description }}</p>
                            @if ($opportunity->page)
                                <p class="mt-2 text-xs text-gray-400">{{ $opportunity->page->url }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-2xl font-semibold text-indigo-600">{{ number_format((float) $opportunity->score, 0) }}</p>
                            <p class="text-xs text-gray-400">score</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-5 gap-2 text-center text-xs">
                        @foreach (['impact' => $opportunity->impact, 'confidence' => $opportunity->confidence, 'reach' => $opportunity->reach, 'funnel' => $opportunity->funnel_relevance, 'effort' => $opportunity->effort] as $label => $value)
                            <div class="rounded-lg bg-gray-50 px-2 py-2 ring-1 ring-gray-100">
                                <p class="font-semibold text-gray-900">{{ $value }}</p>
                                <p class="text-gray-400">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if (is_array($opportunity->evidence) && isset($opportunity->evidence['observed']))
                        <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 ring-1 ring-gray-100">
                            Evidence: {{ $opportunity->evidence['observed'] }}
                        </p>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">
                    No opportunities were generated for this analysis.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
