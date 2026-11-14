<script setup lang="ts">
import { 
    DraftingCompass, 
    BookOpen, 
    Sparkles, 
    Sun, 
    Calculator, 
    ShieldCheck, 
    Layers, 
    Lock, 
    ArrowUpRight, 
    FileText, 
    CheckCircle2, 
    Compass,
    Cpu,
    Radio
} from '@lucide/vue';
</script>

<template>
    <section id="features" class="relative py-20 sm:py-28 bg-[#050608] text-white">
        <!-- Ambient Background Glow -->
        <div class="pointer-events-none absolute bottom-1/4 left-1/2 -translate-x-1/2 h-96 w-full max-w-5xl bg-indigo-600/5 blur-[160px]"></div>

        <div class="relative mx-auto max-w-6xl px-6">
            <!-- Section Title -->
            <div class="mx-auto max-w-3xl text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-neutral-300">
                    <Cpu class="h-3 w-3 text-indigo-400" />
                    <span>Ekosistem Arsitektur Terintegrasi</span>
                </div>
                <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-5xl font-sans leading-tight">
                    Dari Model 3D ke Gambar CAD 2D, <br />
                    <span class="bg-gradient-to-r from-neutral-200 via-neutral-400 to-neutral-500 bg-clip-text text-transparent">
                        Render AI, Booklet A3 & RAB Otomatis.
                    </span>
                </h2>
                <p class="mt-4 text-sm sm:text-base text-neutral-400 max-w-2xl mx-auto leading-relaxed">
                    Satu alur kerja terpadu untuk arsitek modern. Setiap visual, denah, catatan revisi, dan dokumen penawaran saling terhubung tanpa jeda.
                </p>
            </div>

            <!-- Apple Pro Bento Grid (3 Rows Balanced) -->
            <div class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-6">

                <!-- Bento 1 (Large 2 Cols): Lembar Kerja CAD 2D & Ekspor DXF -->
                <div class="md:col-span-2 group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 sm:p-10 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25">
                    <div class="pointer-events-none absolute -right-16 -bottom-16 h-64 w-64 rounded-full bg-indigo-500/10 blur-[80px]"></div>

                    <div class="flex items-center justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/20 border border-indigo-500/30 text-indigo-400">
                            <DraftingCompass class="h-6 w-6" />
                        </div>
                        <span class="rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 text-xs font-medium text-indigo-300">
                            CAD 2D Interaktif
                        </span>
                    </div>

                    <div class="mt-8 max-w-lg">
                        <h3 class="text-2xl font-bold text-white tracking-tight">
                            Lembar Kerja CAD 2D & Ekspor DXF
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-neutral-300">
                            Gambar denah arsitektural langsung di browser dengan grid snap presisi. Lengkap dengan library dinding tebal, bukaan pintu swing, jendela, kolom beton, tangga, perabot, dan dimensi otomatis. Dukungan frame kertas ISO A4–A0 dengan Title Block otomatis serta ekspor langsung ke format <span class="text-indigo-300 font-medium">AutoCAD DXF & SVG</span>.
                        </p>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-3 text-xs text-neutral-300">
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5 font-mono">Dinding & Bukaan Pintu</span>
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5 font-mono">Kop Gambar Title Block</span>
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5 font-mono text-indigo-300">Ekspor DXF / SVG / PNG</span>
                    </div>
                </div>

                <!-- Bento 2 (1 Col): Booklet Presentasi A3 Landscape -->
                <div class="group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-500/20 border border-amber-500/30 text-amber-400">
                                <BookOpen class="h-6 w-6" />
                            </div>
                            <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-[10px] font-semibold text-amber-300">
                                1-Klik PDF
                            </span>
                        </div>
                        <h3 class="mt-6 text-xl font-bold text-white tracking-tight">
                            Booklet Presentasi A3
                        </h3>
                        <p class="mt-2.5 text-xs leading-relaxed text-neutral-400">
                            Otomatiskan penyusunan portofolio presentasi klien format A3 Landscape: Cover eksekutif proyek, Master Floor Plan 2D, visual render per ruangan lengkap dengan Key Plan mini locator denah.
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-xs text-amber-300 font-medium">
                        <span>Layout Standard ISO</span>
                        <ArrowUpRight class="h-4 w-4" />
                    </div>
                </div>

                <!-- Bento 3 (1 Col): Direct AI Architectural Render -->
                <div class="group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400">
                                <Sparkles class="h-6 w-6" />
                            </div>
                            <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-300">
                                Google Gemini AI
                            </span>
                        </div>
                        <h3 class="mt-6 text-xl font-bold text-white tracking-tight">
                            Render AI Fotorealistik
                        </h3>
                        <p class="mt-2.5 text-xs leading-relaxed text-neutral-400">
                            Ubah tangkapan layar 3D WebGL menjadi render fotorealistik arsitektur seketika tanpa mengubah bentuk geometri bangunan (100% geometry adherence). Pilihan mood cahaya Golden Hour, Blue Hour, hingga Interior Night.
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-xs text-emerald-300 font-medium">
                        <span>Batch & Single Render</span>
                        <ArrowUpRight class="h-4 w-4" />
                    </div>
                </div>

                <!-- Bento 4 (Large 2 Cols): 3D Viewer & Simulasi Matahari -->
                <div class="md:col-span-2 group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 sm:p-10 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25">
                    <div class="pointer-events-none absolute -left-16 -top-16 h-64 w-64 rounded-full bg-blue-500/10 blur-[80px]"></div>

                    <div class="flex items-center justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-500/20 border border-blue-500/30 text-blue-400">
                            <Sun class="h-6 w-6" />
                        </div>
                        <span class="rounded-full border border-blue-500/30 bg-blue-500/10 px-3 py-1 text-xs font-medium text-blue-300">
                            Simulasi Spasial & Solar Path
                        </span>
                    </div>

                    <div class="mt-8 max-w-lg">
                        <h3 class="text-2xl font-bold text-white tracking-tight">
                            Simulasi Matahari & Penempatan Pin Spasial
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-neutral-300">
                            Analisis arah bayangan dan pencahayaan alami bangunan berdasarkan koordinat geografis lintang & bujur secara akurat (SunCalc). Ambil snapshot multi-sudut berkode otomatis (<span class="font-mono text-blue-300">EXT-01</span>, <span class="font-mono text-blue-300">R-01</span>) dan sematkan pin revisi langsung pada permukaan struktur dengan garis penunjuk SVG dinamis.
                        </p>
                    </div>

                    <div class="mt-8 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-center">
                            <div class="font-bold text-white">Solar Calculation</div>
                            <div class="text-[10px] text-neutral-400 mt-0.5">Preset Pagi/Siang/Sore/Malam</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-center">
                            <div class="font-bold text-white">Snapshot Kode Ruang</div>
                            <div class="text-[10px] text-neutral-400 mt-0.5">EXT-* & R-* Sinkron ke CAD</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-center col-span-2 sm:col-span-1">
                            <div class="font-bold text-white">Draco 3D WebGL</div>
                            <div class="text-[10px] text-neutral-400 mt-0.5">Kompresi Model 85% Lebih Ringan</div>
                        </div>
                    </div>
                </div>

                <!-- Bento 5 (1 Col): Estimator RAB Otomatis dari 3D & Denah -->
                <div class="group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-500/20 border border-cyan-500/30 text-cyan-400">
                                <Calculator class="h-6 w-6" />
                            </div>
                            <span class="rounded-full border border-cyan-500/30 bg-cyan-500/10 px-2.5 py-0.5 text-[10px] font-semibold text-cyan-300">
                                RAB Terintegrasi
                            </span>
                        </div>
                        <h3 class="mt-6 text-xl font-bold text-white tracking-tight">
                            Estimator RAB & Volume 3D
                        </h3>
                        <p class="mt-2.5 text-xs leading-relaxed text-neutral-400">
                            Hitung volume mesh 3D model (.GLB) otomatis atau hasilkan rincian RAB per ruangan langsung dari lembar kerja denah 2D. Terintegrasi formula analisa harga satuan SNI, overhead 10%, PPN 11%, dan ekspor Excel (.xlsx).
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-xs text-cyan-300 font-medium">
                        <span>Ekspor Excel (.xlsx)</span>
                        <ArrowUpRight class="h-4 w-4" />
                    </div>
                </div>

                <!-- Bento 6 (Large 2 Cols): Proteksi Undangan Email & Kuota Anti Scope-Creep -->
                <div class="md:col-span-2 group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 sm:p-10 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25">
                    <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-emerald-500/10 blur-[80px]"></div>

                    <div class="flex items-center justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400">
                            <ShieldCheck class="h-6 w-6" />
                        </div>
                        <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-300">
                            Keamanan & Kuota Revisi
                        </span>
                    </div>

                    <div class="mt-8 max-w-lg">
                        <h3 class="text-2xl font-bold text-white tracking-tight">
                            Akses Klien Terproteksi & Kuota Anti Scope-Creep
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-neutral-300">
                            Karya Anda terlindungi: proyek hanya dapat dibuka oleh email klien yang diundang lewat otorisasi aman (Google OAuth / Passkey). Kuota revisi yang transparan mencegah permintaan revisi berulang tanpa batas, dilengkapi live chat terintegrasi via Reverb untuk komunikasi profesional.
                        </p>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-3 text-xs text-neutral-300">
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5">Undangan Khusus Email Klien</span>
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5">Kunci Kuota Revisi Otomatis</span>
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5">Live Chat Project Reverb</span>
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5">1 Akun: Arsitek & Klien</span>
                    </div>
                </div>

            </div>
        </div>
    </section>
</template>
