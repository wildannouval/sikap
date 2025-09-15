<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Tanda Tangan Digital - SIKAP</title>
    {{-- Memuat Tailwind CSS dari CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Anda bisa menambahkan font custom di sini jika perlu */
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 antialiased">

    <div class="container mx-auto max-w-2xl px-4 py-8 sm:py-12">
        
        {{-- Header dengan Logo/Nama Sistem --}}
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">SIKAP</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Sistem Informasi Kerja Praktik</p>
        </div>

        {{-- [START] KARTU UTAMA VERIFIKASI --}}
        <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl overflow-hidden">
            
            {{-- Bagian Header Status --}}
            @if($status === 'valid')
                <div class="bg-green-500 text-white p-6 text-center">
                    <svg class="w-16 h-16 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <h2 class="text-2xl font-bold">Dokumen Sah dan Valid</h2>
                </div>
            @elseif($status === 'kedaluwarsa')
                <div class="bg-yellow-500 text-white p-6 text-center">
                     <svg class="w-16 h-16 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    <h2 class="text-2xl font-bold">Token Verifikasi Kedaluwarsa</h2>
                </div>
            @else
                <div class="bg-red-500 text-white p-6 text-center">
                    <svg class="w-16 h-16 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <h2 class="text-2xl font-bold">Dokumen Tidak Valid</h2>
                </div>
            @endif
            
            {{-- Bagian Detail Dokumen --}}
            <div class="p-6 sm:p-8 space-y-4">
                <p class="text-center text-slate-600 dark:text-slate-400">{{ $alasan }}</p>

                @if($surat)
                    <div class="border-t border-slate-200 dark:border-slate-700 pt-4 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Jenis Dokumen:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">
                                @php
                                    $klass = get_class($surat);
                                    $jenis = str_contains($klass, 'SuratPengantar') ? 'Surat Pengantar' : (str_contains($klass, 'KerjaPraktek') ? 'SPK' : 'Berita Acara Seminar');
                                    echo $jenis;
                                @endphp
                            </span>
                        </div>
                         <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">ID Dokumen Unik:</span>
                            <span class="font-mono text-xs text-slate-700 dark:text-slate-200">{{ $surat->uuid }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Ditandatangani Oleh:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $surat->ttd_signed_by ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Waktu Tanda Tangan:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ optional($surat->ttd_signed_at)->format('d F Y, H:i:s') ?? '-' }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        {{-- [END] KARTU UTAMA VERIFIKASI --}}

        {{-- [BARU] Kartu Informasi --}}
        <div class="mt-8 bg-white dark:bg-slate-800 shadow-lg rounded-xl p-6 sm:p-8">
            <h3 class="text-lg font-semibold text-slate-800 dark:text-white">Tentang Halaman Verifikasi Ini</h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                Halaman ini adalah alat verifikasi resmi untuk memastikan keaslian dokumen yang dikeluarkan dan ditandatangani secara digital melalui sistem SIKAP.
            </p>
            <div class="mt-4 space-y-3 border-t border-slate-200 dark:border-slate-700 pt-4">
                <div class="flex items-start gap-3">
                    <div class="w-4 h-4 rounded-full bg-green-500 mt-1 flex-shrink-0"></div>
                    <p class="text-sm"><b class="text-slate-800 dark:text-white">Valid:</b> Tanda tangan digital dan token verifikasi cocok. Dokumen ini terjamin keasliannya.</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-4 h-4 rounded-full bg-yellow-500 mt-1 flex-shrink-0"></div>
                    <p class="text-sm"><b class="text-slate-800 dark:text-white">Kedaluwarsa:</b> Token verifikasi ini sudah melewati batas waktu amannya. Dokumen mungkin masih asli, namun link verifikasi ini tidak aktif lagi.</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-4 h-4 rounded-full bg-red-500 mt-1 flex-shrink-0"></div>
                    <p class="text-sm"><b class="text-slate-800 dark:text-white">Tidak Valid:</b> Token verifikasi tidak dikenali. Ada kemungkinan QR code salah, rusak, atau telah dimanipulasi.</p>
                </div>
            </div>
        </div>
        
        {{-- Footer --}}
        <p class="text-center text-xs text-slate-400 dark:text-slate-500 mt-10">
            © {{ date('Y') }} Jurusan Informatika. Scan QR pada area tanda tangan pejabat di dokumen untuk membuka halaman ini.
        </p>

    </div>

</body>
</html>