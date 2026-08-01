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
                        <a href="https://api.whatsapp.com/send/?phone=5492612592729" class="hover:text-white transition-colors">+54 9 2612 59-2729</a>
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

<!-- Floating WhatsApp bubble — bot de reservas -->
<a href="https://api.whatsapp.com/send/?phone=5492612592729"
   target="_blank"
   rel="noopener"
   aria-label="Chat on WhatsApp"
   class="fixed bottom-6 right-6 z-50 w-14 h-14 bg-[#25D366] hover:bg-[#20bd5a] rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-8 h-8 fill-white" aria-hidden="true">
        <path d="M16.001 2.667c-7.363 0-13.334 5.97-13.334 13.333 0 2.353.615 4.66 1.782 6.686L2.667 29.333l6.83-1.79a13.27 13.27 0 0 0 6.504 1.657h.006c7.363 0 13.333-5.97 13.333-13.333 0-3.56-1.387-6.907-3.905-9.425a13.24 13.24 0 0 0-9.434-3.775zm0 24.4h-.005a11.05 11.05 0 0 1-5.63-1.542l-.404-.24-4.053 1.063 1.082-3.951-.263-.406a11.05 11.05 0 0 1-1.694-5.892c0-6.114 4.976-11.09 11.093-11.09a11.03 11.03 0 0 1 7.848 3.253 11.03 11.03 0 0 1 3.247 7.847c-.003 6.115-4.98 11.09-11.221 11.958zm6.083-8.3c-.334-.167-1.98-.977-2.286-1.089-.307-.111-.53-.167-.753.167-.223.334-.865 1.089-1.06 1.312-.195.223-.39.25-.723.084-.334-.167-1.41-.52-2.686-1.658-.993-.885-1.663-1.978-1.858-2.312-.195-.334-.02-.514.146-.68.15-.15.334-.39.5-.585.167-.195.223-.334.334-.557.111-.223.056-.418-.028-.585-.084-.167-.753-1.815-1.032-2.487-.272-.653-.548-.566-.753-.577-.195-.01-.418-.012-.641-.012-.223 0-.585.084-.892.418-.307.334-1.169 1.142-1.169 2.786 0 1.644 1.196 3.233 1.363 3.457.167.223 2.354 3.594 5.703 5.041.797.344 1.418.55 1.902.704.799.254 1.526.218 2.101.132.641-.096 1.98-.809 2.259-1.591.279-.782.279-1.452.195-1.591-.084-.14-.307-.223-.641-.39z"/>
    </svg>
</a>

<?php include_once __DIR__ . '/_ga.php'; ?>