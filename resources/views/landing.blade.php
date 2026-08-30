<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OmniSignal &bull; Pure Signal. Zero Noise. &bull; Attribution Nirvana for Laravel & WordPress</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        .radial-zen {
            background: radial-gradient(circle at 50% 20%, rgba(16, 185, 129, 0.12) 0%, rgba(6, 182, 212, 0.05) 35%, transparent 70%);
        }
        .glow-lotus {
            box-shadow: 0 0 50px -10px rgba(16, 185, 129, 0.25);
        }
    </style>
</head>
<body class="bg-[#090D16] text-slate-200 antialiased selection:bg-emerald-500/20 selection:text-emerald-300">

    <!-- Ambient Zen Halo -->
    <div class="fixed inset-0 radial-zen pointer-events-none -z-10"></div>

    <!-- Navigation -->
    <header class="sticky top-0 z-50 backdrop-blur-md bg-[#090D16]/80 border-b border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="/" class="flex items-center space-x-3 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500/20 via-cyan-500/20 to-indigo-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-bold text-xl group-hover:border-emerald-400/60 transition shadow-lg">
                    ॐ
                </div>
                <div class="flex flex-col">
                    <span class="text-xl font-bold tracking-tight text-white flex items-center gap-1.5">
                        OmniSignal
                        <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">v2.0</span>
                    </span>
                    <span class="text-[11px] text-slate-400 font-medium tracking-wide">omnisignal.dev</span>
                </div>
            </a>

            <nav class="hidden md:flex items-center space-x-8 text-sm font-medium text-slate-300">
                <a href="#features" class="hover:text-emerald-400 transition">Channels</a>
                <a href="#calculator" class="hover:text-emerald-400 transition">ROAS Calculator</a>
                <a href="#privacy" class="hover:text-emerald-400 transition">Privacy Nirvana</a>
                <a href="#pricing" class="hover:text-emerald-400 transition">Pricing</a>
                <a href="/ad-conversions" class="hover:text-emerald-400 transition flex items-center gap-1.5 text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Live Dashboard
                </a>
            </nav>

            <div class="flex items-center space-x-4">
                <a href="#pricing" class="rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 px-5 py-2.5 text-sm font-bold shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/30 transition transform active:scale-95">
                    Attain Nirvana →
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <main>
        <section class="relative pt-20 pb-28 overflow-hidden text-center">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs font-semibold text-emerald-400 mb-8">
                    <span>🕉️ Pure Signal. Zero Noise.</span>
                    <span class="w-1 h-1 rounded-full bg-emerald-400"></span>
                    <span class="text-slate-400 font-normal">Server-Side Attribution for Laravel & WordPress</span>
                </div>

                <!-- Headline -->
                <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white leading-[1.1]">
                    Quiet the tracking chaos.<br>
                    <span class="bg-gradient-to-r from-emerald-400 via-cyan-400 to-indigo-400 bg-clip-text text-transparent">Attain Attribution Nirvana.</span>
                </h1>

                <!-- Subtitle -->
                <p class="mt-6 text-lg sm:text-xl text-slate-400 max-w-3xl mx-auto font-normal leading-relaxed">
                    Stop losing 40% of ad conversions to iOS Safari, cookie blocks, and browser noise. OmniSignal broadcasts crystal-clear offline conversion signals directly from your server to <strong class="text-white">Google Ads</strong>, <strong class="text-white">Meta CAPI</strong>, <strong class="text-white">LinkedIn</strong>, <strong class="text-white">TikTok</strong>, and <strong class="text-white">Microsoft</strong> in one line of PHP.
                </p>

                <!-- Action Buttons -->
                <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="#pricing" class="w-full sm:w-auto rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 px-8 py-4 text-base font-bold shadow-xl shadow-emerald-500/25 transition transform active:scale-95 flex items-center justify-center gap-2">
                        <span>Get OmniSignal Pro</span>
                        <span>→</span>
                    </a>
                    <a href="/ad-conversions" class="w-full sm:w-auto rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700/80 text-white px-7 py-4 text-base font-semibold shadow-sm transition flex items-center justify-center gap-2">
                        <span>Preview Local Dashboard</span>
                    </a>
                </div>

                <!-- Code Zen Preview -->
                <div class="mt-16 text-left max-w-3xl mx-auto rounded-2xl border border-slate-800 bg-[#0c121e] shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 bg-[#080d17] border-b border-slate-800/80">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
                            <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                            <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                            <span class="ml-2 text-xs font-mono text-slate-500">app/Http/Controllers/CheckoutController.php</span>
                        </div>
                        <span class="text-xs font-mono text-emerald-400/80">⚡ 1-Line Fan-Out</span>
                    </div>
                    <pre class="p-6 text-sm leading-relaxed overflow-x-auto text-slate-300 font-mono"><code><span class="text-slate-500">// Attain conversion nirvana across all ad platforms</span>
