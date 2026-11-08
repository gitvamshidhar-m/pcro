<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 leading-tight">Analysis history</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-6 py-3">Website</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">CRO score</th>
                                <th class="px-4 py-3">Pages</th>
                                <th class="px-4 py-3">Completed</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($crawls as $crawl)
                                <tr class="hover:bg-gray-50">
                                    <td class="max-w-md px-6 py-3">
                                        <p class="truncate font-medium text-gray-900">{{ $crawl->project?->website?->domain ?? 'Website' }}</p>
                                        <p class="truncate text-xs text-gray-500">{{ data_get($crawl->settings, 'target_url') }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $crawl->status->badgeClasses() }}">
                                            {{ $crawl->status->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $crawl->cro_score !== null ? number_format((float) $crawl->cro_score, 0) : '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $crawl->pages_crawled }}/{{ $crawl->pages_requested }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $crawl->completed_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if ($crawl->status === \App\Enums\CrawlStatus::Completed)
                                            <a href="{{ route('analyses.dashboard', $crawl) }}" class="text-indigo-600 hover:text-indigo-800">Open</a>
                                        @else
                                            <a href="{{ route('analyses.show', $crawl) }}" class="text-indigo-600 hover:text-indigo-800">Progress</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-100 px-6 py-4">
                    {{ $crawls->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
