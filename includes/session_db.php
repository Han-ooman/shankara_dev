<?php
// session_db.php — Penyimpanan sesi PHP ke DATABASE (bukan file /tmp).
//
// Latar: di Vercel (serverless), file sesi PHP di /tmp hanya hidup per-instance
// function. Saat request berikutnya mendarat di instance lain, session_start()
// tidak menemukan file sesi -> sesi kosong -> user "logout" sendiri
// (contoh nyata: klik "Simpan" di Data Konsumen langsung terlempar ke login).
//
// Solusi: session_set_save_handler menulis sesi ke tabel `php_sessions`
// (Supabase saat mode online / MySQL saat offline) sehingga sesi tetap valid
// dari instance mana pun.
//
// File ini DIAMAN dipakai berulang dari banyak file (guard + status sesi):
//   - di-require dari includes/security.php (mencakup semua halaman yang
//     include koneksi.php sebelum session_start, termasuk login.php)
//   - di-require di awal api/index.php (dispatcher Vercel, sebelum target page)
//   - di-require manual di halaman yang session_start() sebelum include koneksi
//     (mis. print/export_*.php, print/cetak_*.php)

if (!class_exists('SdbDbSessionHandler')) {

    class SdbDbSessionHandler implements SessionHandlerInterface
    {
        /** @var sdb|null */
        private $db = null;

        private function conn()
        {
            if ($this->db === null) {
                // Pakai koneksi aplikasi bila sudah tersedia (hemat koneksi bareng)
                if (isset($GLOBALS['koneksi']) && is_object($GLOBALS['koneksi'])
                    && method_exists($GLOBALS['koneksi'], 'ping') && $GLOBALS['koneksi']->ping()) {
                    $this->db = $GLOBALS['koneksi'];
                } else {
                    require_once __DIR__ . '/db_adapter.php';
                    $this->db = (DB_MODE === 'online') ? sdb_connect_pgsql() : sdb_connect_mysql();
                }
            }
            return $this->db;
        }

        #[\ReturnTypeWillChange]
        public function open($save_path, $name)
        {
            return $this->conn() !== null;
        }

        #[\ReturnTypeWillChange]
        public function close()
        {
            return true;
        }

        #[\ReturnTypeWillChange]
        public function read($session_id)
        {
            $db = $this->conn();
            if (!$db) return '';
            $id_e = $db->real_escape_string($session_id);
            $q = $db->query("SELECT session_data FROM php_sessions WHERE session_id = '$id_e'");
            if (!$q) return '';
            $r = $q->fetch_assoc();
            return $r ? (string) ($r['session_data'] ?? '') : '';
        }

        #[\ReturnTypeWillChange]
        public function write($session_id, $session_data)
        {
            $db = $this->conn();
            if (!$db) return false;
            $id_e   = $db->real_escape_string($session_id);
            $data_e = $db->real_escape_string($session_data);
            $driver = $db->getDriver();
            if ($driver === 'mysql') {
                return (bool) $db->query(
                    "INSERT INTO php_sessions (session_id, session_data, updated_at) VALUES ('$id_e', '$data_e', NOW())
                     ON DUPLICATE KEY UPDATE session_data = VALUES(session_data), updated_at = NOW()"
                );
            } elseif ($driver === 'turso') {
                // SQLite: NOW() -> CURRENT_TIMESTAMP, EXCLUDED not supported, use column directly
                return (bool) $db->query(
                    "INSERT INTO php_sessions (session_id, session_data, updated_at) VALUES ('$id_e', '$data_e', CURRENT_TIMESTAMP)
                     ON CONFLICT (session_id) DO UPDATE SET session_data = '$data_e', updated_at = CURRENT_TIMESTAMP"
                );
            }
            // Postgres / other
            return (bool) $db->query(
                "INSERT INTO php_sessions (session_id, session_data, updated_at) VALUES ('$id_e', '$data_e', NOW())
                 ON CONFLICT (session_id) DO UPDATE SET session_data = EXCLUDED.session_data, updated_at = NOW()"
            );
        }

        #[\ReturnTypeWillChange]
        public function destroy($session_id)
        {
            $db = $this->conn();
            if (!$db) return false;
            $id_e = $db->real_escape_string($session_id);
            return (bool) $db->query("DELETE FROM php_sessions WHERE session_id = '$id_e'");
        }

        #[\ReturnTypeWillChange]
        public function gc($max_lifetime)
        {
            $db = $this->conn();
            if (!$db) return 0;
            $max = max(60, (int) $max_lifetime);
            $driver = $db->getDriver();
            if ($driver === 'mysql') {
                $db->query("DELETE FROM php_sessions WHERE updated_at < (NOW() - INTERVAL $max SECOND)");
            } elseif ($driver === 'turso') {
                $db->query("DELETE FROM php_sessions WHERE updated_at < datetime('now', '-{$max} seconds')");
            } else {
                $db->query("DELETE FROM php_sessions WHERE updated_at < NOW() - INTERVAL '$max seconds'");
            }
            return 0;
        }
    }
}

// Daftarkan handler SEKALI, hanya saat sesi belum berjalan & belum ter-registrasi.
if (session_status() === PHP_SESSION_NONE && empty($GLOBALS['SDB_DB_SESSION_READY'])) {
    $GLOBALS['SDB_DB_SESSION_READY'] = true;
    session_set_save_handler(new SdbDbSessionHandler(), true);
}
