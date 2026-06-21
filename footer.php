<style>
    /* Premium Global Footer */
    .milele-footer { margin-top: 80px; padding: 60px 20px 30px; border-top: 1px solid rgba(255,255,255,0.05); background: #020202; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;}
    .footer-content { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 40px; margin-bottom: 40px; }
    .footer-brand h2 { color: #2DD4BF; margin: 0 0 15px 0; font-size: 1.8rem; letter-spacing: -1px; font-weight: 800;}
    .footer-brand p { color: #888; font-size: 0.9rem; line-height: 1.6; }
    .footer-links h3 { color: #fff; font-size: 1.1rem; margin: 0 0 20px 0; }
    .footer-links a { display: block; color: #888; text-decoration: none; margin-bottom: 12px; font-size: 0.9rem; transition: 0.2s; }
    .footer-links a:hover { color: #2DD4BF; transform: translateX(5px); }
    .footer-bottom { max-width: 1200px; margin: 0 auto; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.05); text-align: center; color: #555; font-size: 0.85rem; }
    .kx-badge { color: #2DD4BF; font-weight: bold; text-decoration: none;}
</style>

<footer class="milele-footer">
    <div class="footer-content">
        <div class="footer-brand">
            <h2>MILELE</h2>
            <p>The definitive campus marketplace powered by secure Safaricom M-Pesa Escrow. Buy safe. Sell fast. Zero scams.</p>
        </div>
        <div class="footer-links">
            <h3>Marketplace</h3>
            <a href="index.php">Global Feed</a>
            <a href="post_item.php">Sell an Item</a>
            <a href="profile.php">My Dashboard</a>
        </div>
        <div class="footer-links">
            <h3>Trust & Legal</h3>
            <a href="terms.php">How Escrow Works</a>
            <a href="terms.php">Terms of Service</a>
            <a href="terms.php#disputes">Dispute Policy</a>
        </div>
        <div class="footer-links">
            <h3>Support</h3>
            <a href="mailto:kibeta425@gmail.com">Contact Admin</a>
            <a href="profile.php">Report an Issue</a>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?php echo date('Y'); ?> MILELE Campus Market. All rights reserved. Engineered by <span class="kx-badge">KXBET</span>.
    </div>
</footer>