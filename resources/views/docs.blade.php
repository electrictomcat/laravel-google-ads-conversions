<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation & Knowledge Base &bull; OmniSignal (omnisignal.dev)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        .radial-zen {
            background: radial-gradient(circle at 50% 10%, rgba(16, 185, 129, 0.08) 0%, rgba(6, 182, 212, 0.03) 30%, transparent 65%);
        }
    </style>
</head>
<body class="bg-[#090D16] text-slate-200 antialiased selection:bg-emerald-500/20 selection:text-emerald-300">

    <div class="fixed inset-0 radial-zen pointer-events-none -z-10"></div>

    <!-- Navigation Header -->
    <header class="sticky top-0 z-50 backdrop-blur-md bg-[#090D16]/90 border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-6">
                <a href="/" class="flex items-center space-x-3 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500/20 via-cyan-500/20 to-indigo-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-bold text-lg group-hover:border-emerald-400/60 transition shadow-lg">
                        ॐ
                    </div>
                    <div class="flex flex-col">
                        <span class="text-lg font-bold tracking-tight text-white flex items-center gap-1.5">
                            OmniSignal
                            <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Docs</span>
                        </span>
                    </div>
                </a>

                <div class="hidden sm:block h-5 w-px bg-slate-800"></div>
                <span class="hidden sm:inline-block text-xs text-slate-400 font-medium">Attribution Nirvana Knowledge Base</span>
            </div>

            <div class="flex items-center space-x-4">
                <a href="/" class="text-sm text-slate-400 hover:text-white transition">Home</a>
                <a href="/dashboard" class="text-sm text-slate-400 hover:text-white transition flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    Live Dashboard
                </a>
                <a href="/#pricing" class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 px-3.5 py-1.5 text-xs font-bold transition">
                    Get License
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex flex-col lg:flex-row gap-10">
        
        <!-- Sidebar Navigation -->
        <aside class="w-full lg:w-64 shrink-0">
            <div class="sticky top-24 space-y-8 text-sm">
                <!-- Section 1 -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400 font-mono">Getting Started</h3>
                    <ul class="mt-3 space-y-2 border-l border-slate-800 pl-3">
                        <li><a href="#quickstart" class="block text-slate-400 hover:text-white transition">Quickstart & Installation</a></li>
                        <li><a href="#architecture" class="block text-slate-400 hover:text-white transition">Architecture & Flow</a></li>
                        <li><a href="#recording" class="block text-slate-400 hover:text-white transition">Recording Conversions</a></li>
                    </ul>
                </div>

                <!-- Section 2 -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-cyan-400 font-mono">Ad Platform Setup</h3>
                    <ul class="mt-3 space-y-2 border-l border-slate-800 pl-3">
                        <li><a href="#google-ads" class="block text-slate-400 hover:text-white transition">Google Ads & Consent v2</a></li>
                        <li><a href="#meta-capi" class="block text-slate-400 hover:text-white transition">Meta Conversions API (CAPI)</a></li>
                        <li><a href="#tiktok" class="block text-slate-400 hover:text-white transition">TikTok Events API</a></li>
                        <li><a href="#linkedin" class="block text-slate-400 hover:text-white transition">LinkedIn Conversions API</a></li>
                        <li><a href="#microsoft" class="block text-slate-400 hover:text-white transition">Microsoft (Bing) Ads</a></li>
                    </ul>
                </div>

                <!-- Section 3 -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-400 font-mono">Privacy & GDPR</h3>
                    <ul class="mt-3 space-y-2 border-l border-slate-800 pl-3">
                        <li><a href="#consent-gating" class="block text-slate-400 hover:text-white transition">Cookie Consent Gating</a></li>
                        <li><a href="#pruning" class="block text-slate-400 hover:text-white transition">Automated 90-Day Pruning</a></li>
                        <li><a href="#enhanced" class="block text-slate-400 hover:text-white transition">Enhanced Conversions (Hashing)</a></li>
                    </ul>
                </div>

                <!-- Section 4 -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 font-mono">Developer Reference</h3>
                    <ul class="mt-3 space-y-2 border-l border-slate-800 pl-3">
                        <li><a href="#testing-fake" class="block text-slate-400 hover:text-white transition">Testing Fake (`fake()`)</a></li>
                        <li><a href="#blade" class="block text-slate-400 hover:text-white transition">Blade Form Directives</a></li>
                        <li><a href="#cli" class="block text-slate-400 hover:text-white transition">Artisan CLI Commands</a></li>
                        <li><a href="#woocommerce" class="block text-slate-400 hover:text-white transition">WooCommerce Plugin</a></li>
                    </ul>
                </div>
            </div>
        </aside>

        <!-- Main Content Documentation -->
        <div class="flex-1 min-w-0 max-w-4xl space-y-16">
            
            <!-- Quickstart -->
            <section id="quickstart" class="scroll-mt-28">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-xs font-medium text-emerald-400 mb-4 border border-emerald-500/20">
                    <span>⚡ Quickstart</span>
                </div>
                <h1 class="text-3xl font-extrabold text-white">Installation & Setup</h1>
                <p class="mt-3 text-slate-400 text-base leading-relaxed">
                    OmniSignal provides seamless server-side offline conversion tracking for Laravel 11, 12, and 13. It captures landing click identifiers (<code class="text-emerald-300">gclid</code>, <code class="text-emerald-300">gbraid</code>, <code class="text-emerald-300">wbraid</code>, <code class="text-emerald-300">fbclid</code>, <code class="text-emerald-300">ttclid</code>, <code class="text-emerald-300">msclkid</code>, <code class="text-emerald-300">li_fat_id</code>) and transmits offline conversions directly from your backend.
                </p>

                <div class="mt-6 rounded-2xl bg-[#0c121e] border border-slate-800 overflow-hidden">
                    <div class="px-4 py-2 bg-[#070b12] border-b border-slate-800 flex items-center justify-between">
                        <span class="text-xs font-mono text-slate-400">Step 1: Install via Composer</span>
                    </div>
                    <pre class="p-4 text-sm text-emerald-300 font-mono"><code>composer require electrictomcat/laravel-google-ads-conversions</code></pre>
                </div>

                <div class="mt-6 rounded-2xl bg-[#0c121e] border border-slate-800 overflow-hidden">
                    <div class="px-4 py-2 bg-[#070b12] border-b border-slate-800 flex items-center justify-between">
                        <span class="text-xs font-mono text-slate-400">Step 2: Publish Config & Migrations</span>
                    </div>
                    <pre class="p-4 text-sm text-slate-300 font-mono"><code>php artisan vendor:publish --tag="laravel-google-ads-conversions-config"
