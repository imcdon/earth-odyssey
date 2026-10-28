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
            <p class="footer-powered">Powered by <a href="https://webstarbusinessservices.com" target="_blank" rel="noopener noreferrer">Webstar Business Services</a></p>
        </div>
    </footer>
</body>
</html>
