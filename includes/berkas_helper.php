<?php

// Helper Pemberkasan: master dokumen & logika dokumen wajib berbasis skema + pekerjaan
// Dipakai bersama oleh pemberkasan.php dan index.php

$master_dokumen = [
    // Identitas & Legalitas Diri
    'doc_1'  => '1. KTP (Pemohon & Pasangan)',
    'doc_2'  => '2. Kartu Keluarga (KK)',
    'doc_3'  => '3. Buku Nikah / Suket Belum Menikah',
    'doc_4'  => '4. NPWP',
    'doc_11' => '11. Pasfoto 3x4 Suami / Istri',

    // Berkas Pekerjaan / Penghasilan
    'doc_5'  => '5. Surat Ket Kerja / SK CPNS / SK PNS',
    'doc_6'  => '6. Taspen (Jika Ada)',
    'doc_7'  => '7. Slip Gaji 3 Bulan Terakhir',
    'doc_8'  => '8. Rek Koran 6 Bulan Terakhir',
    'doc_9'  => '9. SKU / NIB (Wirausaha)',
    'doc_10' => '10. Bon / Nota / Pembukuan Usaha',

    // Berkas Khusus FLPP / KPR Subsidi
    'doc_12' => '12. Checklis FLPP',
    'doc_13' => '13. Surat Keterangan Belum Memiliki Rumah',
    'doc_14' => '14. Surat Pernyataan Penghasilan',
    'doc_15' => '15. Surat Pernyataan Permohonan KPR Sejahtera',
    'doc_16' => '16. Surat Pernyataan (Menyatakan Menyertakan/Mengetahui)',
    'doc_17' => '17. Lamp 23 (Surat Permohonan Utk Mendapatkan Fasilitas KPR Sejahtera)',
    'doc_18' => '18. Surat Pernyataan Prasarana sarana dan utilitas',
    'doc_19' => '19. Pernyataan Penghasilan di bawah PTKP',
    'doc_20' => '20. Lamp 5 Surat Permohonan Subsidi Bantuan Uang Muka',
    'doc_21' => '21. Lamp 4 Pengakuan Kekurangan Bayar Uang Muka',
    'doc_22' => '22. Standing Instruction',
    'doc_23' => '23. Pembayaran SBUM',

    // Berkas Legalitas Bangunan & Transaksi
    'doc_24' => '24. SLF (Surat Pernyataan Kelaikan Fungsi) (Copy Warna)',
    'doc_25' => '25. IMB / PBG',
    'doc_26' => '26. PBB',
    'doc_27' => '27. SHM',
    'doc_28' => '28. Surat Penawaran (SPR)',
    'doc_29' => '29. Bukti Transfer DP / KWITANSI DP'
];

// Deteksi pekerjaan usaha / wirausaha
function isPekerjaanUsaha($pekerjaan) {
    $p = strtoupper(trim((string)$pekerjaan));
    return strpos($p, 'WIRA') !== false || strpos($p, 'USAHA') !== false;
}

// Dokumen penghasilan yang dicentang otomatis sesuai pekerjaan
function dokumenPekerjaan($pekerjaan) {
    if (isPekerjaanUsaha($pekerjaan)) {
        // Usaha / Wirausaha: Rek Koran, SKU/NIB, Bon/Nota
        return ['doc_8', 'doc_9', 'doc_10'];
    }
    // Karyawan / PNS / Guru / TNI / POLRI / dll: SK Kerja, Slip Gaji, Rek Koran
    return ['doc_5', 'doc_7', 'doc_8'];
}

// Dokumen wajib berdasarkan skema pembayaran
function hitungDokumenWajib($skema, $master_dokumen) {
    if (strpos(strtolower($skema), 'subsidi') !== false) {
        return array_keys($master_dokumen);
    } elseif (strpos(strtolower($skema), 'komersil') !== false) {
        $req = [];
        foreach ($master_dokumen as $key => $val) {
            $num = (int)str_replace('doc_', '', $key);
            if ($num < 12 || $num > 23) {
                $req[] = $key;
            }
        }
        return $req;
    } else {
        return ['doc_1', 'doc_2', 'doc_3', 'doc_4', 'doc_11', 'doc_28', 'doc_29'];
    }
}

// Hitung progres kelengkapan dari daftar dokumen tersimpan
function hitungProgresBerkas($saved_docs, $skema, $master_dokumen) {
    $required = hitungDokumenWajib($skema, $master_dokumen);
    $checked = 0;
    foreach ($required as $key) {
        if (isset($saved_docs[$key]) && $saved_docs[$key] == 1) {
            $checked++;
        }
    }
    $total = count($required);
    $persen = ($total > 0) ? round(($checked / $total) * 100) : 0;
    return [
        'required' => $required,
        'checked'  => $checked,
        'total'    => $total,
        'persen'   => $persen,
        'lengkap'  => ($total > 0 && $checked >= $total)
    ];
}
