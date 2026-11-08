<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">{{ $page->title ?: $page->path }}</h2>
            <a href="{{ route('analyses.pages', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back to pages</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs text-gray-400">Type</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ $page->page_type->label() }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs text-gray-400">Status</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ $page->status_code ?? '—' }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs text-gray-400">Words</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ number_format($page->word_count) }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs text-gray-400">Depth</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ $page->depth }}</p>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs text-gray-400">URL</p>
                <p class="mt-1 break-all text-sm text-gray-700">{{ $page->url }}</p>
                @if ($page->meta_description)
                    <p class="mt-4 text-xs text-gray-400">Meta description</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $page->meta_description }}</p>
                @endif
                @if ($page->h1)
                    <p class="mt-4 text-xs text-gray-400">H1</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $page->h1 }}</p>
                @endif
            </div>

            @if ($page->screenshot_path)
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Screenshot</h3>
                    <p class="mt-2 text-xs text-gray-500">Stored at {{ $page->screenshot_path }}</p>
                </div>
            @endif

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h3 class="text-base font-semibold text-gray-900">Extracted elements ({{ $page->elements->count() }})</h3>
                <div class="mt-4 space-y-2">
                    @foreach ($page->elements->take(40) as $element)
                        <div class="flex items-start justify-between gap-4 rounded-lg bg-gray-50 px-3 py-2 text-xs ring-1 ring-gray-100">
                            <span class="font-medium text-gray-700">{{ $element->type }}</span>
                            <span class="truncate text-gray-500">{{ $element->text }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($page->metrics)
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Metrics</h3>
                    <pre class="mt-3 overflow-auto rounded-lg bg-gray-50 p-4 text-xs text-gray-700 ring-1 ring-gray-100">{{ json_encode($page->metrics->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
