<?php

// ============================================================
// SHARED DATA: LAPORAN MINGGUAN
// Dipakai oleh laporan_mingguan.php dan print/cetak_laporan_mingguan.php
// agar angka yang tampil di layar & PDF selalu identik.
// ============================================================

if (!function_exists('lm_rupiah')) {
    function lm_rupiah($n) {
        return 'Rp ' . number_format((int)$n, 0, ',', '.');
    }
}

if (!function_exists('lm_parse_week')) {
    /**
     * Ubah "2026-W42" menjadi rentang Senin..Minggu.
     * Mengembalikan [week, start, end] atau rentang minggu berjalan bila gagal.
     */
    function lm_parse_week(?string $week): array {
        $week = trim((string)$week);
        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $week, $m)) {
            $year = (int)$m[1];
            $wk   = (int)$m[2];
            if ($wk >= 1 && $wk <= 53) {
                $d = new DateTime();
                $d->setISODate($year, $wk, 1);
                $start = $d->format('Y-m-d');
                $end   = (clone $d)->modify('+6 days')->format('Y-m-d');
                return [sprintf('%04d-W%02d', $year, $wk), $start, $end];
            }
        }
        $d = new DateTime();
        $wk = (int)$d->format('W');
        $d->setISODate((int)$d->format('o'), $wk, 1);
        $start = $d->format('Y-m-d');
        $end   = (clone $d)->modify('+6 days')->format('Y-m-d');
        return [sprintf('%04d-W%02d', (int)$d->format('o'), $wk), $start, $end];
    }
}

