<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In — MILELE Campus</title>
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
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .glass-input {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
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

    <div class="absolute top-0 right-1/4 w-96 h-96 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-1/4 w-96 h-96 bg-purple-500/5 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-12 glass-panel rounded-3xl overflow-hidden shadow-2xl min-h-[600px]">
        
        <div class="hidden lg:flex lg:col-span-5 relative bg-neutral-900 flex-col justify-between p-8 border-r border-white/5 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-t from-[#0A0A0C] via-transparent to-black/50 z-10"></div>
            <div class="absolute inset-0 opacity-40 bg-cover bg-center transition-transform duration-1000 hover:scale-105" style="background-image: url('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&q=80&w=800');"></div>

            <div class="relative z-20">
                <span class="text-xs font-semibold tracking-widest text-white/50 uppercase">Welcome Back</span>
            </div>

            <div class="relative z-20 mt-auto">
                <h1 class="text-3xl font-bold tracking-tight text-white mb-2">MILELE</h1>
                <p class="text-sm text-gray-300 leading-relaxed">Pick up right where you left off. Access your escrow vault, messages, and campus deals.</p>
            </div>
        </div>

        <div class="col-span-1 lg:col-span-7 flex flex-col justify-center p-6 sm:p-10 lg:p-12">
            
            <div class="mb-8">
                <h2 class="text-2xl font-semibold tracking-tight text-white mb-1">Log In</h2>
                <p class="text-sm text-gray-400">Enter your student email and password to continue.</p>
            </div>

            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="mb-6 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-sm text-red-400 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?php 
                        echo htmlspecialchars($_SESSION['error_msg']); 
                        unset($_SESSION['error_msg']); 
                    ?>
                </div>
            <?php endif; ?>

            <form action="login_process.php" method="POST" class="space-y-6">
                
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-400 mb-2">Student Email</label>
                    <input type="email" name="email" required placeholder="name@student.university.ac.ke" 
                           class="w-full px-4 py-3 rounded-xl glass-input text-sm text-white placeholder-gray-600 focus:ring-0">
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-400">Password</label>
                        <a href="#" class="text-xs text-blue-400 hover:text-blue-300 transition-colors">Forgot it?</a>
                    </div>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full px-4 py-3 rounded-xl glass-input text-sm text-white placeholder-gray-600 focus:ring-0">
                </div>

                <button type="submit" 
                        class="w-full mt-2 bg-white text-black font-semibold text-sm py-3.5 px-4 rounded-xl shadow-lg hover:bg-gray-100 transition-all duration-200 active:scale-[0.98]">
                    Access Account
                </button>

            </form>

            <div class="mt-8 pt-6 border-t border-white/5 text-center">
                <p class="text-xs text-gray-400">
                    New to MILELE? 
                    <a href="register.php" class="text-white font-medium hover:underline ml-1">Create an account</a>
                </p>
            </div>

        </div>
    </div>

</body>
</html>