<span class="text-cyan-400">OmniSignal</span>::<span class="text-emerald-400">record</span>(
    eventName: <span class="text-amber-300">'Demo Booked'</span>,
    value: <span class="text-cyan-300">250.00</span>,
    currency: <span class="text-amber-300">'USD'</span>,
    orderId: <span class="text-amber-300">'DEMO-8092'</span>,
    user: [
        <span class="text-amber-300">'email'</span> => <span class="text-slate-400">$lead->email</span>, <span class="text-slate-500">// auto SHA-256 hashed</span>
        <span class="text-amber-300">'phone'</span> => <span class="text-slate-400">$lead->phone</span>,
    ],
);</code></pre>
                </div>
            </div>
        </section>

        <!-- Channel Logos Marquee -->
        <section class="py-10 border-y border-slate-800/60 bg-[#080c15]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <p class="text-center text-xs uppercase tracking-widest text-slate-500 font-semibold mb-6">
                    Harmonious Multi-Channel Signal Delivery
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-6 text-center">
                    <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-800 text-sm font-semibold text-slate-300 flex items-center justify-center gap-2">
                        <span class="text-emerald-400 font-bold">G</span> Google Ads & Consent v2
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-800 text-sm font-semibold text-slate-300 flex items-center justify-center gap-2">
                        <span class="text-blue-400 font-bold">∞</span> Meta CAPI (v20.0)
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-800 text-sm font-semibold text-slate-300 flex items-center justify-center gap-2">
                        <span class="text-cyan-400 font-bold">in</span> LinkedIn Conversions API
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-800 text-sm font-semibold text-slate-300 flex items-center justify-center gap-2">
                        <span class="text-rose-400 font-bold">♪</span> TikTok Events API
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-800 text-sm font-semibold text-slate-300 flex items-center justify-center gap-2">
                        <span class="text-amber-400 font-bold">⊞</span> Microsoft Ads (Bing)
                    </div>
                </div>
            </div>
        </section>

        <!-- Interactive ROAS & Signal Recovery Calculator -->
        <section id="calculator" class="py-24 relative">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">How much lost ad revenue will you recover?</h2>
                    <p class="mt-3 text-slate-400">iOS 14.5+ ATT and ad blockers silently discard ~35% of browser ad clicks.</p>
                </div>

                <div class="rounded-3xl bg-slate-900/80 border border-slate-800 p-8 sm:p-12 shadow-2xl glow-lotus">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                        <div>
                            <label for="spend-range" class="block text-sm font-semibold text-slate-300 mb-2">
                                Monthly Paid Ad Spend: <span id="spend-display" class="text-emerald-400 font-bold text-xl">$10,000 / mo</span>
                            </label>
                            <input id="spend-range" type="range" min="1000" max="100000" step="1000" value="10000"
                                class="w-full h-3 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-emerald-400">

                            <div class="flex justify-between text-xs text-slate-500 mt-2 font-mono">
                                <span>$1,000</span>
                                <span>$50,000</span>
                                <span>$100,000+</span>
                            </div>

                            <div class="mt-8 space-y-3">
                                <div class="flex items-center text-sm text-slate-300">
                                    <span class="w-5 h-5 rounded-full bg-emerald-400/20 text-emerald-400 flex items-center justify-center text-xs mr-3">✓</span>
                                    <span>Recover GCLID, GBRAID, WBRAID, and FBCLID</span>
                                </div>
                                <div class="flex items-center text-sm text-slate-300">
                                    <span class="w-5 h-5 rounded-full bg-emerald-400/20 text-emerald-400 flex items-center justify-center text-xs mr-3">✓</span>
                                    <span>Google Consent Mode v2 & GDPR auto-pruning</span>
                                </div>
                                <div class="flex items-center text-sm text-slate-300">
                                    <span class="w-5 h-5 rounded-full bg-emerald-400/20 text-emerald-400 flex items-center justify-center text-xs mr-3">✓</span>
                                    <span>Direct server upload bypasses all ad blockers</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-[#090d16] p-8 border border-emerald-500/20 text-center">
                            <p class="text-xs uppercase font-mono tracking-widest text-slate-400">Estimated Monthly Value Recovered</p>
                            <p id="recovered-display" class="text-4xl sm:text-5xl font-extrabold text-transparent bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text mt-3">
                                $3,500 / mo
                            </p>
                            <p class="text-xs text-slate-500 mt-3">
                                Based on average 35% tracking signal restoration & algorithm optimization.
                            </p>
                            <div class="mt-6 pt-6 border-t border-slate-800">
                                <span class="text-xs text-emerald-400 font-semibold">ROI: 35x in the first 30 days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-24 border-t border-slate-800/80 bg-[#080d16]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Serene, transparent pricing.</h2>
                <p class="mt-3 text-slate-400 max-w-xl mx-auto">One investment. Infinite attribution peace of mind. Sold via LemonSqueezy with instant Composer access.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-16 text-left">
                    <!-- Starter -->
                    <div class="rounded-3xl bg-slate-900/60 p-8 border border-slate-800 hover:border-slate-700 transition flex flex-col justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-white">Starter</h3>
                            <p class="text-xs text-slate-400 mt-1">For single Laravel or WooCommerce stores</p>
                            <div class="mt-6 flex items-baseline gap-1">
                                <span class="text-4xl font-extrabold text-white">$99</span>
                                <span class="text-sm text-slate-400 font-medium">/ year</span>
                            </div>

                            <ul class="mt-8 space-y-3.5 text-sm text-slate-300">
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> 1 Production Domain</li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> All 5 Ad Channels (Google, Meta, etc.)</li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> In-App Embedded Analytics Dashboard</li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> 1 Year of Updates & Security Fixes</li>
                            </ul>
                        </div>
                        <a href="https://omnisignal.dev" class="mt-8 block w-full rounded-xl bg-slate-800 hover:bg-slate-700 text-center py-3 text-sm font-semibold text-white transition">
                            Choose Starter
                        </a>
                    </div>

                    <!-- Pro (Featured) -->
                    <div class="rounded-3xl bg-slate-900 p-8 border-2 border-emerald-500 relative shadow-2xl flex flex-col justify-between glow-lotus">
                        <div class="absolute -top-4 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-emerald-500 text-slate-950 font-bold text-xs uppercase tracking-wider">
                            Most Serene Choice
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Pro Developer</h3>
                            <p class="text-xs text-slate-400 mt-1">For indie creators and multi-project teams</p>
                            <div class="mt-6 flex items-baseline gap-1">
                                <span class="text-4xl font-extrabold text-emerald-400">$199</span>
                                <span class="text-sm text-slate-400 font-medium">/ year</span>
                            </div>

                            <ul class="mt-8 space-y-3.5 text-sm text-slate-300">
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> <strong>5 Production Domains</strong></li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> Laravel Package + WordPress Plugin</li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> Enhanced Conversions (SHA-256 Hashing)</li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> Google Consent Mode v2 & GDPR Pruning</li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> Priority Support</li>
                            </ul>
                        </div>
                        <a href="https://omnisignal.dev" class="mt-8 block w-full rounded-xl bg-emerald-500 hover:bg-emerald-400 text-center py-3 text-sm font-bold text-slate-950 transition shadow-lg shadow-emerald-500/20">
                            Choose Pro
                        </a>
                    </div>

                    <!-- Agency -->
                    <div class="rounded-3xl bg-slate-900/60 p-8 border border-slate-800 hover:border-slate-700 transition flex flex-col justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-white">Agency Zen</h3>
                            <p class="text-xs text-slate-400 mt-1">For digital marketing & dev agencies</p>
                            <div class="mt-6 flex items-baseline gap-1">
                                <span class="text-4xl font-extrabold text-white">$399</span>
                                <span class="text-sm text-slate-400 font-medium">/ year</span>
                            </div>

                            <ul class="mt-8 space-y-3.5 text-sm text-slate-300">
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> <strong>Unlimited Client Websites</strong></li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> Central Agency Reporting Portal</li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> Multi-Client Tenant Separation</li>
                                <li class="flex items-center gap-3"><span class="text-emerald-400">✓</span> 1-on-1 Onboarding Assistance</li>
                            </ul>
                        </div>
                        <a href="https://omnisignal.dev" class="mt-8 block w-full rounded-xl bg-slate-800 hover:bg-slate-700 text-center py-3 text-sm font-semibold text-white transition">
                            Choose Agency
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="py-12 border-t border-slate-800/60 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <span class="text-emerald-400">🕉️</span>
                <span class="font-bold text-slate-300">OmniSignal</span>
                <span>&bull; Pure Signal. Zero Noise.</span>
            </div>
            <p>&copy; 2026 OmniSignal (<a href="https://omnisignal.dev" class="text-slate-400 hover:underline">omnisignal.dev</a>). All rights reserved.</p>
        </div>
    </footer>

    <!-- Interactive Calculator JS -->
    <script>
        const range = document.getElementById('spend-range');
        const spendDisplay = document.getElementById('spend-display');
        const recoveredDisplay = document.getElementById('recovered-display');

        range.addEventListener('input', (e) => {
            const spend = parseInt(e.target.value);
            spendDisplay.textContent = '$' + spend.toLocaleString() + ' / mo';
            const recovered = Math.round(spend * 0.35);
            recoveredDisplay.textContent = '$' + recovered.toLocaleString() + ' / mo';
        });
    </script>
</body>
</html>