php artisan vendor:publish --tag="laravel-google-ads-conversions-migrations"
php artisan migrate</code></pre>
                </div>

                <div class="mt-6 rounded-2xl bg-[#0c121e] border border-slate-800 overflow-hidden">
                    <div class="px-4 py-2 bg-[#070b12] border-b border-slate-800 flex items-center justify-between">
                        <span class="text-xs font-mono text-slate-400">Step 3: Register Middleware in bootstrap/app.php</span>
                    </div>
                    <pre class="p-4 text-sm text-slate-300 font-mono"><code><span class="text-slate-500">// bootstrap/app.php</span>
-><span class="text-cyan-400">withMiddleware</span>(<span class="text-emerald-400">function</span> (Middleware <span class="text-amber-300">$middleware</span>) {
    <span class="text-amber-300">$middleware</span>-><span class="text-cyan-400">web</span>(append: [
        \App\OmniSignal\Http\Middleware\CaptureGclid::<span class="text-emerald-400">class</span>,
    ]);
})</code></pre>
                </div>
            </section>

            <!-- Architecture & Flow -->
            <section id="architecture" class="scroll-mt-28 pt-8 border-t border-slate-800/80">
                <h2 class="text-2xl font-bold text-white">Architecture & Signal Flow</h2>
                <p class="mt-3 text-slate-400 text-sm leading-relaxed">
                    OmniSignal decouples client tracking from immediate API latency using a high-throughput 3-tier architecture:
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                    <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold mb-3">1</div>
                        <h4 class="text-sm font-bold text-white">Capture & Buffer</h4>
                        <p class="text-xs text-slate-400 mt-1">Middleware intercepts landing click parameters and buffers visitor tracking in high-speed cache with 0ms DB overhead.</p>
                    </div>
                    <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800">
                        <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center font-bold mb-3">2</div>
                        <h4 class="text-sm font-bold text-white">Sync & Deduplicate</h4>
                        <p class="text-xs text-slate-400 mt-1">Buffered conversions are flushed to PostgreSQL/MySQL and deduplicated via transaction/order identifiers.</p>
                    </div>
                    <div class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800">
                        <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold mb-3">3</div>
                        <h4 class="text-sm font-bold text-white">Batched CAPI Fan-Out</h4>
                        <p class="text-xs text-slate-400 mt-1">Queued workers aggregate up to 2,000 conversions per batch and dispatch directly to Google, Meta, TikTok, etc.</p>
                    </div>
                </div>
            </section>

            <!-- Recording Conversions -->
            <section id="recording" class="scroll-mt-28 pt-8 border-t border-slate-800/80">
                <h2 class="text-2xl font-bold text-white">Recording Offline Conversions</h2>
                <p class="mt-3 text-slate-400 text-sm leading-relaxed">
                    Call <code class="text-emerald-300">OmniSignal::record()</code> anywhere in your application (Controllers, Livewire, Observers, Stripe/LemonSqueezy webhooks):
                </p>

                <div class="mt-6 rounded-2xl bg-[#0c121e] border border-slate-800 overflow-hidden">
                    <pre class="p-5 text-sm text-slate-300 font-mono leading-relaxed"><code><span class="text-emerald-400">use</span> App\OmniSignal\Facades\OmniSignal;

