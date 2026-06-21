<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trust & Terms | MILELE</title>
    <style>
        body { background: #050505; color: #ccc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; line-height: 1.7;}
        .container { max-width: 800px; margin: 0 auto; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 50px; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.5);}
        .nav-bar { margin-bottom: 40px; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-size: 0.9rem; transition: 0.3s;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
        
        h1 { color: #fff; font-size: 2.5rem; margin-bottom: 10px; }
        .subtitle { color: #2DD4BF; font-size: 1.1rem; margin-bottom: 40px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;}
        
        h2 { color: #fff; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-top: 40px; }
        .highlight-box { background: rgba(45,212,191,0.05); border-left: 4px solid #2DD4BF; padding: 20px; border-radius: 0 12px 12px 0; margin: 30px 0; color: #fff;}
        strong { color: #fff; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <a href="index.php" class="btn-glass">← Back to Market</a>
    </div>

    <h1>Trust & Legal</h1>
    <div class="subtitle">Platform Rules & Escrow Policy</div>

    <p>Welcome to MILELE. By using this platform to buy or sell items, you agree to the following terms designed to keep every transaction 100% secure.</p>

    <h2>1. The Escrow System</h2>
    <p>MILELE operates as an independent escrow agent via Safaricom M-Pesa. We hold the buyer's funds in a secure cloud vault. Funds are only released to the seller when the buyer physically receives the item and hands over their unique 4-digit Handover PIN.</p>

    <div class="highlight-box">
        <strong>The Golden Rule:</strong> Never hand over your 4-digit PIN until you have physically inspected the item and are satisfied with your purchase. The moment you provide the PIN, the sale is final.
    </div>

    <h2>2. Buyer Responsibilities</h2>
    <ul>
        <li>You must inspect the item thoroughly during the physical meetup.</li>
        <li>MILELE is not responsible for the physical condition, authenticity, or warranty of the items sold. We only guarantee the security of the funds.</li>
        <li>If an item does not match the description, do NOT give the seller your PIN. Walk away, and click "Report Issue" to instantly reverse your funds.</li>
    </ul>

    <h2>3. Seller Responsibilities</h2>
    <ul>
        <li>You must ensure your listing is accurate and truthful.</li>
        <li>Do not hand over the item to the buyer until they provide the 4-digit PIN, and you have successfully entered it into your dashboard to clear the funds.</li>
    </ul>

    <h2 id="disputes">4. Dispute Resolution & Emergency Freeze</h2>
    <p>If a meetup goes wrong (e.g., the seller doesn't show up, or the item is defective), buyers can trigger an <strong>Emergency Freeze</strong> via their profile. This locks the funds in the vault and alerts the MILELE Executive Admin Team.</p>
    <p>The Admin will investigate the situation by contacting both parties. The Admin holds the ultimate authority to either refund the buyer or force the payout to the seller based on the evidence provided. Attempting to scam the escrow system will result in a permanent ban and potential reporting to campus authorities.</p>

    <h2>5. Meetup Safety Guidelines</h2>
    <p>We mandate that all physical handovers occur during daylight hours in public, highly visible areas on campus (e.g., the student center or library). Never meet in private residences or off-campus locations.</p>
</div>

<?php include 'footer.php'; ?>

</body>
</html>