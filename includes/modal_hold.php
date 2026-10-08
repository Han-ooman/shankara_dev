<?php
// Snippet notifikasi HOLD — include di bawah <body> setiap halaman yang
// bisa memicu blokir (transaksi.php, pembayaran.php, rencana_akad.php, index.php).
// Menampilkan modal Bootstrap otomatis bila URL memuat ?status=hold
if (isset($_GET['status']) && $_GET['status'] === 'hold') {
    $hold_cluster = trim($_GET['cluster'] ?? '');
    $hold_pesan = $hold_cluster
        ? cluster_hold_pesan($hold_cluster)
        : 'Cluster yang dipilih sedang di HOLD oleh sistem, sehingga booking dan pembayaran baru tidak dapat dilakukan. Silakan hubungi Super Admin untuk membuka kembali cluster ini.';
?>
<div class="modal fade" id="modalClusterHold" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="py-4 text-center" style="background: linear-gradient(135deg,#b91c1c,#dc2626);">
                <i class="bi bi-ban text-white" style="font-size: 3rem;"></i>
            </div>
            <div class="modal-body text-center px-4 pt-4 pb-3">
                <h5 class="fw-bold text-danger mb-1">Cluster Sedang Dihold</h5>
                <?php if ($hold_cluster): ?>
                <p class="fw-semibold text-dark mb-2"><?= htmlspecialchars($hold_cluster, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <p class="text-muted small mb-0"><?= htmlspecialchars($hold_pesan, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 justify-content-center">
                <button type="button" class="btn btn-danger px-5 rounded-3" data-bs-dismiss="modal">Mengerti</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function tampilModalHold() {
        var el = document.getElementById('modalClusterHold');
        if (!el || typeof bootstrap === 'undefined') return;
        new bootstrap.Modal(el).show();
        // Bersihkan query ?status=hold agar modal tidak tampil ulang saat refresh
        if (window.history && window.history.replaceState) {
            var u = new URL(window.location.href);
            u.searchParams.delete('status');
            u.searchParams.delete('cluster');
            window.history.replaceState(null, '', u.toString());
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tampilModalHold);
    } else {
        tampilModalHold();
    }
})();
</script>
<?php } ?>