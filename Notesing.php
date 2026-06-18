<?php
// MILELE - Premium Creation Studio

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Post Item — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; }
        .glass-nav { background: rgba(10, 10, 12, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        
        .input-premium {
            width: 100%; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px; padding: 16px; color: white; font-size: 15px; outline: none; transition: all 0.3s;
        }
        .input-premium:focus { background: rgba(45, 212, 191, 0.05); border-color: #2DD4BF; box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.1); }
        .input-premium::placeholder { color: #6B7280; }
        
        /* Custom Type Toggle */
        .type-toggle { display: flex; background: rgba(255,255,255,0.05); border-radius: 12px; padding: 4px; border: 1px solid rgba(255,255,255,0.05); }
        .toggle-btn { flex: 1; text-align: center; padding: 10px; font-size: 12px; font-weight: 700; color: #9CA3AF; border-radius: 8px; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .toggle-btn.active { background: #fff; color: #000; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        
        /* Hidden file input trick */
        input[type="file"] { display: none; }
        .file-upload-box {
            border: 2px dashed rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.2s;
        }
        .file-upload-box:hover { border-color: #2DD4BF; background: rgba(45, 212, 191, 0.05); }
    </style>
</head>
<body class="antialiased pb-20">

    <nav class="fixed top-0 w-full z-50 glass-nav">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-white hover:bg-white/10 transition">✕</a>
            <span class="font-bold tracking-widest text-sm text-white uppercase">New Listing</span>
            <div class="w-10"></div>
        </div>
    </nav>

    <main class="max-w-xl mx-auto px-4 pt-24">
        
        <form action="process_listing.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            
            <input type="hidden" name="item_type" id="item_type" value="physical">

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Delivery Method</label>
                <div class="type-toggle">
                    <div id="btn-physical" class="toggle-btn active" onclick="setType('physical')">🤝 Physical Meetup</div>
                    <div id="btn-digital" class="toggle-btn" onclick="setType('digital')">⚡ Digital File (.ZIP/.PDF)</div>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">What are you selling?</label>
                <input type="text" name="title" required placeholder="e.g. iPhone 11 Pro / Intro to Java Notes" class="input-premium">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Price (KES)</label>
                <input type="number" name="price" required placeholder="0" class="input-premium font-bold text-xl">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Category</label>
                <select name="category" required class="input-premium appearance-none">
                    <option value="" disabled selected>Select a category...</option>
                    <option value="electronics">Electronics</option>
                    <option value="books">Books & Textbooks</option>
                    <option value="notes">Notes & Study Guides</option>
                    <option value="rooms">Hostel Rooms</option>
                    <option value="clothing">Fashion & Clothing</option>
                    <option value="digital">Software & Digital</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Description</label>
                <textarea name="description" required placeholder="Describe the condition, specs, or what's included..." rows="4" class="input-premium resize-none"></textarea>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1" id="upload-label">Item Photo</label>
                
                <label class="file-upload-box block">
                    <input type="file" name="listing_file" id="listing_file" required accept="image/*" onchange="updateFileName(this)">
                    <div id="upload-icon" class="text-4xl mb-3">📸</div>
                    <div id="upload-text" class="text-sm font-bold text-white mb-1">Tap to select photo</div>
                    <div id="upload-sub" class="text-xs text-gray-500">JPG, PNG up to 5MB</div>
                </label>
            </div>

            <div class="pt-4 pb-10">
                <button type="submit" class="w-full bg-teal-400 text-black font-bold py-4 rounded-xl text-lg shadow-[0_0_20px_rgba(45,212,191,0.3)] hover:bg-teal-300 transition-colors active:scale-95">
                    Post to Campus Feed
                </button>
            </div>

        </form>
    </main>

    <script>
        function setType(type) {
            document.getElementById('item_type').value = type;
            
            const btnPhysical = document.getElementById('btn-physical');
            const btnDigital = document.getElementById('btn-digital');
            const fileInput = document.getElementById('listing_file');
            
            const uploadLabel = document.getElementById('upload-label');
            const uploadIcon = document.getElementById('upload-icon');
            const uploadText = document.getElementById('upload-text');
            const uploadSub = document.getElementById('upload-sub');

            if (type === 'physical') {
                btnPhysical.classList.add('active');
                btnDigital.classList.remove('active');
                
                // Restrict to images only
                fileInput.setAttribute('accept', 'image/*');
                uploadLabel.textContent = "Item Photo";
                uploadIcon.textContent = "📸";
                uploadText.textContent = "Tap to select photo";
                uploadSub.textContent = "JPG, PNG up to 5MB";
            } else {
                btnDigital.classList.add('active');
                btnPhysical.classList.remove('active');
                
                // Allow PDFs, Documents, and ZIP files
                fileInput.setAttribute('accept', 'application/pdf, application/zip, application/x-rar-compressed, .doc, .docx');
                uploadLabel.textContent = "Upload Digital File";
                uploadIcon.textContent = "📁";
                uploadText.textContent = "Tap to select file";
                uploadSub.textContent = "PDF, ZIP, or DOCX up to 50MB";
            }
            
            // Reset file input if they switch types
            fileInput.value = "";
        }

        function updateFileName(input) {
            const uploadText = document.getElementById('upload-text');
            const uploadIcon = document.getElementById('upload-icon');
            
            if (input.files && input.files.length > 0) {
                const fileName = input.files[0].name;
                uploadText.textContent = fileName;
                uploadIcon.textContent = "✅";
                uploadText.classList.add('text-teal-400');
            }
        }
    </script>
</body>
</html>