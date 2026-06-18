<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: If they are not logged in, kick them back to login.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Routing Check: If they are already verified, they shouldn't be here. Send them to the marketplace.
if ($_SESSION['account_state'] !== 'registered') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0A0A0C;
            overflow-x: hidden;
        }

        .glass-panel {
            background: rgba(18, 18, 22, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .glass-input {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            font-size: 2rem;
            letter-spacing: 0.5em;
            transition: all 0.3s ease;
        }

        .glass-input:focus {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.03);
            outline: none;
        }
    </style>
</head>
<body class="text-gray-100 min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">

    <div class="absolute top-0 right-1/4 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-2 glass-panel rounded-3xl overflow-hidden shadow-2xl min-h-[500px]">
        
        <div class="hidden lg:flex relative bg-neutral-900 p-8 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-t from-[#0A0A0C] to-transparent z-10"></div>
            <div class="absolute inset-0 opacity-40 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?auto=format&fit=crop&q=80&w=800');"></div>
            
            <div class="relative z-20 mt-auto">
                <div class="w-12 h-12 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-4 border border-white/10">
                    <span class="text-2xl">🎓</span>
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-white mb-2">Almost there, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>.</h2>
                <p class="text-sm text-gray-300 leading-relaxed">To keep the MILELE community secure, we need to confirm you actually have access to that student inbox.</p>
            </div>
        </div>

        <div class="flex flex-col justify-center p-8 sm:p-12">
            
            <div class="mb-8 text-center">
                <h2 class="text-2xl font-semibold tracking-tight text-white mb-2">Check your inbox</h2>
                <p class="text-sm text-gray-400">We sent a 4-digit security code to your student email. Enter it below to unlock the marketplace.</p>
            </div>

            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="mb-6 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-sm text-red-400 text-center">
                    <?php 
                        echo htmlspecialchars($_SESSION['error_msg']); 
                        unset($_SESSION['error_msg']); 
                    ?>
                </div>
            <?php endif; ?>

            <form action="verify_process.php" method="POST" class="space-y-6 flex flex-col items-center">
                
                <div class="w-full max-w-[240px]">
                    <input type="text" name="otp_code" maxlength="4" required autocomplete="off" placeholder="••••"
                           class="w-full py-4 rounded-2xl glass-input text-white placeholder-gray-600 font-bold focus:ring-0">
                </div>

                <button type="submit" 
                        class="w-full max-w-[240px] bg-white text-black font-semibold text-sm py-3.5 rounded-xl shadow-lg hover:bg-gray-100 transition-all duration-200">
                    Verify Email
                </button>

            </form>

            <div class="mt-8 text-center">
                <p class="text-xs text-gray-500">
                    Didn't get the email? <button class="text-gray-300 hover:text-white transition-colors">Click here to resend</button>
                </p>
                <div class="mt-6 pt-6 border-t border-white/5">
                    <a href="login.php" class="text-xs text-gray-500 hover:text-white transition-colors">Logout</a>
                </div>
            </div>

        </div>
    </div>

</body>
</html>