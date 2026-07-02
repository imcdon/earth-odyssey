<?php
/*
 * footer.php - Site footer and closing HTML tags.
 * Expects $company_name from config.php.
 */
?>
    </main>
    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($company_name) ?>. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
