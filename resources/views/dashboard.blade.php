<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OmniSignal &bull; Conversion Analytics Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans antialiased">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between pb-8 border-b border-slate-800">
            <div>
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">⚡</span>
                    <h1 class="text-2xl font-bold tracking-tight text-white">OmniSignal</h1>
                    <span class="inline-flex items-center rounded-md bg-emerald-400/10 px-2 py-1 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-400/20">v2.0 Active</span>
                </div>
                <p class="mt-1 text-sm text-slate-400">Server-side offline conversion tracking & delivery analytics &bull; <a href="https://omnisignal.dev" target="_blank" class="text-indigo-400 hover:underline">omnisignal.dev</a></p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-3">
                <a href="{{ url()->current() }}" class="inline-flex items-center rounded-lg bg-slate-800 px-3.5 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-700 shadow-sm transition">
                    ↻ Refresh
                </a>
            </div>
        </div>

        <!-- Metric Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
            <!-- Card 1: Total Leads -->
            <div class="rounded-xl bg-slate-900/80 p-6 border border-slate-800 shadow-lg">
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tracked Visitors / Leads</dt>
                <dd class="mt-3 text-3xl font-extrabold text-white">{{ number_format($totalLeads) }}</dd>
                <p class="mt-2 text-xs text-slate-500">Total attributed clicks buffered & synced</p>
            </div>

            <!-- Card 2: Uploaded Conversions -->
            <div class="rounded-xl bg-slate-900/80 p-6 border border-slate-800 shadow-lg">
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Conversions Uploaded</dt>
                <dd class="mt-3 text-3xl font-extrabold text-emerald-400">{{ number_format($totalUploaded) }}</dd>
                <div class="mt-2 flex items-center space-x-2 text-xs text-slate-400">
                    <span>Pending: <strong class="text-amber-400">{{ $totalPending }}</strong></span>
                    <span>&bull;</span>
                    <span>Failed: <strong class="text-rose-400">{{ $totalFailed }}</strong></span>
                </div>
            </div>

            <!-- Card 3: Total Value -->
            <div class="rounded-xl bg-slate-900/80 p-6 border border-slate-800 shadow-lg">
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Attributed Revenue</dt>
                <dd class="mt-3 text-3xl font-extrabold text-cyan-400">{{ $defaultCurrency }} {{ number_format($totalValue, 2) }}</dd>
                <p class="mt-2 text-xs text-slate-500">Across all offline converted events</p>
            </div>

            <!-- Card 4: Match Quality -->
            <div class="rounded-xl bg-slate-900/80 p-6 border border-slate-800 shadow-lg">
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Attribution Match Rate</dt>
                <dd class="mt-3 text-3xl font-extrabold text-indigo-400">{{ $matchRate }}%</dd>
                <p class="mt-2 text-xs text-slate-500">Leads with verified click ID</p>
            </div>
        </div>

        <!-- Channels Status Section -->
        <div class="mt-10">
            <h2 class="text-lg font-bold text-white mb-4">Ad Network Channels</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                @foreach ($channels as $key => $ch)
                    <div class="rounded-xl bg-slate-900/60 p-4 border border-slate-800 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $ch['name'] }}</p>
                            <p class="text-xs text-slate-400">{{ $ch['status'] }}</p>
                        </div>
                        <span class="h-3 w-3 rounded-full {{ $ch['configured'] ? 'bg-emerald-500 animate-pulse' : 'bg-slate-600' }}"></span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Recent Conversions Table -->
        <div class="mt-10">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-white">Recent Conversions Stream</h2>
                <span class="text-xs text-slate-400">Last {{ count($recentConversions) }} events</span>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80 shadow-xl">
                @if (empty($recentConversions))
                    <div class="py-12 text-center">
                        <span class="text-4xl">📊</span>
                        <p class="mt-3 text-sm font-medium text-slate-400">No conversions recorded yet</p>
                        <p class="text-xs text-slate-600 mt-1">Conversions will appear here automatically when recorded.</p>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-slate-800">
                        <thead class="bg-slate-950/50">
                            <tr>
                                <th class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold text-slate-400 sm:pl-6">Event Name</th>
                                <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-400">Value</th>
                                <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-400">Click Identifier</th>
                                <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-400">Order ID</th>
                                <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-400">Status</th>
                                <th class="px-3 py-3.5 text-left text-xs font-semibold text-slate-400">Recorded</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 bg-transparent">
                            @foreach ($recentConversions as $conv)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="whitespace-nowrap py-3.5 pl-4 pr-3 text-sm font-medium text-white sm:pl-6">
                                        {{ $conv['event'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-sm text-cyan-400 font-mono">
                                        @if ($conv['value'] > 0)
                                            {{ $conv['currency'] }} {{ number_format($conv['value'], 2) }}
                                        @else
                                            <span class="text-slate-500">-</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-400 font-mono">
                                        {{ Str::limit($conv['click_id'], 20) }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-300">
                                        {{ $conv['order_id'] ?? '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-xs">
                                        @if ($conv['status'] === 'uploaded')
                                            <span class="inline-flex items-center rounded-full bg-emerald-400/10 px-2 py-0.5 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-400/20">Uploaded</span>
                                        @elseif ($conv['status'] === 'failed')
                                            <span class="inline-flex items-center rounded-full bg-rose-400/10 px-2 py-0.5 text-xs font-medium text-rose-400 ring-1 ring-inset ring-rose-400/20">Failed</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-400/10 px-2 py-0.5 text-xs font-medium text-amber-400 ring-1 ring-inset ring-amber-400/20">Pending</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-500">
                                        {{ date('M j, Y H:i:s', $conv['timestamp']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
