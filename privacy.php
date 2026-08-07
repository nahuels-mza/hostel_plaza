<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | Hostel Plaza</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    
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
                Privacy <span class="italic text-teal-300">Policy</span>
            </h1>
            <p class="text-lg md:text-xl text-white/80 font-light">
                How we handle and protect your personal information.
            </p>
        </div>
    </section>

    <main class="flex-1 max-w-4xl mx-auto px-8 md:px-16 py-16 bg-white rounded-3xl shadow-xl -mt-16 relative z-30 mb-24 w-[90%] md:w-full">
        
        <div class="prose prose-slate max-w-none">
            <p class="text-sm text-slate-500 uppercase tracking-widest font-bold mb-8">Last Updated: <?php echo date("F Y"); ?></p>

            <p class="text-slate-600 leading-relaxed mb-8">
                At Hostel Plaza, your privacy is our priority. This Privacy Policy outlines how we collect, use, protect, and handle your personal information when you use our website or stay at our property in Mendoza, Argentina.
            </p>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">1. Information We Collect</h2>
            <p class="text-slate-600 leading-relaxed mb-4">When you interact with our website or make a reservation, we may collect the following types of information:</p>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li><strong>Personal Identification Data:</strong> Full name, email address, phone number, physical address, and date of birth.</li>
                <li><strong>Booking Details:</strong> Arrival and departure dates, room preferences, and special requests.</li>
                <li><strong>Payment Information:</strong> Credit/debit card details required to secure and process your reservation (handled securely by trusted payment processors).</li>
                <li><strong>Identification Documents:</strong> A copy of your passport or national ID at check-in, as required by local Argentine law.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">2. How We Use Your Information</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li>To process and manage your room reservations and payments.</li>
                <li>To communicate with you regarding your stay, including sending booking confirmations and check-in instructions.</li>
                <li>To comply with local legal and regulatory requirements (such as tourist tax exemptions for foreign guests).</li>
                <li>To improve our website, services, and guest experience.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">3. Data Sharing and Third Parties</h2>
            <p class="text-slate-600 leading-relaxed mb-8">
                We do not sell, trade, or rent your personal information to third parties. We may share your data only with trusted third-party service providers who assist us in operating our website, conducting our business, or servicing you (such as secure payment gateways or booking engine software). These parties agree to keep this information strictly confidential.
            </p>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">4. Data Security</h2>
            <p class="text-slate-600 leading-relaxed mb-8">
                We implement a variety of security measures to maintain the safety of your personal information. Sensitive payment data is encrypted and transmitted securely. While no method of transmission over the internet is 100% secure, we strive to use commercially acceptable means to protect your personal data.
            </p>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">5. Cookies and Tracking</h2>
            <p class="text-slate-600 leading-relaxed mb-8">
                Our website uses "cookies" to enhance your browsing experience. Cookies are small files transferred to your computer's hard drive through your web browser that enable the site's systems to recognize your browser and capture certain information (such as saving your booking dates for your session). You can choose to turn off all cookies via your browser settings, though some features of the site may not function properly.
            </p>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">6. Your Rights</h2>
            <p class="text-slate-600 leading-relaxed mb-8">
                You have the right to request access to the personal data we hold about you, to ask for corrections to be made, or to request the deletion of your data (subject to our legal obligations to retain certain records for taxation and security purposes).
            </p>

            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 mt-12">
                <h3 class="font-bold text-slate-900 mb-2">Contact Us</h3>
                <p class="text-slate-600 text-sm mb-0">If you have any questions regarding this Privacy Policy or how your data is handled, please contact us at <a href="mailto:info@hostelplaza.com" class="text-teal font-medium hover:underline">info@hostelplaza.com</a>.</p>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>