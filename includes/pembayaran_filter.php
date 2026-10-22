<?php
// Helper filter & query Riwayat Kwitansi Pembayaran.
// Dipakai oleh pembayaran.php agar logic filter terpusat seperti konsumen_filter.php.

if (!function_exists('pembayaran_get_filters')) {

    // Ambil & bersihkan parameter filter dari $_GET
    function pembayaran_get_filters()
    {
        $g = function ($key) {
            return isset($_GET[$key]) ? trim((string) $_GET[$key]) : '';
        };
        $tgl = function ($v) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        };
        return [
            'keyword'    => $g('keyword'),
            'arus'       => $g('arus'),
            'jenis'      => $g('jenis'),
            'metode'     => $g('metode'),
            'tgl_dari'   => $tgl($g('tgl_dari')),
            'tgl_sampai' => $tgl($g('tgl_sampai')),
            'sort'       => $g('sort'),
        ];
    }

    function pembayaran_filter_active($f)
    {
        foreach (['keyword', 'arus', 'jenis', 'metode', 'tgl_dari', 'tgl_sampai', 'sort'] as $k) {
            if ($f[$k] !== '') {
                return true;
            }
        }
        return false;
    }

    // Bangun SQL lengkap (SELECT + WHERE + ORDER BY)
    function pembayaran_build_query($koneksi, $f)
    {
        $esc = function ($v) use ($koneksi) {
            return mysqli_real_escape_string($koneksi, $v);
        };

        $q = "SELECT p.*, "
            . "COALESCE(MAX(k.name_lengkap), p.nama_pembayar, '-') AS nama_pembayar_tampil, "
            . "GROUP_CONCAT(DISTINCT r.no_blok ORDER BY r.no_blok SEPARATOR ', ') AS daftar_blok "
            . "FROM pembayaran p "
            . "LEFT JOIN data_pembayaran_detail pd ON pd.id_pembayaran = p.id_pembayaran "
            . "LEFT JOIN data_transaksi t ON pd.id_transaksi = t.id_transaksi "
            . "LEFT JOIN data_konsumen k ON t.id_konsumen = k.id_konsumen "
            . "LEFT JOIN data_rumah r ON t.id_rumah = r.id_rumah "
            . "WHERE 1=1";

        if ($f['keyword'] !== '') {
            $kw = $esc($f['keyword']);
            $q .= " AND (p.no_kwitansi LIKE '%$kw%'"
                . " OR p.nama_pembayar LIKE '%$kw%'"
                . " OR p.keterangan LIKE '%$kw%'"
                . " OR EXISTS (SELECT 1 FROM data_pembayaran_detail pdx"
                . " JOIN data_transaksi tx ON pdx.id_transaksi = tx.id_transaksi"
                . " LEFT JOIN data_konsumen kx ON tx.id_konsumen = kx.id_konsumen"
                . " LEFT JOIN data_rumah rx ON tx.id_rumah = rx.id_rumah"
                . " WHERE pdx.id_pembayaran = p.id_pembayaran"
                . " AND (kx.name_lengkap LIKE '%$kw%' OR rx.no_blok LIKE '%$kw%')))";
        }

        if ($f['arus'] === 'pemasukan') {
            $q .= " AND p.sumber_pembayaran = 'Unit Rumah'";
        } elseif ($f['arus'] === 'pengeluaran') {
            $q .= " AND p.sumber_pembayaran = 'Lainnya'";
        }

        if ($f['jenis'] !== '') {
            $q .= " AND p.jenis_pembayaran = '" . $esc($f['jenis']) . "'";
        }

        if ($f['metode'] === 'Transfer') {
            $q .= " AND p.metode_pembayaran = 'Transfer'";
        } elseif ($f['metode'] === 'Cash') {
            $q .= " AND p.metode_pembayaran = 'Cash'";
        }

        if ($f['tgl_dari'] !== '') {
            $q .= " AND p.tgl_bayar >= '" . $esc($f['tgl_dari']) . "'";
        }
        if ($f['tgl_sampai'] !== '') {
            $q .= " AND p.tgl_bayar <= '" . $esc($f['tgl_sampai']) . "'";
        }

        switch ($f['sort']) {
            case 'kw_asc':
                $q .= " GROUP BY p.id_pembayaran ORDER BY p.id_pembayaran ASC";
                break;
            case 'jumlah_desc':
                $q .= " GROUP BY p.id_pembayaran ORDER BY p.jumlah_bayar DESC";
                break;
            case 'jumlah_asc':
                $q .= " GROUP BY p.id_pembayaran ORDER BY p.jumlah_bayar ASC";
                break;
            default:
                $q .= " GROUP BY p.id_pembayaran ORDER BY p.id_pembayaran DESC";
        }

        return $q;
    }

    function pembayaran_list($koneksi, $f)
    {
        $rows = [];
        $res = mysqli_query($koneksi, pembayaran_build_query($koneksi, $f));
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
        }
        return $rows;
    }

    // Total semua kwitansi (tanpa filter) untuk ringkasan
    function pembayaran_jumlah_total($koneksi)
    {
        $res = mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM data_pembayaran");
        return ($res && ($row = mysqli_fetch_assoc($res))) ? (int) $row['jml'] : 0;
    }

    // Daftar jenis pembayaran unik yang sudah dipakai di data
    function pembayaran_daftar_jenis($koneksi)
    {
        $out = [];
        $res = mysqli_query($koneksi, "SELECT DISTINCT jenis_pembayaran FROM data_pembayaran WHERE jenis_pembayaran IS NOT NULL AND jenis_pembayaran <> '' ORDER BY jenis_pembayaran ASC");
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $out[] = $r['jenis_pembayaran'];
            }
        }
        return $out;
    }

    // Label filter aktif (untuk ringkasan di layar / header cetak)
    function pembayaran_filter_labels($f)
    {
        $labels = [];
        if ($f['keyword'] !== '') {
            $labels[] = 'Keyword: "' . $f['keyword'] . '"';
        }
        if ($f['arus'] === 'pemasukan') {
            $labels[] = 'Arus: Pemasukan';
        } elseif ($f['arus'] === 'pengeluaran') {
            $labels[] = 'Arus: Pengeluaran';
        }
        if ($f['jenis'] !== '') {
            $labels[] = 'Jenis: ' . $f['jenis'];
        }
        if ($f['metode'] !== '') {
            $labels[] = 'Metode: ' . ($f['metode'] === 'Cash' ? 'Cash / Tunai' : 'Transfer Bank');
        }
        if ($f['tgl_dari'] !== '') {
            $labels[] = 'Dari: ' . $f['tgl_dari'];
        }
        if ($f['tgl_sampai'] !== '') {
            $labels[] = 'Sampai: ' . $f['tgl_sampai'];
        }
        $map_sort = [
            'kw_asc'      => 'Kwitansi Terlama',
            'jumlah_desc' => 'Jumlah Terbesar',
            'jumlah_asc'  => 'Jumlah Terkecil',
        ];
        if (isset($map_sort[$f['sort']])) {
            $labels[] = 'Urut: ' . $map_sort[$f['sort']];
        }
        return $labels;
    }
}
