<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">Recommendations</h2>
            <a href="{{ route('analyses.dashboard', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            @forelse ($recommendations as $recommendation)
                <a href="{{ route('analyses.recommendation', [$crawl, $recommendation]) }}" class="block rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:ring-indigo-200">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $recommendation->source === 'ai' ? 'AI' : 'Heuristic' }}</p>
                            <h3 class="mt-1 text-base font-semibold text-gray-900">{{ $recommendation->title }}</h3>
                            <p class="mt-2 line-clamp-2 text-sm text-gray-600">{{ $recommendation->description }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $recommendation->priority->badgeClasses() }}">
                            {{ $recommendation->priority->label() }}
                        </span>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">
                    No recommendations generated.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
