<?php
require_once __DIR__ . '/functions.php';
$flash = get_flash();
?>
</main>

<footer class="site-footer">
    <div class="site-footer__inner">
        <p>Projet L2 MIAGE — <?= date('Y') ?></p>
        <p class="site-footer__note">Papier chaud, encre et un peu de PHP.</p>
    </div>
</footer>

<div class="toast-stack" id="toast-stack">
    <?php if ($flash): ?>
    <div class="toast toast--<?= e($flash['type']) ?> is-visible" role="status" data-toast>
        <p class="toast__msg"><?= e($flash['message']) ?></p>
    </div>
    <?php endif; ?>
</div>

<script src="public/js/app.js"></script>
</body>
</html>
