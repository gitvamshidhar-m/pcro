<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">Category audit</h2>
            <a href="{{ route('analyses.dashboard', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('analyses.categories', $crawl) }}" class="rounded-full px-3 py-1.5 text-xs font-medium ring-1 {{ $selected === null ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-200' }}">
                    All
                </a>
                @foreach (\App\Enums\AuditCategory::all() as $category)
                    <a href="{{ route('analyses.categories', [$crawl, $category->value]) }}"
                       class="rounded-full px-3 py-1.5 text-xs font-medium ring-1 {{ $selected?->value === $category->value ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-200' }}">
                        {{ $category->label() }}
                    </a>
                @endforeach
            </div>

            @foreach (\App\Enums\AuditCategory::all() as $category)
                @if ($selected !== null && $selected !== $category)
                    @continue
                @endif
                @php $score = $scores->get($category->value); @endphp
                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ $category->label() }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ $category->description() }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-semibold text-gray-900">{{ $score?->score !== null ? number_format((float) $score->score, 0) : '—' }}</p>
                            <p class="text-xs text-gray-400">weight {{ number_format($category->value === 'ux' ? 0.20 : ($category->value === 'conversion' ? 0.25 : ($category->value === 'trust' ? 0.15 : ($category->value === 'technical' ? 0.15 : 0.25))), 2) }}</p>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($score?->components ?? [] as $component)
                            <div class="flex items-start justify-between gap-4 px-6 py-4">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900">{{ $component['title'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $component['rule_key'] }}</p>
                                    @if (! empty($component['observed']))
                                        <p class="mt-1 text-xs text-gray-600">{{ $component['observed'] }}</p>
                                    @endif
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $component['status'] === 'pass' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : ($component['status'] === 'warning' ? 'bg-amber-50 text-amber-700 ring-amber-200' : ($component['status'] === 'fail' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-slate-100 text-slate-700 ring-slate-200')) }}">
                                        {{ ucfirst($component['status']) }}
                                    </span>
                                    <p class="mt-1 text-xs text-gray-400">{{ $component['score'] !== null ? number_format((float) $component['score'], 0) : 'N/A' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
