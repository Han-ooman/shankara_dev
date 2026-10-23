<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="theme-color" content="#1e3a8a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Shankara Dev">
<?php if (!empty($_SESSION['csrf_token'])): ?>
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<?php endif; ?>
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="assets/images/favicon/icon-192.png">
<link rel="stylesheet" href="assets/css/app.css">
<script defer src="assets/js/push_client.js"></script>

<!-- Vercel Web Analytics -->
<script>
  window.va = window.va || function () { (window.vaq = window.vaq || []).push(arguments); };
</script>
<script defer src="/_vercel/insights/script.js"></script>