<span class="text-cyan-400">OmniSignal</span>::<span class="text-emerald-400">record</span>(
    eventName: <span class="text-amber-300">'Demo Booked'</span>,
    value: <span class="text-cyan-300">250.00</span>,                      <span class="text-slate-500">// Conversion value</span>
    currency: <span class="text-amber-300">'USD'</span>,                     <span class="text-slate-500">// ISO currency</span>
    orderId: <span class="text-amber-300">'ORD-2026-981'</span>,               <span class="text-slate-500">// Transaction deduplication ID</span>
    user: [                              <span class="text-slate-500">// First-party data (auto SHA-256 hashed)</span>
        <span class="text-amber-300">'email'</span> => <span class="text-slate-400">$user->email</span>,
        <span class="text-amber-300">'phone'</span> => <span class="text-slate-400">$user->phone</span>,
    ],
    consent: [                           <span class="text-slate-500">// Google Consent Mode v2</span>
        <span class="text-amber-300">'ad_user_data'</span> => <span class="text-amber-300">'GRANTED'</span>,
        <span class="text-amber-300">'ad_personalization'</span> => <span class="text-amber-300">'GRANTED'</span>,
    ],
);</code></pre>
                </div>
            </section>

            <!-- Google Ads Setup -->
            <section id="google-ads" class="scroll-mt-28 pt-8 border-t border-slate-800/80">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-xs font-medium text-emerald-400 mb-3 border border-emerald-500/20">
                    <span>🟢 Google Ads</span>
                </div>
                <h2 class="text-2xl font-bold text-white">Google Ads Offline Conversions & Consent Mode v2</h2>
                <p class="mt-3 text-slate-400 text-sm leading-relaxed">
                    Set up your Google Ads credentials in <code class="text-emerald-300">.env</code>. Supports direct customer accounts and Manager Accounts (MCC):
                </p>

                <div class="mt-4 rounded-2xl bg-[#0c121e] border border-slate-800 p-5 font-mono text-xs text-slate-300 space-y-1">
                    <div><span class="text-cyan-400">GOOGLE_ADS_DEVELOPER_TOKEN</span>=your_developer_token</div>
                    <div><span class="text-cyan-400">GOOGLE_ADS_CLIENT_ID</span>=your_client_id.apps.googleusercontent.com</div>
                    <div><span class="text-cyan-400">GOOGLE_ADS_CLIENT_SECRET</span>=your_client_secret</div>
                    <div><span class="text-cyan-400">GOOGLE_ADS_REFRESH_TOKEN</span>=1//0g...</div>
                    <div><span class="text-cyan-400">GOOGLE_ADS_CUSTOMER_ID</span>=1234567890</div>
                    <div><span class="text-cyan-400">GOOGLE_ADS_LOGIN_CUSTOMER_ID</span>=  <span class="text-slate-500"># Optional: MCC ID</span></div>
                </div>

                <div class="mt-6 p-4 rounded-xl bg-slate-900/60 border border-slate-800 text-xs text-slate-300 leading-relaxed">
                    <strong class="text-emerald-400 font-semibold">Consent Mode v2 Integration:</strong>
                    OmniSignal automatically attaches <code class="text-emerald-300">ad_user_data</code> and <code class="text-emerald-300">ad_personalization</code> signals to Google Ads API <code class="text-emerald-300">ClickConversion</code> payloads to comply with the European Digital Markets Act (DMA).
                </div>
            </section>

            <!-- Meta CAPI Setup -->
            <section id="meta-capi" class="scroll-mt-28 pt-8 border-t border-slate-800/80">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 text-xs font-medium text-blue-400 mb-3 border border-blue-500/20">
                    <span>🔵 Meta CAPI</span>
                </div>
                <h2 class="text-2xl font-bold text-white">Meta (Facebook & Instagram) Conversions API</h2>
                <p class="mt-3 text-slate-400 text-sm leading-relaxed">
                    Connect Meta Conversions API (Graph API v20.0) with automated SHA-256 normalization, <code class="text-emerald-300">_fbc</code>/<code class="text-emerald-300">_fbp</code> cookie matching, and browser/server event deduplication via <code class="text-emerald-300">event_id</code>:
                </p>

                <div class="mt-4 rounded-2xl bg-[#0c121e] border border-slate-800 p-5 font-mono text-xs text-slate-300 space-y-1">
                    <div><span class="text-cyan-400">META_PIXEL_ID</span>=1234567890123456</div>
                    <div><span class="text-cyan-400">META_ACCESS_TOKEN</span>=EAAG...system_user_token</div>
                    <div><span class="text-cyan-400">META_TEST_EVENT_CODE</span>=TEST12345  <span class="text-slate-500"># Optional: for live test events</span></div>
                </div>
            </section>

            <!-- Testing Fake -->
            <section id="testing-fake" class="scroll-mt-28 pt-8 border-t border-slate-800/80">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 text-xs font-medium text-indigo-400 mb-3 border border-indigo-500/20">
                    <span>🧪 Testing Fake</span>
                </div>
                <h2 class="text-2xl font-bold text-white">Application Testing with `OmniSignal::fake()`</h2>
                <p class="mt-3 text-slate-400 text-sm leading-relaxed">
                    Write tests for your application without hitting real databases, caches, or external advertising APIs:
                </p>

                <div class="mt-6 rounded-2xl bg-[#0c121e] border border-slate-800 overflow-hidden">
                    <pre class="p-5 text-sm text-slate-300 font-mono leading-relaxed"><code><span class="text-emerald-400">use</span> App\OmniSignal\Facades\OmniSignal;

