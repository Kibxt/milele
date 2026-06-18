<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Join MILELE — Campus Escrow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; }
        .glass-panel { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-input { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); transition: all 0.3s ease; }
        .glass-input:focus { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.3); outline: none; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 selection:bg-white/20">

    <div class="w-full max-w-md glass-panel rounded-[32px] p-8 sm:p-10 relative overflow-hidden">
        
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-gray-800 to-gray-700 border border-white/10 flex items-center justify-center text-xl mx-auto mb-4">🛍️</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Create your account</h1>
            <p class="text-sm text-gray-400 mt-2">Join the safest student marketplace.</p>
        </div>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="mb-6 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-sm text-red-400 text-center">
                <?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>

        <form action="auth_process.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="register">

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 pl-1">Full Name</label>
                <input type="text" name="full_name" required placeholder="Emmanuel Wonder" class="w-full px-5 py-3.5 rounded-xl glass-input text-sm text-white placeholder-gray-600">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 pl-1">Student Email</label>
                <input type="email" name="email" required placeholder="emmanuel@strathmore.edu" class="w-full px-5 py-3.5 rounded-xl glass-input text-sm text-white placeholder-gray-600">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 pl-1">M-Pesa Number</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 text-sm font-bold">+254</span>
                    <input type="tel" name="phone_number" required placeholder="712 345 678" pattern="[0-9]{9}" maxlength="9" class="w-full px-5 py-3.5 pl-14 rounded-xl glass-input text-sm text-white placeholder-gray-600">
                </div>
                <p class="text-[9px] text-gray-500 mt-1 pl-1">Required to receive instant payouts when you sell items.</p>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 pl-1">Select University</label>
                <select name="university_name" required class="w-full px-5 py-3.5 rounded-xl glass-input text-sm text-white appearance-none">
                    <option value="" class="bg-gray-900 text-gray-500">Choose your campus...</option>
                    <option value="Strathmore University" class="bg-gray-900">Strathmore University</option>
                    <option value="CUEA" class="bg-gray-900">CUEA</option>
                    <option value="University of Nairobi" class="bg-gray-900">University of Nairobi</option>
                    <option value="Kenyatta University" class="bg-gray-900">Kenyatta University</option>
                    <option value="JKUAT" class="bg-gray-900">JKUAT</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 pl-1">Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full px-5 py-3.5 rounded-xl glass-input text-sm text-white placeholder-gray-600">
            </div>

            <button type="submit" class="w-full bg-white text-black font-bold text-sm py-4 rounded-xl shadow-[0_0_20px_rgba(255,255,255,0.15)] hover:scale-[1.02] transition-transform mt-2">
                Create Account
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500">Already have an account? <a href="login.php" class="text-white font-bold hover:underline">Sign In</a></p>
        </div>
    </div>
</body>
</html>