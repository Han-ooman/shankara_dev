<?php
include "includes/koneksi.php";

session_start();
// Jika belum login atau status login tidak valid, lempar paksa kembali ke halaman login
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php");
    exit();
}
include "includes/cek_sesi.php";
require_once __DIR__ . '/includes/role_access.php';
role_guard();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php include "pwa_head.php"; ?>

    <!-- Tailwind CSS (dimuat SEBELUM CSS aplikasi agar preflight tidak menimpa style sidebar/modal) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    <title>Simulasi Kredit KPR - Shankara Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @media print {
            body { background: white !important; color: black !important; }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .shadow-xl, .shadow-md, .shadow-lg { box-shadow: none !important; }
        }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 22px;
            width: 22px;
            border-radius: 50%;
            background: #059669;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            margin-top: -8px;
        }
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 6px;
            cursor: pointer;
            background: #cbd5e1;
            border-radius: 3px;
        }
        /* Area kalkulator: latar lembut di dalam wrapper aplikasi */
        .simcalc-wrap {
            background: #f1f5f9;
            border-radius: 1rem;
            padding: 1.25rem;
        }
        @media (max-width: 575.98px) {
            .simcalc-wrap { padding: 0.5rem; }
        }
    </style>
</head>
<body class="font-sans text-slate-800 antialiased">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Buka menu" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
    <div class="app-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
    <div class="app-layout">
    <?php include "sidebar.php"; ?>

    <main class="main-wrapper">
        <div class="simcalc-wrap">

            <!-- Header Halaman -->
            <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-3 no-print mb-5">
                <div class="flex items-center gap-3">
                    <div class="bg-brand-800 text-white p-2.5 rounded-xl shadow-sm">
                        <i class="fa-solid fa-house-chimney-crack text-emerald-300 text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-slate-900 font-extrabold text-xl sm:text-2xl tracking-tight leading-tight mb-0">Simulasi KPR DP Besar</h1>
                        <p class="text-xs text-emerald-700 font-medium mb-0">Hitung Penghematan &amp; Angsuran Presisi</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="window.print()" class="bg-brand-800 hover:bg-brand-700 text-white text-xs sm:text-sm font-semibold py-2 px-3.5 rounded-lg transition flex items-center gap-2 no-print">
                        <i class="fa-solid fa-print"></i>
                        <span class="hidden sm:inline">Cetak PDF</span>
                    </button>
                    <button onclick="resetCalculator()" class="bg-rose-500/20 hover:bg-rose-500/30 text-rose-700 text-xs sm:text-sm font-semibold py-2 px-3.5 rounded-lg border border-rose-300 transition flex items-center gap-2">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span class="hidden sm:inline">Reset</span>
                    </button>
                </div>
            </div>

            <!-- Printable Header (Only visible when printing) -->
            <div class="hidden print-only mb-6 border-b-2 border-slate-800 pb-4">
                <h1 class="text-2xl font-bold">Laporan Simulasi KPR &amp; DP Besar Perumahan</h1>
                <p class="text-sm text-slate-600">Dicetak pada: <span id="printDate"></span></p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start max-w-6xl mx-auto">

                <!-- Left Panel: Input & Controls -->
                <div class="lg:col-span-7 space-y-6 no-print">

                    <!-- Card 1: Selection of House Type -->
                    <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-md border border-slate-200/80">
                        <div class="flex items-center justify-between mb-4">
                            <label class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-building text-brand-600"></i> Pilih Tipe Perumahan
                            </label>
                            <span class="text-xs bg-brand-50 text-brand-700 font-semibold px-2.5 py-1 rounded-full border border-brand-200">
                                Preset Dokumen
                            </span>
                        </div>

                        <!-- Grid Selection Cards -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-2" id="presetTypeGrid">
                            <!-- Dynamic Buttons inserted by JS -->
                        </div>

                        <!-- Custom Option for Type 36 Plafon Adjustment -->
                        <div id="type36PlafonContainer" class="hidden mt-4 p-4 bg-emerald-50/80 border border-emerald-200 rounded-xl space-y-2">
                            <label class="block text-xs font-bold text-emerald-900 uppercase tracking-wider">
                                <i class="fa-solid fa-sliders text-emerald-600 mr-1"></i> Penyesuaian Plafon Kredit Tipe 36
                            </label>
                            <p class="text-xs text-slate-600">Pilih plafon kredit standar bank yang disetujui:</p>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <button type="button" onclick="setTipe36Plafon(176000000)" id="plafon176Btn" class="py-1.5 px-3 rounded-lg text-xs font-bold border transition">
                                    Rp 176.000.000 (Rekomendasi Bank)
                                </button>
                                <button type="button" onclick="setTipe36Plafon(180000000)" id="plafon180Btn" class="py-1.5 px-3 rounded-lg text-xs font-bold border transition">
                                    Rp 180.000.000 (Standar)
                                </button>
                            </div>
                        </div>

                        <!-- Custom Type Input Form -->
                        <div id="customTypeContainer" class="hidden mt-4 p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                            <h4 class="font-bold text-xs uppercase tracking-wider text-slate-600">Parameter Tipe Custom</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Tipe / Unit</label>
                                    <input type="text" id="customTypeName" value="Tipe Custom" class="w-full text-sm font-medium border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Harga Jual (Rp)</label>
                                    <input type="number" id="customHousePrice" value="300000000" step="1000000" class="w-full text-sm font-medium border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">DP Standar (Rp)</label>
                                    <input type="number" id="customStandardDP" value="20000000" step="1000000" class="w-full text-sm font-medium border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Angsuran 15Th (Standar)</label>
                                    <input type="number" id="customBaseAngsuran" value="2500000" step="50000" class="w-full text-sm font-medium border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Custom DP Calculator Controls -->
                    <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-md border border-slate-200/80 space-y-6">
                        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                            <label class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-coins text-amber-500"></i> Pengaturan Uang Muka (DP)
                            </label>
                            <span id="dpPercentageBadge" class="text-xs font-bold bg-amber-100 text-amber-800 px-3 py-1 rounded-full">
                                0% dari Harga
                            </span>
                        </div>

                        <!-- Nominal DP Input -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-medium text-slate-500">Nominal Uang Muka (DP) Anda</span>
                                <span class="text-xs text-slate-400">Minimal: <span id="minDpDisplay">Rp 0</span></span>
                            </div>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 font-bold text-slate-400 text-sm">Rp</span>
                                <input type="text" id="customDpInput" class="w-full font-extrabold text-xl sm:text-2xl text-slate-800 pl-11 pr-4 py-3 border-2 border-slate-200 rounded-xl focus:border-brand-500 focus:ring-2 focus:ring-brand-200 focus:outline-none transition">
                            </div>
                        </div>

                        <!-- Slider Control -->
                        <div>
                            <div class="flex justify-between text-xs text-slate-500 mb-1 font-medium">
                                <span>DP Minimal</span>
                                <span>60% Harga</span>
                            </div>
                            <input type="range" id="dpSlider" min="0" max="50" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-600">
                        </div>

                        <!-- Quick Preset DP Buttons -->
                        <div>
                            <span class="text-xs font-semibold text-slate-500 block mb-2">Pilihan Cepat Tambahan DP:</span>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <button type="button" onclick="setQuickDpMultiplier(1)" class="py-2 px-3 bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 text-xs font-semibold rounded-lg border border-slate-200 transition text-center">
                                    DP Standar
                                </button>
                                <button type="button" onclick="setQuickDpValue(50000000)" class="py-2 px-3 bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 text-xs font-semibold rounded-lg border border-slate-200 transition text-center">
                                    DP Rp 50 Juta
                                </button>
                                <button type="button" onclick="setQuickDpValue(75000000)" class="py-2 px-3 bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 text-xs font-semibold rounded-lg border border-slate-200 transition text-center">
                                    DP Rp 75 Juta
                                </button>
                                <button type="button" onclick="setQuickDpValue(100000000)" class="py-2 px-3 bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 text-xs font-semibold rounded-lg border border-slate-200 transition text-center">
                                    DP Rp 100 Juta
                                </button>
                            </div>
                        </div>

                        <!-- Comparison Box (Standard vs Custom) -->
                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-200/80 rounded-xl p-4 space-y-3">
                            <div class="text-xs font-bold text-emerald-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-chart-line text-brand-600"></i> Perbandingan Pengurangan Plafon
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white/80 p-3 rounded-lg border border-emerald-100">
                                    <span class="text-xs text-slate-500 block">Plafon Standar</span>
                                    <span id="stdPlafonDisplay" class="font-bold text-slate-700 text-sm sm:text-base">Rp 0</span>
                                </div>
                                <div class="bg-white p-3 rounded-lg border border-brand-300 shadow-sm">
                                    <span class="text-xs text-brand-800 font-medium block">Plafon Baru (DP Besar)</span>
                                    <span id="newPlafonDisplay" class="font-extrabold text-brand-700 text-sm sm:text-base">Rp 0</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-xs pt-1 border-t border-emerald-200/60 text-emerald-900 font-semibold">
                                <span>Pengurangan Beban Hutang Pokok:</span>
                                <span id="plafonReducedDisplay" class="font-bold text-emerald-700">Rp 0 (0%)</span>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Right Panel: Result Summary Card -->
                <div class="lg:col-span-5 space-y-4">

                    <!-- Green Card UI (Reference Replica) -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200">

                        <!-- Card Header (Green Gradient) -->
                        <div class="bg-gradient-to-r from-brand-900 via-brand-800 to-brand-900 text-white p-5 flex justify-between items-center relative">
                            <div class="flex items-center space-x-3">
                                <div class="bg-white/10 p-2 rounded-lg border border-white/20">
                                    <i class="fa-solid fa-house text-xl text-emerald-300"></i>
                                </div>
                                <div>
                                    <h2 id="cardTitle" class="font-bold text-xl sm:text-2xl leading-snug mb-0">Tipe 50 Komersil</h2>
                                    <p class="text-xs text-emerald-200 mb-0">Skema Angsuran &amp; Detail DP</p>
                                </div>
                            </div>
                            <button title="Reset pilihan" onclick="resetCalculator()" class="text-emerald-200 hover:text-white p-2 transition no-print">
                                <i class="fa-solid fa-trash-can text-lg"></i>
                            </button>
                        </div>

                        <!-- Card Body -->
                        <div class="p-5 space-y-5">

                            <!-- Price & DP Details -->
                            <div class="space-y-3 pb-4 border-b border-slate-200/80">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-slate-500">Harga Jual</span>
                                    <span id="cardHousePrice" class="font-extrabold text-brand-700 text-lg sm:text-xl">Rp 299.000.000</span>
                                </div>
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-sm font-medium text-slate-500 block">Uang Muka (DP)</span>
                                        <span id="cardDpLabel" class="text-xs text-slate-400 font-medium">DP Disesuaikan</span>
                                    </div>
                                    <div class="text-right">
                                        <span id="cardDpPercentage" class="font-extrabold text-amber-600 text-base sm:text-lg">6,69%</span>
                                        <span class="text-slate-400 mx-1">/</span>
                                        <span id="cardDpNominal" class="font-extrabold text-amber-600 text-base sm:text-lg">Rp 20.000.000</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheme Section Title -->
                            <div class="flex items-center gap-2 text-brand-900 font-extrabold text-sm tracking-wide uppercase">
                                <i class="fa-solid fa-calculator text-brand-600"></i>
                                <span>SKEMA ANGSURAN / BULAN</span>
                            </div>

                            <!-- Tenor Options Tabs / Display -->
                            <div id="tenorSchemesContainer" class="space-y-4">
                                <!-- Tenor cards injected by JS -->
                            </div>

                            <!-- Plafon Info Footer inside Card -->
                            <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-2 text-xs leading-relaxed text-slate-600">
                                <div class="flex items-start gap-2 text-slate-800 font-medium">
                                    <i class="fa-solid fa-circle-info text-brand-600 mt-0.5 text-sm"></i>
                                    <div>
                                        <span>Plafon kredit KPR saat ini sebesar </span>
                                        <strong id="cardPlafonFooter" class="text-slate-900 font-bold">Rp 279.000.000</strong>.
                                    </div>
                                </div>
                                <p class="text-slate-500 pl-6 mb-0">
                                    Berkas administrasi KPR harus diserahkan dalam waktu <strong class="text-slate-700">14 hari</strong> setelah membayar uang muka.
                                </p>
                            </div>

                            <!-- Savings Alert Box (Shown when custom DP > Standard) -->
                            <div id="savingsAlertBox" class="bg-emerald-50 border border-emerald-300 rounded-xl p-4 text-xs space-y-1">
                                <div class="flex items-center gap-2 text-emerald-800 font-bold text-sm">
                                    <i class="fa-solid fa-piggy-bank text-emerald-600 text-base"></i>
                                    <span>Estimasi Penghematan Angsuran</span>
                                </div>
                                <p class="text-emerald-700 leading-snug mb-0">
                                    Dengan menambah DP sebesar <strong id="dpDiffDisplay">Rp 0</strong>, Anda menghemat angsuran sekitar <strong id="monthlySavingsDisplay">Rp 0/bulan</strong>.
                                </p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="space-y-2 pt-2 no-print">
                                <button onclick="shareToWhatsApp()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-xl shadow-md transition flex items-center justify-center gap-2 text-sm">
                                    <i class="fa-brands fa-whatsapp text-lg"></i>
                                    <span>Hubungi Marketing / Kirim Hasil</span>
                                </button>
                                <button onclick="window.print()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 px-4 rounded-xl border border-slate-300 transition text-xs flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    <span>Cetak Rincian Simulasi</span>
                                </button>
                            </div>

                        </div>
                    </div>

                    <!-- Footer Disclaimer -->
                    <p class="text-center text-xs text-slate-400 no-print mb-0">
                        *Simulasi ini merupakan estimasi angsuran. Suku bunga dan ketentuan bank dapat berubah sewaktu-waktu sesuai kebijakan penyedia KPR.
                    </p>

                </div>

            </div>
        </div>
    </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/idle.js"></script>
    <script>
        // Datasets Exactly Matched to CSV / Documents
        const HOUSING_PRESETS = [
            {
                id: 't36_subsidi',
                name: 'Tipe 36 (Subsidi)',
                price: 182000000,
                standardDp: 2000000,
                customPlafonOption: 176000000, // Configurable default (176jt or 180jt)
                standardPlafon: 176000000,
                schemes: [
                    {
                        tenorMonths: 180,
                        tenorYears: 15,
                        label: 'Tenor 180 bulan (15 tahun)',
                        steps: [{ years: '1-15 TH (Flat)', baseAmount: 1391797 }]
                    },
                    {
                        tenorMonths: 120,
                        tenorYears: 10,
                        label: 'Tenor 120 bulan (10 tahun)',
                        steps: [{ years: '1-10 TH (Flat)', baseAmount: 1866753 }]
                    },
                    {
                        tenorMonths: 60,
                        tenorYears: 5,
                        label: 'Tenor 60 bulan (5 tahun)',
                        steps: [{ years: '1-5 TH (Flat)', baseAmount: 3321337 }]
                    }
                ]
            },
            {
                id: 't36_komersil',
                name: 'Tipe 36 Komersil',
                price: 192000000,
                standardDp: 12000000,
                customPlafonOption: 176000000,
                standardPlafon: 180000000,
                schemes: [
                    {
                        tenorMonths: 180,
                        tenorYears: 15,
                        label: 'Tenor 180 bulan (15 tahun)',
                        steps: [{ years: '1-15 TH (Flat)', baseAmount: 1391797 }]
                    },
                    {
                        tenorMonths: 120,
                        tenorYears: 10,
                        label: 'Tenor 120 bulan (10 tahun)',
                        steps: [{ years: '1-10 TH (Flat)', baseAmount: 1866753 }]
                    },
                    {
                        tenorMonths: 60,
                        tenorYears: 5,
                        label: 'Tenor 60 bulan (5 tahun)',
                        steps: [{ years: '1-5 TH (Flat)', baseAmount: 3321337 }]
                    }
                ]
            },
            {
                id: 't45',
                name: 'Tipe 45 Non-Subsidi',
                price: 270000000,
                standardDp: 10000000,
                standardPlafon: 260000000,
                schemes: [
                    {
                        tenorMonths: 120,
                        tenorYears: 10,
                        label: 'Tenor 120 bulan (10 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 2726041 },
                            { years: '2-3 TH', baseAmount: 2961156 },
                            { years: '4 TH', baseAmount: 3258154 },
                            { years: '5-10 TH', baseAmount: 3347799 }
                        ]
                    },
                    {
                        tenorMonths: 180,
                        tenorYears: 15,
                        label: 'Tenor 180 bulan (15 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 2022363 },
                            { years: '2-3 TH', baseAmount: 2284128 },
                            { years: '4-10 TH', baseAmount: 2656703 },
                            { years: '11-15 TH', baseAmount: 2718794 }
                        ]
                    }
                ]
            },
            {
                id: 't50_komersil',
                name: 'Tipe 50 Komersil',
                price: 299000000,
                standardDp: 20000000,
                standardPlafon: 279000000,
                schemes: [
                    {
                        tenorMonths: 120,
                        tenorYears: 10,
                        label: 'Tenor 120 bulan (10 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 2925252 },
                            { years: '2-3 TH', baseAmount: 3177549 },
                            { years: '4 TH', baseAmount: 3496249 },
                            { years: '5-10 TH', baseAmount: 3577963 }
                        ]
                    },
                    {
                        tenorMonths: 180,
                        tenorYears: 15,
                        label: 'Tenor 180 bulan (15 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 2170151 },
                            { years: '2-3 TH', baseAmount: 2451045 },
                            { years: '4 TH', baseAmount: 2850846 },
                            { years: '5-10 TH', baseAmount: 2972241 },
                            { years: '11-15 TH', baseAmount: 2291491 }
                        ]
                    }
                ]
            },
            {
                id: 't55',
                name: 'Tipe 55 Non-Subsidi',
                price: 390000000,
                standardDp: 20000000,
                standardPlafon: 370000000,
                schemes: [
                    {
                        tenorMonths: 120,
                        tenorYears: 10,
                        label: 'Tenor 120 bulan (10 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 3702266 },
                            { years: '2 TH', baseAmount: 4027182 },
                            { years: '3 TH', baseAmount: 4333366 },
                            { years: '4 TH', baseAmount: 4616525 },
                            { years: '5-10 TH', baseAmount: 4872545 }
                        ]
                    },
                    {
                        tenorMonths: 180,
                        tenorYears: 15,
                        label: 'Tenor 180 bulan (15 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 2690723 },
                            { years: '2-3 TH', baseAmount: 3048903 },
                            { years: '4-5 TH', baseAmount: 3408503 },
                            { years: '6-7 TH', baseAmount: 3765329 },
                            { years: '8-10 TH', baseAmount: 4115572 },
                            { years: '11-15 TH', baseAmount: 4281458 }
                        ]
                    }
                ]
            },
            {
                id: 't60_legian',
                name: 'Tipe 60 Legian',
                price: 360000000,
                standardDp: 10000000,
                standardPlafon: 350000000,
                schemes: [
                    {
                        tenorMonths: 120,
                        tenorYears: 10,
                        label: 'Tenor 120 bulan (10 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 3669671 },
                            { years: '2-3 TH', baseAmount: 4084139 },
                            { years: '4-6 TH', baseAmount: 4407338 },
                            { years: '7-10 TH', baseAmount: 4491423 }
                        ]
                    },
                    {
                        tenorMonths: 180,
                        tenorYears: 15,
                        label: 'Tenor 180 bulan (15 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 2722412 },
                            { years: '2-3 TH', baseAmount: 3353776 },
                            { years: '4-6 TH', baseAmount: 3623174 },
                            { years: '7-15 TH', baseAmount: 3856455 }
                        ]
                    }
                ]
            },
            {
                id: 't65',
                name: 'Tipe 65 Non-Subsidi',
                price: 430000000,
                standardDp: 20000000,
                standardPlafon: 410000000,
                schemes: [
                    {
                        tenorMonths: 120,
                        tenorYears: 10,
                        label: 'Tenor 120 bulan (10 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 4102511 },
                            { years: '2 TH', baseAmount: 4462553 },
                            { years: '3 TH', baseAmount: 4801838 },
                            { years: '4 TH', baseAmount: 5115609 },
                            { years: '5-10 TH', baseAmount: 5399306 }
                        ]
                    },
                    {
                        tenorMonths: 180,
                        tenorYears: 15,
                        label: 'Tenor 180 bulan (15 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: 2981612 },
                            { years: '2-3 TH', baseAmount: 3378514 },
                            { years: '4-5 TH', baseAmount: 3776990 },
                            { years: '6-7 TH', baseAmount: 4172392 },
                            { years: '8-10 TH', baseAmount: 4560498 },
                            { years: '11-15 TH', baseAmount: 4744319 }
                        ]
                    }
                ]
            }
        ];

        // Global State Variables
        let activePresetId = 't50_komersil';
        let currentPrice = 299000000;
        let currentStandardDp = 20000000;
        let currentStandardPlafon = 279000000;
        let currentDp = 20000000;

        // Formatter Helper Functions
        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(number).replace('Rp', 'Rp ');
        }

        function parseCurrencyInput(valStr) {
            return parseInt(valStr.replace(/[^0-9]/g, '')) || 0;
        }

        // Initialize Web Application
        window.onload = function() {
            const today = new Date();
            document.getElementById('printDate').innerText = today.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

            renderPresetButtons();
            selectPreset('t50_komersil');
            setupEventListeners();
        };

        // Render Preset Grid Buttons
        function renderPresetButtons() {
            const grid = document.getElementById('presetTypeGrid');
            grid.innerHTML = '';

            HOUSING_PRESETS.forEach(preset => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.id = `btn-${preset.id}`;
                btn.onclick = () => selectPreset(preset.id);
                btn.className = `p-2.5 rounded-xl border text-left transition flex flex-col justify-between h-full ${
                    preset.id === activePresetId
                        ? 'border-brand-600 bg-brand-50 text-brand-900 ring-2 ring-brand-500/20 font-bold'
                        : 'border-slate-200 bg-white hover:border-brand-300 text-slate-700'
                }`;

                btn.innerHTML = `
                    <span class="text-xs font-bold block truncate">${preset.name}</span>
                    <span class="text-[10px] text-slate-500 font-medium mt-1">${(preset.price/1000000).toFixed(0)} Juta</span>
                `;
                grid.appendChild(btn);
            });

            // Add Custom Type Button
            const customBtn = document.createElement('button');
            customBtn.type = 'button';
            customBtn.id = 'btn-custom';
            customBtn.onclick = () => selectPreset('custom');
            customBtn.className = 'p-2.5 rounded-xl border border-dashed border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-600 text-left transition flex flex-col justify-between h-full';
            customBtn.innerHTML = `
                <span class="text-xs font-bold block"><i class="fa-solid fa-plus text-brand-600 mr-1"></i> Custom</span>
                <span class="text-[10px] text-slate-500 font-medium mt-1">Input Manual</span>
            `;
            grid.appendChild(customBtn);
        }

        // Select House Type
        function selectPreset(presetId) {
            activePresetId = presetId;

            const allBtns = document.querySelectorAll('#presetTypeGrid button');
            allBtns.forEach(btn => {
                btn.classList.remove('border-brand-600', 'bg-brand-50', 'text-brand-900', 'ring-2', 'ring-brand-500/20', 'font-bold');
                btn.classList.add('border-slate-200', 'bg-white', 'text-slate-700');
            });

            const activeBtn = document.getElementById(`btn-${presetId}`);
            if (activeBtn) {
                activeBtn.classList.remove('border-slate-200', 'bg-white', 'text-slate-700');
                activeBtn.classList.add('border-brand-600', 'bg-brand-50', 'text-brand-900', 'ring-2', 'ring-brand-500/20', 'font-bold');
            }

            const customContainer = document.getElementById('customTypeContainer');
            const type36Container = document.getElementById('type36PlafonContainer');

            if (presetId === 't36_subsidi' || presetId === 't36_komersil') {
                type36Container.classList.remove('hidden');
                setTipe36Plafon(176000000); // Default to 176jt requirement
            } else {
                type36Container.classList.add('hidden');
            }

            if (presetId === 'custom') {
                customContainer.classList.remove('hidden');
                updateFromCustomInputs();
            } else {
                customContainer.classList.add('hidden');
                const preset = HOUSING_PRESETS.find(p => p.id === presetId);
                if (preset) {
                    currentPrice = preset.price;
                    currentStandardDp = preset.standardDp;
                    currentStandardPlafon = preset.standardPlafon;
                    currentDp = preset.standardDp;
                }
            }

            updateSliderLimits();
            syncDpInputs(currentDp);
            calculateAndRender();
        }

        function setTipe36Plafon(plafonVal) {
            const preset = HOUSING_PRESETS.find(p => p.id === activePresetId);
            if (preset) {
                currentStandardPlafon = plafonVal;
                currentStandardDp = preset.price - plafonVal;
                currentDp = currentStandardDp;

                const btn176 = document.getElementById('plafon176Btn');
                const btn180 = document.getElementById('plafon180Btn');

                if (plafonVal === 176000000) {
                    btn176.className = "py-1.5 px-3 rounded-lg text-xs font-bold border bg-brand-600 text-white border-brand-700 shadow-sm";
                    btn180.className = "py-1.5 px-3 rounded-lg text-xs font-bold border bg-white text-slate-700 border-slate-300 hover:bg-slate-50";
                } else {
                    btn180.className = "py-1.5 px-3 rounded-lg text-xs font-bold border bg-brand-600 text-white border-brand-700 shadow-sm";
                    btn176.className = "py-1.5 px-3 rounded-lg text-xs font-bold border bg-white text-slate-700 border-slate-300 hover:bg-slate-50";
                }

                updateSliderLimits();
                syncDpInputs(currentDp);
                calculateAndRender();
            }
        }

        function updateSliderLimits() {
            const slider = document.getElementById('dpSlider');
            slider.min = Math.floor(currentStandardDp);
            slider.max = Math.floor(currentPrice * 0.6);
            slider.step = 1000000;
            document.getElementById('minDpDisplay').innerText = formatRupiah(currentStandardDp);
        }

        function syncDpInputs(amount) {
            currentDp = Math.max(currentStandardDp, Math.min(amount, currentPrice * 0.8));
            document.getElementById('customDpInput').value = currentDp.toLocaleString('id-ID');
            document.getElementById('dpSlider').value = currentDp;
        }

        function setupEventListeners() {
            const dpInput = document.getElementById('customDpInput');
            const dpSlider = document.getElementById('dpSlider');

            dpInput.addEventListener('input', function(e) {
                let rawVal = parseCurrencyInput(e.target.value);
                currentDp = rawVal;
                dpSlider.value = rawVal;
                e.target.value = rawVal ? rawVal.toLocaleString('id-ID') : '';
                calculateAndRender();
            });

            dpInput.addEventListener('blur', function(e) {
                if (currentDp < currentStandardDp) {
                    syncDpInputs(currentStandardDp);
                    calculateAndRender();
                }
            });

            dpSlider.addEventListener('input', function(e) {
                let val = parseInt(e.target.value);
                currentDp = val;
                dpInput.value = val.toLocaleString('id-ID');
                calculateAndRender();
            });

            ['customTypeName', 'customHousePrice', 'customStandardDP', 'customBaseAngsuran'].forEach(id => {
                document.getElementById(id).addEventListener('input', () => {
                    if (activePresetId === 'custom') {
                        updateFromCustomInputs();
                        updateSliderLimits();
                        calculateAndRender();
                    }
                });
            });
        }

        function updateFromCustomInputs() {
            currentPrice = parseInt(document.getElementById('customHousePrice').value) || 100000000;
            currentStandardDp = parseInt(document.getElementById('customStandardDP').value) || 10000000;
            currentStandardPlafon = currentPrice - currentStandardDp;
            if (currentDp < currentStandardDp) {
                currentDp = currentStandardDp;
            }
        }

        function setQuickDpMultiplier(multiplier) {
            syncDpInputs(currentStandardDp * multiplier);
            calculateAndRender();
        }

        function setQuickDpValue(value) {
            syncDpInputs(Math.max(currentStandardDp, value));
            calculateAndRender();
        }

        function calculateAndRender() {
            const stdPlafon = currentStandardPlafon;
            const newPlafon = Math.max(0, currentPrice - currentDp);

            const ratio = stdPlafon > 0 ? (newPlafon / stdPlafon) : 0;
            const dpPercentage = ((currentDp / currentPrice) * 100).toFixed(2).replace('.', ',');

            document.getElementById('dpPercentageBadge').innerText = `${dpPercentage}% dari Harga`;
            document.getElementById('stdPlafonDisplay').innerText = formatRupiah(stdPlafon);
            document.getElementById('newPlafonDisplay').innerText = formatRupiah(newPlafon);

            const plafonReduced = stdPlafon - newPlafon;
            const plafonReducedPct = stdPlafon > 0 ? ((plafonReduced / stdPlafon) * 100).toFixed(1) : 0;
            document.getElementById('plafonReducedDisplay').innerText = `${formatRupiah(plafonReduced)} (${plafonReducedPct}%)`;

            let currentPreset = HOUSING_PRESETS.find(p => p.id === activePresetId);
            let activeName = currentPreset ? currentPreset.name : document.getElementById('customTypeName').value;

            document.getElementById('cardTitle').innerText = activeName;
            document.getElementById('cardHousePrice').innerText = formatRupiah(currentPrice);
            document.getElementById('cardDpNominal').innerText = formatRupiah(currentDp);
            document.getElementById('cardDpPercentage').innerText = `${dpPercentage}%`;
            document.getElementById('cardPlafonFooter').innerText = formatRupiah(newPlafon);

            if (currentDp > currentStandardDp) {
                document.getElementById('cardDpLabel').innerText = `DP Besar (Standar: ${formatRupiah(currentStandardDp)})`;
                document.getElementById('cardDpLabel').classList.add('text-brand-700', 'font-semibold');
            } else {
                document.getElementById('cardDpLabel').innerText = 'DP Standar';
                document.getElementById('cardDpLabel').classList.remove('text-brand-700', 'font-semibold');
            }

            let activeSchemes = [];
            if (activePresetId === 'custom') {
                const baseAng = parseInt(document.getElementById('customBaseAngsuran').value) || 2000000;
                activeSchemes = [
                    {
                        tenorMonths: 180,
                        tenorYears: 15,
                        label: 'Tenor 180 bulan (15 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: Math.round(baseAng * 0.85) },
                            { years: '2-3 TH', baseAmount: Math.round(baseAng * 0.95) },
                            { years: '4-15 TH', baseAmount: baseAng }
                        ]
                    },
                    {
                        tenorMonths: 120,
                        tenorYears: 10,
                        label: 'Tenor 120 bulan (10 tahun)',
                        steps: [
                            { years: '1 TH', baseAmount: Math.round(baseAng * 1.1) },
                            { years: '2-10 TH', baseAmount: Math.round(baseAng * 1.3) }
                        ]
                    }
                ];
            } else {
                activeSchemes = currentPreset.schemes;
            }

            const schemesContainer = document.getElementById('tenorSchemesContainer');
            schemesContainer.innerHTML = '';

            let firstStepSavings = 0;

            activeSchemes.forEach((scheme, index) => {
                const schemeBox = document.createElement('div');
                schemeBox.className = 'bg-slate-50/80 rounded-xl p-4 border border-slate-200/90 space-y-3';

                let stepsHtml = '';
                scheme.steps.forEach((step, sIdx) => {
                    const adjustedAmount = Math.round(step.baseAmount * ratio);
                    const diffAmount = step.baseAmount - adjustedAmount;

                    if (index === 0 && sIdx === 0) {
                        firstStepSavings = diffAmount;
                    }

                    stepsHtml += `
                        <div class="flex justify-between items-center text-xs sm:text-sm py-1 border-b border-slate-200/50 last:border-0">
                            <span class="font-medium text-slate-600 w-28 sm:w-32">${step.years}</span>
                            <div class="text-right">
                                <span class="font-extrabold text-slate-800">${formatRupiah(adjustedAmount)}</span>
                                ${currentDp > currentStandardDp ? `<span class="block text-[10px] text-emerald-600 font-medium">(Hemat ${formatRupiah(diffAmount)})</span>` : ''}
                            </div>
                        </div>
                    `;
                });

                schemeBox.innerHTML = `
                    <div class="flex items-center gap-2 text-xs sm:text-sm font-bold text-slate-800 pb-1 border-b border-slate-200">
                        <i class="fa-regular fa-clock text-brand-600"></i>
                        <span>${scheme.label}</span>
                    </div>
                    <div class="space-y-1">
                        ${stepsHtml}
                    </div>
                `;

                schemesContainer.appendChild(schemeBox);
            });

            const savingsBox = document.getElementById('savingsAlertBox');
            if (currentDp > currentStandardDp) {
                savingsBox.classList.remove('hidden');
                document.getElementById('dpDiffDisplay').innerText = formatRupiah(currentDp - currentStandardDp);
                document.getElementById('monthlySavingsDisplay').innerText = `${formatRupiah(firstStepSavings)}`;
            } else {
                savingsBox.classList.add('hidden');
            }
        }

        function resetCalculator() {
            selectPreset('t50_komersil');
        }

        function shareToWhatsApp() {
            let currentPreset = HOUSING_PRESETS.find(p => p.id === activePresetId);
            let activeName = currentPreset ? currentPreset.name : document.getElementById('customTypeName').value;

            const stdPlafon = currentStandardPlafon;
            const newPlafon = Math.max(0, currentPrice - currentDp);
            const ratio = stdPlafon > 0 ? (newPlafon / stdPlafon) : 0;

            let text = `Halo Marketing, saya berminat dengan simulasi KPR berikut:\n\n`;
            text += `🏡 *${activeName}*\n`;
            text += `💰 *Harga Jual:* ${formatRupiah(currentPrice)}\n`;
            text += `💵 *Rencana DP Besar:* ${formatRupiah(currentDp)} (${((currentDp/currentPrice)*100).toFixed(1)}%)\n`;
            text += `📄 *Plafon Kredit KPR:* ${formatRupiah(newPlafon)}\n\n`;
            text += `*Estimasi Angsuran:* \n`;

            let activeSchemes = currentPreset ? currentPreset.schemes : [];
            activeSchemes.forEach(scheme => {
                text += `• *${scheme.label}*:\n`;
                scheme.steps.forEach(step => {
                    const adjusted = Math.round(step.baseAmount * ratio);
                    text += `   - ${step.years}: ${formatRupiah(adjusted)}/bln\n`;
                });
            });

            text += `\nMohon informasi ketersediaan unit dan berkas persyaratannya. Terima kasih!`;

            const encodedText = encodeURIComponent(text);
            window.open(`https://api.whatsapp.com/send?text=${encodedText}`, '_blank');
        }
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
