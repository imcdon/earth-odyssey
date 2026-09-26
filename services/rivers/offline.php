<?php
/*
 * offline.php - Shown by the service worker (sw.js) when a page that was never saved is opened with no signal.
 * rivers.js fills in the saved rivers from the service worker.
 */
require __DIR__ . '/../../includes/config.php';

$page_theme = 'rivers';
$page_title = 'Offline | River Gauges | ' . $site_name;
require __DIR__ . '/../../includes/header.php';
?>

<div class="rivers-page" data-river-page="offline" data-api="<?= htmlspecialchars(url('services/rivers/api.php')) ?>">
    <header class="rivers-head">
        <div class="container">
            <p class="kicker">Stream gauges</p>
            <h1 class="rivers-title">You're offline</h1>
            <p class="rivers-intro">This page wasn't saved on your phone. These rivers are, with their readings and charts from the last time you had signal.</p>
        </div>
    </header>

    <section class="container rivers-list-section" aria-labelledby="saved-title">
        <h2 class="rivers-section-title" id="saved-title">Saved rivers</h2>
        <ul class="rivers-list rivers-saved-list" data-saved-list></ul>
        <p class="rivers-empty" data-saved-empty hidden>Nothing is saved yet. Open a river page or save a favorite while you have signal, and it will be here next time.</p>
    </section>
</div>

<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
