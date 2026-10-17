<?php
// Helper filter & query Data Konsumen.
// Dipakai bersama oleh konsumen.php, print/cetak_konsumen.php, dan print/export_konsumen.php
// agar hasil di layar = hasil saat dicetak/diexport.

if (!function_exists('konsumen_status_sql')) {
    // Ekspresi subquery status transaksi terbaru per konsumen (alias tabel: k)
    function konsumen_status_sql()
    {
        return "(SELECT t.status_konsumen FROM data_transaksi t WHERE t.id_konsumen = k.id_konsumen ORDER BY t.id_transaksi DESC LIMIT 1)";
    }

    function konsumen_bi_sql()
    {
        return "(SELECT b.hasil_bi FROM data_bi_checking b WHERE b.id_konsumen = k.id_konsumen ORDER BY b.tgl_cek DESC, b.id_bi_checking DESC LIMIT 1)";
    }

    function konsumen_bi_tgl_sql()
    {
        return "(SELECT b.tgl_cek FROM data_bi_checking b WHERE b.id_konsumen = k.id_konsumen ORDER BY b.tgl_cek DESC, b.id_bi_checking DESC LIMIT 1)";
    }

    // Ambil & bersihkan parameter filter dari $_GET
    function konsumen_get_filters()
    {
        $g = function ($key) {
            return isset($_GET[$key]) ? trim((string) $_GET[$key]) : '';
        };
        return [
            'keyword'   => $g('keyword'),
            'pekerjaan' => $g('pekerjaan'),
            'wa'        => $g('wa'),
            'ktp'       => $g('ktp'),
            'bi'        => $g('bi'),
            'transaksi' => $g('transaksi'),
            'sort'      => $g('sort'),
        ];
    }

    function konsumen_filter_active($f)
    {
        foreach (['keyword', 'pekerjaan', 'wa', 'ktp', 'bi', 'transaksi', 'sort'] as $k) {
            if ($f[$k] !== '') {
                return true;
            }
        }
        return false;
    }

    // Bangun SQL lengkap (SELECT + WHERE + ORDER BY)
    function konsumen_build_query($koneksi, $f)
    {
        $esc = function ($v) use ($koneksi) {
            return mysqli_real_escape_string($koneksi, $v);
        };

        $bi_expr = konsumen_bi_sql();
        $st_expr = konsumen_status_sql();

        $q = "SELECT k.*, "
            . $bi_expr . " AS bi_status, "
            . konsumen_bi_tgl_sql() . " AS bi_tgl, "
            . $st_expr . " AS status_transaksi "
            . "FROM data_konsumen k WHERE 1=1";

        if ($f['keyword'] !== '') {
            $kw = $esc($f['keyword']);
            $q .= " AND (k.id_konsumen LIKE '%$kw%'"
                . " OR k.name_lengkap LIKE '%$kw%'"
                . " OR k.pekerjaan LIKE '%$kw%'"
                . " OR k.no_whatsapp LIKE '%$kw%'"
                . " OR k.no_ktp LIKE '%$kw%'"
                . " OR k.alamat_ktp LIKE '%$kw%')";
        }

        if ($f['pekerjaan'] !== '') {
            $q .= " AND k.pekerjaan = '" . $esc($f['pekerjaan']) . "'";
        }

        if ($f['wa'] === 'ada') {
            $q .= " AND k.no_whatsapp IS NOT NULL AND k.no_whatsapp != '' AND k.no_whatsapp != '-'";
        } elseif ($f['wa'] === 'tidak') {
            $q .= " AND (k.no_whatsapp IS NULL OR k.no_whatsapp = '' OR k.no_whatsapp = '-')";
        }

        if ($f['ktp'] === 'ada') {
            $q .= " AND k.no_ktp IS NOT NULL AND k.no_ktp != '' AND k.no_ktp != '-'";
        } elseif ($f['ktp'] === 'tidak') {
            $q .= " AND (k.no_ktp IS NULL OR k.no_ktp = '' OR k.no_ktp = '-')";
        }

        $map_bi = [
            'lulus'           => 'Lulus',
            'dalam_proses'    => 'Dalam Proses',
            'dipertimbangkan' => 'Dipertimbangkan',
            'tidak_lulus'     => 'Tidak Lulus',
        ];
        if ($f['bi'] === 'belum') {
            $q .= " AND $bi_expr IS NULL";
        } elseif (isset($map_bi[$f['bi']])) {
            $q .= " AND $bi_expr = '" . $esc($map_bi[$f['bi']]) . "'";
        }

        if ($f['transaksi'] === 'belum') {
            $q .= " AND $st_expr IS NULL";
        } elseif ($f['transaksi'] === 'proses') {
            $q .= " AND $st_expr = 'PROSES'";
        } elseif ($f['transaksi'] === 'acc') {
            $q .= " AND $st_expr = 'ACC - AKAD'";
        } elseif ($f['transaksi'] === 'batal') {
            $q .= " AND $st_expr = 'BATAL'";
        }

        switch ($f['sort']) {
            case 'nama_desc':
                $q .= " ORDER BY k.name_lengkap DESC";
                break;
            case 'id_desc':
                $q .= " ORDER BY k.id_konsumen DESC";
                break;
            case 'id_asc':
                $q .= " ORDER BY k.id_konsumen ASC";
                break;
            default:
                $q .= " ORDER BY k.name_lengkap ASC";
        }

        return $q;
    }

    function konsumen_list($koneksi, $f)
    {
        $rows = [];
        $res = mysqli_query($koneksi, konsumen_build_query($koneksi, $f));
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
        }
        return $rows;
    }

    // Daftar pekerjaan unik untuk dropdown
    function konsumen_daftar_pekerjaan($koneksi)
    {
        $out = [];
        $res = mysqli_query($koneksi, "SELECT DISTINCT pekerjaan FROM data_konsumen WHERE pekerjaan IS NOT NULL AND pekerjaan != '' ORDER BY pekerjaan ASC");
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $out[] = $r['pekerjaan'];
            }
        }
        return $out;
    }

    // Label filter aktif (untuk header cetak/export)
    function konsumen_filter_labels($f)
    {
        $labels = [];
        if ($f['keyword'] !== '') {
            $labels[] = 'Keyword: "' . $f['keyword'] . '"';
        }
        if ($f['pekerjaan'] !== '') {
            $labels[] = 'Pekerjaan: ' . $f['pekerjaan'];
        }
        if ($f['wa'] === 'ada') {
            $labels[] = 'WhatsApp: Ada';
        } elseif ($f['wa'] === 'tidak') {
            $labels[] = 'WhatsApp: Tidak Ada';
        }
        if ($f['ktp'] === 'ada') {
            $labels[] = 'No. KTP: Ada';
        } elseif ($f['ktp'] === 'tidak') {
            $labels[] = 'No. KTP: Tidak Ada';
        }
        $map_bi = [
            'lulus'           => 'Lulus',
            'dalam_proses'    => 'Dalam Proses',
            'dipertimbangkan' => 'Dipertimbangkan',
            'tidak_lulus'     => 'Tidak Lulus',
            'belum'           => 'Belum Dicek',
        ];
        if (isset($map_bi[$f['bi']])) {
            $labels[] = 'BI Checking: ' . $map_bi[$f['bi']];
        }
        $map_tx = [
            'belum'  => 'Belum Ada Transaksi',
            'proses' => 'Proses',
            'acc'    => 'ACC - Akad',
            'batal'  => 'Batal',
        ];
        if (isset($map_tx[$f['transaksi']])) {
            $labels[] = 'Status Transaksi: ' . $map_tx[$f['transaksi']];
        }
        return $labels;
    }

    // Label status transaksi untuk ditampilkan di tabel
    function konsumen_status_badge_data($status)
    {
        $s = $status === null ? '' : trim((string) $status);
        switch ($s) {
            case 'ACC - AKAD':
                return ['bg' => 'bg-primary text-white', 'ikon' => 'bi-patch-check-fill', 'label' => 'ACC - Akad'];
            case 'PROSES':
                return ['bg' => 'bg-warning text-dark', 'ikon' => 'bi-hourglass-split', 'label' => 'Proses'];
            case 'BATAL':
                return ['bg' => 'bg-danger text-white', 'ikon' => 'bi-x-octagon-fill', 'label' => 'Batal'];
            default:
                return ['bg' => 'bg-light text-secondary border', 'ikon' => 'bi-dash-circle', 'label' => 'Belum Ada'];
        }
    }
}
