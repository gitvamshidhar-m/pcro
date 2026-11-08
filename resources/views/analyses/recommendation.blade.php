<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">{{ $recommendation->title }}</h2>
            <a href="{{ route('analyses.recommendations', $crawl) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $recommendation->priority->badgeClasses() }}">
                        {{ $recommendation->priority->label() }} priority
                    </span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-200">
                        {{ $recommendation->source === 'ai' ? 'AI-generated' : 'Heuristic' }}
                    </span>
                </div>

                <p class="mt-4 text-sm text-gray-700">{{ $recommendation->description }}</p>

                <h3 class="mt-6 text-sm font-semibold text-gray-900">Why this matters</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $recommendation->rationale }}</p>

                @if (is_array($recommendation->action_steps) && $recommendation->action_steps !== [])
                    <h3 class="mt-6 text-sm font-semibold text-gray-900">Action steps</h3>
                    <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm text-gray-600">
                        @foreach ($recommendation->action_steps as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                @endif
            </div>

            @if (is_array($recommendation->ab_test))
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">A/B test plan</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="font-medium text-gray-900">Hypothesis</dt>
                            <dd class="text-gray-600">{{ $recommendation->ab_test['hypothesis'] ?? '' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-900">Primary metric</dt>
                            <dd class="text-gray-600">{{ $recommendation->ab_test['primary_metric'] ?? '' }}</dd>
                        </div>
                        @if (! empty($recommendation->ab_test['secondary_metrics']))
                            <div>
                                <dt class="font-medium text-gray-900">Secondary metrics</dt>
                                <dd class="text-gray-600">{{ implode(', ', $recommendation->ab_test['secondary_metrics']) }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="font-medium text-gray-900">Minimum runtime</dt>
                            <dd class="text-gray-600">{{ $recommendation->ab_test['minimum_runtime'] ?? '' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-900">Note</dt>
                            <dd class="text-gray-600">{{ $recommendation->ab_test['note'] ?? '' }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            @if (is_array($recommendation->evidence_refs) && $recommendation->evidence_refs !== [])
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Evidence references</h3>
                    <ul class="mt-3 space-y-1 text-xs text-gray-600">
                        @foreach ($recommendation->evidence_refs as $ref)
                            <li class="break-all">{{ $ref }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