<span class="text-emerald-400">public function</span> <span class="text-cyan-400">test_booking_records_offline_conversion</span>()
{
    <span class="text-cyan-400">OmniSignal</span>::<span class="text-emerald-400">fake</span>();

    <span class="text-amber-300">$this</span>-><span class="text-cyan-400">post</span>(<span class="text-amber-300">'/book-demo'</span>, [<span class="text-amber-300">'name'</span> => <span class="text-amber-300">'Alice'</span>]);

    <span class="text-slate-500">// Assert conversion was recorded</span>
    <span class="text-cyan-400">OmniSignal</span>::<span class="text-emerald-400">assertRecorded</span>(<span class="text-amber-300">'Demo Booked'</span>, <span class="text-cyan-300">250.0</span>);
    <span class="text-cyan-400">OmniSignal</span>::<span class="text-emerald-400">assertNotRecorded</span>(<span class="text-amber-300">'Unrelated Event'</span>);
    <span class="text-cyan-400">OmniSignal</span>::<span class="text-emerald-400">assertRecordedCount</span>(<span class="text-cyan-300">1</span>);
}</code></pre>
                </div>
            </section>

            <!-- Blade Directives -->
            <section id="blade" class="scroll-mt-28 pt-8 border-t border-slate-800/80">
                <h2 class="text-2xl font-bold text-white">Blade Form Directives</h2>
                <p class="mt-3 text-slate-400 text-sm leading-relaxed">
                    Inject hidden input fields with active click identifiers into your HTML/Blade contact forms:
                </p>

                <div class="mt-6 rounded-2xl bg-[#0c121e] border border-slate-800 p-5 font-mono text-sm text-slate-300 leading-relaxed">
                    &lt;form action="/lead" method="POST"&gt;<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;@csrf<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;<span class="text-emerald-400">@googleAdsClickInputs</span><br><br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&lt;input type="text" name="name" required&gt;<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&lt;button type="submit"&gt;Submit&lt;/button&gt;<br>
                    &lt;/form&gt;
                </div>
            </section>

            <!-- CLI Commands -->
            <section id="cli" class="scroll-mt-28 pt-8 border-t border-slate-800/80">
                <h2 class="text-2xl font-bold text-white">Artisan CLI Commands</h2>
                <div class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-950/60 text-slate-400 font-mono text-xs">
                            <tr>
                                <th class="py-3 pl-4 text-left">Command</th>
                                <th class="py-3 pr-4 text-left">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-300 font-mono text-xs">
                            <tr>
                                <td class="py-3 pl-4 text-emerald-400 font-bold">php artisan ad-conversions:install</td>
                                <td class="py-3 pr-4 font-sans text-slate-300">Interactive setup wizard for all 5 ad channels.</td>
                            </tr>
                            <tr>
                                <td class="py-3 pl-4 text-emerald-400 font-bold">php artisan google-ads:upload</td>
                                <td class="py-3 pr-4 font-sans text-slate-300">Uploads pending offline conversions (supports <code class="text-slate-400">--dry-run</code>, <code class="text-slate-400">--force</code>).</td>
                            </tr>
                            <tr>
                                <td class="py-3 pl-4 text-emerald-400 font-bold">php artisan google-ads:sync</td>
                                <td class="py-3 pr-4 font-sans text-slate-300">Flushes cached conversion buffer directly into database.</td>
                            </tr>
                            <tr>
                                <td class="py-3 pl-4 text-emerald-400 font-bold">php artisan google-ads:test-connection</td>
                                <td class="py-3 pr-4 font-sans text-slate-300">Tests API credentials and verifies conversion action mapping.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>

    <!-- Footer -->
    <footer class="py-12 border-t border-slate-800/60 text-center text-xs text-slate-500 mt-20">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <span class="text-emerald-400">🕉️</span>
                <span class="font-bold text-slate-300">OmniSignal</span>
                <span>&bull; Pure Signal. Zero Noise.</span>
            </div>
            <p>&copy; 2026 OmniSignal (<a href="https://omnisignal.dev" class="text-slate-400 hover:underline">omnisignal.dev</a>). All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
