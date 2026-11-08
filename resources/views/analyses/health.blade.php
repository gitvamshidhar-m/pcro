<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">Technical health</h2>
            <a href="{{ route('analyses.dashboard', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs text-gray-400">Technical score</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $technicalScore?->score !== null ? number_format((float) $technicalScore->score, 0) : '—' }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs text-gray-400">Broken links</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ count($brokenLinks) }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs text-gray-400">Mobile capture</p>
                    <p class="mt-2 text-lg font-semibold text-gray-900">{{ $mobile ? 'Available' : 'Not available' }}</p>
                </div>
            </div>

            @if (is_array($lighthouse))
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Lighthouse categories</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($lighthouse as $key => $value)
                            <div class="rounded-lg bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
                                <p class="text-xs text-gray-400">{{ str_replace('-', ' ', ucfirst($key)) }}</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ is_numeric($value) ? $value : '—' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (is_array($mobile) && $mobile !== [])
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Mobile signals</h3>
                    <pre class="mt-3 overflow-auto rounded-lg bg-gray-50 p-4 text-xs text-gray-700 ring-1 ring-gray-100">{{ json_encode($mobile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif

            @if (count($brokenLinks) > 0)
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Broken links</h3>
                    <ul class="mt-3 space-y-1 text-xs text-gray-600">
                        @foreach ($brokenLinks as $link)
                            <li class="break-all">{{ is_array($link) ? ($link['url'] ?? json_encode($link)) : $link }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
