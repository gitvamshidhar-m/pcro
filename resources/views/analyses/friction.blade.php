<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">Conversion friction</h2>
            <a href="{{ route('analyses.dashboard', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Friction index</p>
                <p class="mt-2 text-4xl font-semibold text-gray-900">{{ number_format((float) $crawl->friction_score, 0) }}</p>
                <p class="mt-1 text-xs text-gray-500">Heuristic index of detected friction. Not a measured abandonment rate.</p>
            </div>

            <div class="space-y-3">
                @forelse ($factors as $factor)
                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $factor['label'] }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $factor['key'] }}</p>
                            </div>
                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200">
                                +{{ $factor['penalty'] }}
                            </span>
                        </div>
                        @if (! empty($factor['evidence']))
                            <pre class="mt-3 overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-600 ring-1 ring-gray-100">{{ json_encode($factor['evidence'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">
                        No friction factors were detected.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
