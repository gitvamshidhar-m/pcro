<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">Inspected pages</h2>
            <a href="{{ route('analyses.dashboard', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-6 py-3">Page</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Words</th>
                                <th class="px-4 py-3">Depth</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($pages as $page)
                                <tr class="hover:bg-gray-50">
                                    <td class="max-w-md px-6 py-3">
                                        <p class="truncate font-medium text-gray-900">{{ $page->title ?: $page->path }}</p>
                                        <p class="truncate text-xs text-gray-500">{{ $page->url }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $page->page_type->label() }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $page->status_code ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ number_format($page->word_count) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $page->depth }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <a href="{{ route('analyses.page', [$crawl, $page]) }}" class="text-indigo-600 hover:text-indigo-800">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
