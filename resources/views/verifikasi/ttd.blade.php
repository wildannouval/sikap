<!-- resources/views/verifikasi/ttd.blade.php -->
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verifikasi TTD Digital</title>
<style>
 body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;padding:24px;max-width:760px;margin:auto}
 .badge{display:inline-block;padding:6px 12px;border-radius:999px;font-weight:600}
 .ok{background:#e6ffed}.warn{background:#fff4e6}.bad{background:#ffe6e6}
 .card{border:1px solid #eee;border-radius:12px;padding:16px;margin-top:16px}
 .muted{color:#666}
</style>
</head><body>
<h1>Verifikasi Tanda Tangan Digital</h1>
@if($status==='valid')<div class="badge ok">VALID ✅</div>
@elseif($status==='kedaluwarsa')<div class="badge warn">KEDALUWARSA ⚠️</div>
@else<div class="badge bad">TIDAK VALID ❌</div>@endif
<div class="card">
  @if($surat)
    <ul>
      <li><strong>Jenis Dokumen:</strong>
        @php
          $klass = get_class($surat);
          $jenis = str_contains($klass,'SuratPengantar')?'Surat Pengantar':(str_contains($klass,'KerjaPraktek')?'SPK':'BAP');
          echo $jenis;
        @endphp
      </li>
      <li><strong>UUID:</strong> {{ $surat->uuid }}</li>
      <li><strong>Ditandatangani Oleh:</strong> {{ $surat->ttd_signed_by ?? '-' }}</li>
      <li><strong>Waktu TTD:</strong> {{ optional($surat->ttd_signed_at)->format('d-m-Y H:i') ?? '-' }}</li>
    </ul>
  @else
    <p>Dokumen tidak ditemukan.</p>
  @endif
  <p class="muted">{{ $alasan }}</p>
</div>
<p class="muted" style="margin-top:24px">Scan QR pada area tanda tangan pejabat di dokumen untuk membuka halaman ini.</p>
</body></html>