if (!function_exists('lm_hitung')) {
    /**
     * Hitung seluruh metrik laporan mingguan untuk rentang $start..$end.
     */
    function lm_hitung(mysqli $koneksi, string $start, string $end): array {
        $s = mysqli_real_escape_string($koneksi, $start);
        $e = mysqli_real_escape_string($koneksi, $end);
        $today = date('Y-m-d');

        $scalar = function (string $sql, string $col = 'v') use ($koneksi) {
            $r = mysqli_query($koneksi, $sql);
            if ($r && ($row = mysqli_fetch_assoc($r))) return $row[$col];
            return 0;
        };

        // ---- KPI ----
        $booking   = (int)$scalar("SELECT COUNT(*) v FROM data_transaksi WHERE tgl_booking BETWEEN '$s' AND '$e'");
        $akad      = (int)$scalar("SELECT COUNT(*) v FROM data_transaksi WHERE tanggal_akad BETWEEN '$s' AND '$e'");
        $kas_masuk = (float)$scalar("SELECT COALESCE(SUM(jumlah_bayar),0) v FROM data_pembayaran WHERE tgl_bayar BETWEEN '$s' AND '$e'");
        $pengeluaran = (float)$scalar("SELECT COALESCE(SUM(nominal),0) v FROM data_keuangan WHERE jenis='Pengeluaran' AND tanggal BETWEEN '$s' AND '$e'");
        $pemasukan = (float)$scalar("SELECT COALESCE(SUM(nominal),0) v FROM data_keuangan WHERE jenis='Pemasukan' AND tanggal BETWEEN '$s' AND '$e'");
        $prospek_baru = (int)$scalar("SELECT COUNT(*) v FROM data_prospek WHERE DATE(created_at) BETWEEN '$s' AND '$e'");
        $followup  = (int)$scalar("SELECT COUNT(*) v FROM data_prospek WHERE tanggal_followup BETWEEN '$s' AND '$e'");
        $berkas    = (int)$scalar("SELECT COUNT(*) v FROM data_bi_checking WHERE tgl_cek BETWEEN '$s' AND '$e'");

        // ---- Rekap per cluster ----
        $per_cluster = [];
        $add = function ($idc, $field, $val) use (&$per_cluster) {
            $idc = (int)$idc;
            if (!isset($per_cluster[$idc])) $per_cluster[$idc] = ['booking' => 0, 'akad' => 0, 'kas' => 0.0];
            $per_cluster[$idc][$field] += $val;
        };
        $q = mysqli_query($koneksi, "SELECT r.id_cluster, COUNT(*) n FROM data_transaksi t
                JOIN data_rumah r ON t.id_rumah = r.id_rumah
                WHERE t.tgl_booking BETWEEN '$s' AND '$e' GROUP BY r.id_cluster");
        if ($q) while ($row = mysqli_fetch_assoc($q)) $add($row['id_cluster'], 'booking', (int)$row['n']);
        $q = mysqli_query($koneksi, "SELECT r.id_cluster, COUNT(*) n FROM data_transaksi t
                JOIN data_rumah r ON t.id_rumah = r.id_rumah
                WHERE t.tanggal_akad BETWEEN '$s' AND '$e' GROUP BY r.id_cluster");
        if ($q) while ($row = mysqli_fetch_assoc($q)) $add($row['id_cluster'], 'akad', (int)$row['n']);
        $q = mysqli_query($koneksi, "SELECT r.id_cluster, COALESCE(SUM(p.jumlah_bayar),0) n FROM data_pembayaran p
                JOIN data_transaksi t ON p.id_transaksi = t.id_transaksi
                JOIN data_rumah r ON t.id_rumah = r.id_rumah
                WHERE p.tgl_bayar BETWEEN '$s' AND '$e' GROUP BY r.id_cluster");
        if ($q) while ($row = mysqli_fetch_assoc($q)) $add($row['id_cluster'], 'kas', (float)$row['n']);

        $cluster_names = [];
        $q = mysqli_query($koneksi, "SELECT id_cluster, cluster_name FROM data_cluster");
        if ($q) while ($row = mysqli_fetch_assoc($q)) $cluster_names[(int)$row['id_cluster']] = $row['cluster_name'];

        $rekap = [];
        foreach ($per_cluster as $idc => $v) {
            if ($v['booking'] === 0 && $v['akad'] === 0 && $v['kas'] <= 0) continue;
            $rekap[] = [
                'cluster' => $cluster_names[$idc] ?? ('Cluster ' . $idc),
                'booking' => $v['booking'],
                'akad'    => $v['akad'],
                'kas'     => $v['kas'],
            ];
        }
        usort($rekap, fn($a, $b) => ($b['kas'] <=> $a['kas']) ?: ($b['booking'] <=> $a['booking']));

        // ---- Kegiatan harian ----
        $keg = ['total' => 0, 'selesai' => 0, 'berlangsung' => 0, 'terhambat' => 0, 'belum' => 0];
        $q = mysqli_query($koneksi, "SELECT
                COUNT(*) total,
                SUM(status='Selesai') selesai,
                SUM(status='Sedang Berlangsung') berlangsung,
                SUM(status='Terhambat') terhambat,
                SUM(status='Belum Dikerjakan') belum
            FROM data_laporan_harian WHERE tanggal BETWEEN '$s' AND '$e'");
        if ($q && ($row = mysqli_fetch_assoc($q))) {
            $keg['total']       = (int)$row['total'];
            $keg['selesai']     = (int)$row['selesai'];
            $keg['berlangsung'] = (int)$row['berlangsung'];
            $keg['terhambat']   = (int)$row['terhambat'];
            $keg['belum']       = (int)$row['belum'];
        }
        $keg_kategori = [];
        $q = mysqli_query($koneksi, "SELECT kategori, COUNT(*) n FROM data_laporan_harian
                WHERE tanggal BETWEEN '$s' AND '$e' GROUP BY kategori ORDER BY n DESC");
        if ($q) while ($row = mysqli_fetch_assoc($q)) $keg_kategori[$row['kategori']] = (int)$row['n'];

        // ---- Prospek per sumber ----
        $sumber = [];
        $q = mysqli_query($koneksi, "SELECT sumber, COUNT(*) n FROM data_prospek
                WHERE DATE(created_at) BETWEEN '$s' AND '$e' GROUP BY sumber ORDER BY n DESC");
        if ($q) while ($row = mysqli_fetch_assoc($q)) $sumber[$row['sumber']] = (int)$row['n'];

        // ---- Tunggakan / pengingat (tanggal jatuh <= akhir minggu) ----
        $tunggakan = [];
        $push = function ($jenis, $label, $nama, $no, $unit, $tanggal) use (&$tunggakan, $e, $today) {
            if (empty($tanggal) || $tanggal === '0000-00-00' || $tanggal > $e) return;
            $sisa = (int)floor((strtotime($tanggal) - strtotime($today)) / 86400);
            if ($sisa < 0)      $st = ['Telat ' . abs($sisa) . ' hari', 'bg-danger'];
            elseif ($sisa === 0) $st = ['Hari ini', 'bg-warning text-dark'];
            else                 $st = ['H-' . $sisa, 'bg-primary'];
            $tunggakan[] = compact('jenis', 'label', 'nama', 'no', 'unit', 'tanggal') + ['sisa' => $sisa, 'st' => $st[0], 'badge' => $st[1]];
        };

        $q = mysqli_query($koneksi, "SELECT * FROM view_database_utama
                WHERE deadline_berkas IS NOT NULL AND deadline_berkas NOT IN ('', '0000-00-00')
                  AND status_konsumen NOT IN ('BATAL', 'ACC - AKAD')
                  AND deadline_berkas <= '$e'");
        if ($q) while ($r = mysqli_fetch_assoc($q)) $push('deadline', 'Deadline Pemberkasan', $r['name_lengkap'] ?? '', $r['no_whatsapp'] ?? '', ($r['cluster_name'] ?? '') . ' - ' . ($r['no_blok'] ?? ''), $r['deadline_berkas']);

        $q = mysqli_query($koneksi, "SELECT * FROM view_database_utama
                WHERE tgl_wawancara IS NOT NULL AND tgl_wawancara NOT IN ('', '0000-00-00')
                  AND status_konsumen NOT IN ('BATAL', 'ACC - AKAD')
                  AND tgl_wawancara <= '$e'");
        if ($q) while ($r = mysqli_fetch_assoc($q)) $push('wawancara', 'Wawancara Bank', $r['name_lengkap'] ?? '', $r['no_whatsapp'] ?? '', ($r['cluster_name'] ?? '') . ' - ' . ($r['no_blok'] ?? ''), $r['tgl_wawancara']);

        $q = mysqli_query($koneksi, "SELECT * FROM view_database_utama
                WHERE tanggal_akad IS NOT NULL AND tanggal_akad NOT IN ('', '0000-00-00')
                  AND status_konsumen NOT IN ('BATAL', 'ACC - AKAD')
                  AND tanggal_akad <= '$e'");
        if ($q) while ($r = mysqli_fetch_assoc($q)) $push('akad', 'Akad KPR', $r['name_lengkap'] ?? '', $r['no_whatsapp'] ?? '', ($r['cluster_name'] ?? '') . ' - ' . ($r['no_blok'] ?? ''), $r['tanggal_akad']);

        $q = mysqli_query($koneksi, "SELECT * FROM data_prospek
                WHERE tanggal_followup IS NOT NULL AND tanggal_followup != ''
                  AND status_followup NOT IN ('Deal', 'Tidak Lanjut')
                  AND tanggal_followup <= '$e'");
        if ($q) while ($r = mysqli_fetch_assoc($q)) $push('followup', 'Follow-up Prospek', $r['name_lengkap'] ?? '', $r['no_whatsapp'] ?? '', trim(($r['cluster_minat'] ?? '') . ' ' . ($r['unit_minat'] ?? '')), $r['tanggal_followup']);

        usort($tunggakan, fn($a, $b) => $a['sisa'] <=> $b['sisa']);
        $jml_telat = count(array_filter($tunggakan, fn($i) => $i['sisa'] < 0));

        // ---- Highlight ----
        $highlight = [];
        if (!empty($rekap)) {
            $top_booking = $rekap[0];
            foreach ($rekap as $r) if ($r['booking'] > $top_booking['booking']) $top_booking = $r;
            if ($top_booking['booking'] > 0) $highlight[] = 'Cluster paling banyak booking: ' . $top_booking['cluster'] . ' (' . $top_booking['booking'] . ' unit)';

            $top_kas = $rekap[0];
            foreach ($rekap as $r) if ($r['kas'] > $top_kas['kas']) $top_kas = $r;
            if ($top_kas['kas'] > 0) $highlight[] = 'Kas masuk terbesar: ' . $top_kas['cluster'] . ' (' . lm_rupiah($top_kas['kas']) . ')';
        }
        if (!empty($sumber)) {
            $s = array_key_first($sumber);
            $highlight[] = 'Sumber prospek terbanyak: ' . $s . ' (' . $sumber[$s] . ' prospek)';
        }
        if (!empty($keg_kategori)) {
            $k = array_key_first($keg_kategori);
            $highlight[] = 'Kegiatan terbanyak: kategori ' . $k . ' (' . $keg_kategori[$k] . ' laporan)';
        }
        if ($jml_telat > 0) $highlight[] = $jml_telat . ' item melewati tenggat dan perlu tindak lanjut.';

        return [
            'start' => $start,
            'end'   => $end,
            'kpi'   => [
                'booking' => $booking, 'akad' => $akad,
                'kas_masuk' => $kas_masuk, 'pengeluaran' => $pengeluaran, 'pemasukan' => $pemasukan,
                'saldo' => $kas_masuk - $pengeluaran,
                'prospek_baru' => $prospek_baru, 'followup' => $followup, 'berkas' => $berkas,
            ],
            'rekap'        => $rekap,
            'kegiatan'     => $keg,
            'keg_kategori' => $keg_kategori,
            'sumber'       => $sumber,
            'tunggakan'    => $tunggakan,
            'jml_telat'    => $jml_telat,
            'highlight'    => $highlight,
        ];
    }
}
