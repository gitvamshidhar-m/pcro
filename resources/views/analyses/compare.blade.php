<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">Historical comparison</h2>
            <a href="{{ route('analyses.dashboard', $comparison['base']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    'CRO score' => $comparison['cro_score'],
                    'Friction index' => $comparison['friction_score'],
                ] as $label => $data)
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $label }}</p>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div>
                                <p class="text-xs text-gray-400">Base</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $data['base'] !== null ? number_format((float) $data['base'], 0) : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">New</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $data['comparison'] !== null ? number_format((float) $data['comparison'], 0) : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Delta</p>
                                <p class="text-lg font-semibold {{ ($data['delta'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $data['delta'] !== null ? (($data['delta'] >= 0 ? '+' : '').number_format((float) $data['delta'], 0)) : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Findings / opportunities</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                        <div>
                            <p class="text-xs text-gray-400">Findings delta</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $comparison['findings']['delta'] >= 0 ? '+' : '' }}{{ $comparison['findings']['delta'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Opportunities delta</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $comparison['opportunities']['delta'] >= 0 ? '+' : '' }}{{ $comparison['opportunities']['delta'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-100 px-6 py-5">
                    <h3 class="text-base font-semibold text-gray-900">Category comparison</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-6 py-3">Category</th>
                                <th class="px-4 py-3">Base</th>
                                <th class="px-4 py-3">New</th>
                                <th class="px-4 py-3">Delta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($comparison['categories'] as $category)
                                <tr>
                                    <td class="px-6 py-3 font-medium text-gray-900">{{ $category['label'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $category['base'] !== null ? number_format((float) $category['base'], 1) : '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $category['comparison'] !== null ? number_format((float) $category['comparison'], 1) : '—' }}</td>
                                    <td class="px-4 py-3 font-semibold {{ ($category['delta'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ $category['delta'] !== null ? (($category['delta'] >= 0 ? '+' : '').number_format((float) $category['delta'], 1)) : '—' }}
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
