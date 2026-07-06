<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions | Hostel Plaza</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        malbec: '#722F37',
                        'malbec-light': '#8C3A44',
                        cream: '#FDFBF7',
                        teal: {
                            DEFAULT: '#1c5457',
                            hover: '#144042'
                        }
                    },
                    fontFamily: {
                        serif: ['"Playfair Display"', 'Georgia', 'serif'],
                        sans: ['"Inter"', 'system-ui', '-apple-system', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .hero-gradient {
            background: linear-gradient(to bottom, rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.4));
        }
        .fade-in {
            animation: fadeIn 1s ease-in forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* CSS de nav/lang/google translate vive en header.php */
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased">

    <?php $hasHero = true; include __DIR__ . '/header.php'; ?>

    <section class="relative h-[40vh] min-h-[350px] flex items-center justify-center">
        <div class="absolute inset-0 z-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat bg-fixed" style="background-image: url('https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=');"></div>
            <div class="absolute inset-0 hero-gradient"></div>
        </div>

        <div class="relative z-10 text-center px-6 max-w-4xl fade-in mt-12">
            <h1 class="text-4xl md:text-6xl text-white mb-4 leading-tight font-serif font-bold">
                Terms & <span class="italic text-teal-300">Conditions</span>
            </h1>
            <p class="text-lg md:text-xl text-white/80 font-light">
                Please review our policies to ensure a perfect stay.
            </p>
        </div>
    </section>

    <main class="flex-1 max-w-4xl mx-auto px-8 md:px-16 py-16 bg-white rounded-3xl shadow-xl -mt-16 relative z-30 mb-24 w-[90%] md:w-full">
        
        <div class="prose prose-slate max-w-none">
            <p class="text-sm text-slate-500 uppercase tracking-widest font-bold mb-8">Last Updated: <?php echo date("F Y"); ?></p>

            <p class="text-slate-600 leading-relaxed mb-8">
                Welcome to Hostel Plaza. By booking a stay with us, you agree to comply with and be bound by the following terms and conditions. Please read them carefully to ensure you have a wonderful and seamless experience in Mendoza.
            </p>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">1. Booking and Reservations</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li>All bookings will need to be confirmed.</li>
                <li>Guests must be at least 18 years old to book a shared dormitory. Minors are only permitted in private rooms when accompanied by a parent or legal guardian.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">2. Check-In & Check-Out</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li><strong>Check-in:</strong> Begins at 14:00 (2:00 PM).</li>
                <li><strong>Check-out:</strong> Strictly by 10:00 AM.</li>
                <li>Early check-in and late check-out are subject to availability and may incur additional charges. If you arrive early, you are welcome to store your luggage in our secure room free of charge.</li>
                <li>A valid government-issued photo ID (passport or National ID) is required upon check-in. And if Argentinian, a DNI card.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">3. Payment & Cancellations</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li>Payment for the balance of your stay is due upon check-in. We accept cash (ARS), credit/debit cards, and bank transfers.</li>
                <li><strong>Standard Cancellation:</strong> Cancellations must be made at least 48 hours prior to your scheduled arrival date.</li>
                <li>Non-refundable rates (if selected at booking) cannot be modified or refunded under any circumstances.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">4. House Rules</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li><strong>Quiet Hours:</strong> We ask all guests to respect quiet hours between 23:00 (11:00 PM) and 07:00 AM to ensure everyone gets a good night's rest.</li>
                <li><strong>Smoking:</strong> Hostel Plaza is a strictly non-smoking indoor facility. Smoking is only permitted in designated outdoor areas (such as the courtyard).</li>
                <li><strong>Outside Guests:</strong> For security reasons, non-registered guests are not permitted in the dormitories or private rooms. They may visit common areas during daytime hours only, provided they sign in at reception.</li>
                <li><strong>Kitchen Use:</strong> Guests are welcome to use the shared kitchen. We ask that you clean your dishes, pots, and pans immediately after use and clearly label any food placed in the communal fridge.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">5. Liability & Security</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li>We provide free security lockers in all shared dormitories. Guests are responsible for providing their own padlocks (or purchasing one at reception).</li>
                <li>Hostel Plaza is not liable for the loss, theft, or damage to personal belongings, luggage, or valuables left unattended in common areas, rooms, or lockers.</li>
                <li>Any damage to hostel property (including linens, furniture, and fixtures) caused by a guest will be charged to the guest's registered credit card.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">6. Right of Admission</h2>
            <p class="text-slate-600 leading-relaxed mb-8">
                We reserve the right to refuse admission or end a stay prematurely, without refund, if a guest behaves in a manner that compromises the safety, comfort, or well-being of other guests or our staff.
            </p>

            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 mt-12">
                <h3 class="font-bold text-slate-900 mb-2">Questions about these terms?</h3>
                <p class="text-slate-600 text-sm mb-0">If you have any questions or require clarification before booking, please do not hesitate to contact our team at <a href="mailto:info@hostelplaza.com" class="text-teal font-medium hover:underline">info@hostelplaza.com</a>.</p>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        lucide.createIcons();
    </script>

</body>
</html>