<footer class="bg-slate-900 text-white pt-20 pb-10 mt-auto border-t border-white/5">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-16 mb-16">

            <div class="space-y-6">
                <img src="hostel.png" alt="Hostel Plaza Logo" style="height: 65px; width: auto; object-fit: contain;" class="block">
                <p class="text-white/50 text-base leading-relaxed max-w-xs">
                    Your home in the heart of Mendoza, Argentina. Unique experiences in the land of Malbec.
                </p>
            </div>

            <div>
                <h4 class="text-sm font-bold uppercase tracking-[0.2em] text-[#2dd4bf] mb-8 flex items-center gap-2">
                    <i data-lucide="menu" class="w-4 h-4"></i> Menu
                </h4>
                <ul class="space-y-4 text-white/60 font-medium">
                    <li><a href="/" class="hover:text-white transition-colors">Home</a></li>
                    <li><a href="about" class="hover:text-white transition-colors">About Us</a></li>
                    <li><a href="rooms" class="hover:text-white transition-colors">Rooms</a></li>
                    <li><a href="tourist-events" class="hover:text-white transition-colors">Tourist Events</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-sm font-bold uppercase tracking-[0.2em] text-[#2dd4bf] mb-8 flex items-center gap-2">
                    <i data-lucide="info" class="w-4 h-4"></i> Useful Info
                </h4>
                <ul class="space-y-4 text-white/60 font-medium">
                    <li><a href="history" class="hover:text-white transition-colors flex items-center gap-2">History</a></li>
                    <li><a href="faq" class="hover:text-white transition-colors">FAQ</a></li>
                    <li><a href="virtual-tour" class="hover:text-white transition-colors">Virtual Tour</a></li>

                </ul>
            </div>

            <div>
                <h4 class="text-sm font-bold uppercase tracking-[0.2em] text-[#2dd4bf] mb-8 flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4"></i> Reservations
                </h4>
                <ul class="space-y-4 text-white/60 font-medium">
                    <li><a href="book" class="hover:text-white transition-colors">Make a Reservation</a></li>
                    <li class="flex items-center gap-3 pt-2">
                        <i data-lucide="phone" class="w-4 h-4 text-[#2dd4bf]"></i>
                        <a href="https://api.whatsapp.com/send/?phone=5492615372767" class="hover:text-white transition-colors">+54 9 2615 37-2767</a>
                    </li>
                    <li class="flex items-center gap-3">
                        <i data-lucide="mail" class="w-4 h-4 text-[#2dd4bf]"></i>
                        <a href="mailto:reservas@hostelplaza.com.ar" class="hover:text-white transition-colors">reservas@hostelplaza.com.ar</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pt-10 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-8 text-white/40 text-xs tracking-wide">
            <div class="flex flex-col md:flex-row items-center gap-2 md:gap-8">
                <p>Hostel Plaza © <?php echo date("Y"); ?> — All Rights Reserved.</p>
                <div class="flex gap-6">
                    <a href="terms" class="hover:text-white transition-colors">Terms & Conditions</a>
                    <a href="privacy" class="hover:text-white transition-colors">Privacy</a>
                    <a href="cookies" class="hover:text-white transition-colors">Cookies</a>
                </div>
            </div>

            <div class="flex items-center gap-2 group cursor-default">
                <span>Designed by</span>
                <span class="text-[#2dd4bf] font-bold italic tracking-tight group-hover:text-white transition-colors">Syntax Design</span>
            </div>
        </div>
    </div>
</footer>
<?php include_once __DIR__ . '/_ga.php'; ?>