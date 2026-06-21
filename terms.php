<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trust, Terms & Fees | MILELE</title>
    <style>
        body { background: #050505; color: #ccc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; line-height: 1.7;}
        .container { max-width: 800px; margin: 0 auto; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 50px; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.5);}
        .nav-bar { margin-bottom: 40px; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-size: 0.9rem; transition: 0.3s;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
        
        h1 { color: #fff; font-size: 2.5rem; margin-bottom: 10px; }
        .subtitle { color: #2DD4BF; font-size: 1.1rem; margin-bottom: 40px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;}
        
        h2 { color: #fff; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-top: 50px; }
        .highlight-box { background: rgba(45,212,191,0.05); border-left: 4px solid #2DD4BF; padding: 20px; border-radius: 0 12px 12px 0; margin: 30px 0; color: #fff;}
        
        .fee-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 25px; border-radius: 16px; margin: 20px 0; display: flex; gap: 20px; align-items: center;}
        .fee-icon { font-size: 2.5rem; }
        
        strong { color: #fff; }
        ul { padding-left: 20px; margin-top: 10px; }
        li { margin-bottom: 10px; }

        .faq-grid { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; }
        .faq-item { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 12px; }
        .faq-q { color: #2DD4BF; font-weight: bold; font-size: 1.05rem; margin-bottom: 8px; }
        .faq-a { color: #aaa; font-size: 0.95rem; line-height: 1.6; margin: 0; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <a href="index.php" class="btn-glass">← Back to Market</a>
    </div>

    <h1>Trust & Legal</h1>
    <div class="subtitle">Platform Rules, Fees & Escrow Policy</div>

    <p>Welcome to MILELE. We believe in total transparency. By using this platform to buy or sell items, you agree to the following terms designed to keep every transaction 100% secure.</p>

    <h2>1. The "Campus Cap" Fee Structure</h2>
    <p>We only make money when you successfully sell an item. There are no listing fees, no subscription fees, and no hidden charges.</p>
    
    <div class="fee-box">
        <div class="fee-icon">🛒</div>
        <div>
            <strong style="color: #2DD4BF; font-size: 1.2rem;">For Buyers: 100% Free</strong><br>
            <span style="color: #aaa; font-size: 0.95rem;">You pay exactly what the item costs. We do not charge you any service fees to lock your money in the escrow vault. Standard Safaricom M-Pesa sending rates apply.</span>
        </div>
    </div>

    <div class="fee-box">
        <div class="fee-icon">🤝</div>
        <div>
            <strong style="color: #2DD4BF; font-size: 1.2rem;">For Sellers: 3% (Capped at KES 500)</strong><br>
            <span style="color: #aaa; font-size: 0.95rem;">We deduct a tiny 3% success fee from your final payout. However, to protect students selling high-value items like laptops and phones, <strong>we will never charge you more than KES 500</strong> per transaction, regardless of the price.</span>
        </div>
    </div>

    <h2>2. The Escrow System</h2>
    <p>MILELE operates as an independent escrow agent via Safaricom M-Pesa. We hold the buyer's funds in a secure cloud vault. Funds are only released to the seller when the buyer physically receives the item and hands over their unique 4-digit Handover PIN.</p>

    <div class="highlight-box">
        <strong>The Golden Rule:</strong> Never hand over your 4-digit PIN until you have physically inspected the item and are satisfied with your purchase. The moment you provide the PIN to the seller, the sale is final.
    </div>

    <h2>3. Prohibited Items</h2>
    <p>To keep the campus community safe, the following items are strictly banned from the MILELE marketplace. Any attempt to list these will result in an immediate and permanent account ban:</p>
    <ul>
        <li>Weapons of any kind (including pocket knives and self-defense tools).</li>
        <li>Illegal drugs, narcotics, alcohol, or prescription medications.</li>
        <li>Academic cheating materials (e.g., stolen exam papers, paid assignment services).</li>
        <li>Counterfeit currency, stolen goods, or hacked electronic devices.</li>
    </ul>

    <h2 id="disputes">4. Dispute Resolution & Emergency Freeze</h2>
    <p>If a meetup goes wrong, buyers can trigger an <strong>Emergency Freeze</strong> via their profile. This locks the funds in the vault and alerts the MILELE Executive Admin Team.</p>
    <p>The Admin will investigate the situation by contacting both parties via the platform inbox or provided phone numbers. The Admin holds ultimate authority to either refund the buyer or force the payout to the seller based on the evidence. Attempting to scam the escrow system will result in a ban and potential reporting to campus authorities.</p>

    <h2>5. Frequently Asked Questions</h2>
    
    <div class="faq-grid">
        <div class="faq-item">
            <div class="faq-q">What if I forget or lose my 4-digit Handover PIN?</div>
            <p class="faq-a">Your PIN is permanently saved in your MILELE Profile. Go to "My Profile" and look at the "Items I've Bought" section. You can view it securely at any time before the handover.</p>
        </div>
        
        <div class="faq-item">
            <div class="faq-q">How long do refunds take if a meetup fails?</div>
            <p class="faq-a">If you trigger an Emergency Freeze and the Admin approves your refund, the money is reversed directly to your M-Pesa account instantly via Safaricom B2C. You do not have to wait for banking days.</p>
        </div>

        <div class="faq-item">
            <div class="faq-q">What if the seller tries to force me to give them the PIN?</div>
            <p class="faq-a">If you feel unsafe or pressured, walk away immediately. Your money cannot be touched without the PIN. Once you are safe, click "Report Issue" in your profile to freeze the transaction and notify the Admin.</p>
        </div>

        <div class="faq-item">
            <div class="faq-q">Where should we meet to complete the handover?</div>
            <p class="faq-a">We strongly mandate that all physical handovers occur during daylight hours in public, highly visible areas on campus (such as the Student Center, Library, or Cafeteria). Never agree to meet in private dorm rooms or off-campus locations.</p>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>

</body>
</html>