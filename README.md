# hostel_plaza

Sitio web y sistema de gestión de **Hostel Plaza** (Mendoza, Argentina): landing pública, motor de reservas, panel de administración/staff, cobro de depósitos con Stripe, envío de mails de confirmación y un agente de WhatsApp con IA para consultas de disponibilidad y precios.

Es una app **PHP clásica sin framework** (sin Composer excepto para PHPMailer): cada `.php` en la raíz es una página o endpoint servido directamente por Apache/PHP, y los datos se persisten en archivos **JSON planos** en lugar de una base de datos.

## Estructura general

```
├── index.php, rooms.php, room.php, about.php, faq.php,   → páginas públicas del sitio
│   history.php, virtual-tour.php, terms.php, privacy.php,  (landing, habitaciones, info)
│   tourist-events.php, hero-img.php, event-img.php
├── header.php, footer.php, _seo.php, _ga.php,             → partials compartidos por las
│   _search_fields.php, _about_gallery.php                  páginas públicas
│
├── book.php                                                → wizard de reserva (3 pasos: fechas → datos → pago)
├── bananadesk_reserve.php                                  → crea la reserva en BananaDesk (motor externo)
├── room_availability.php, rooms_for_dates.php              → endpoints de disponibilidad/precio consultando BananaDesk
├── create_checkout_session.php, pay.php,                  → cobro del depósito con Stripe Embedded Checkout
│   stripe_webhook.php, stripe_lib.php
├── mail_argentina.php, mail_extranjero.php                → templates de mail de confirmación (AR vs. extranjero)
│
├── login.php, admin.php, staff.php, guest.php,            → panel administrativo y de staff (autenticados por sesión)
│   checklist.php, extend.php, logout.php
│
├── whatsapp/                                                → agente conversacional de WhatsApp (ver whatsapp/README.md)
│   ├── webhook.php, agent.php, claude_client.php,           usa Claude (Anthropic) + WhatsApp Cloud API,
│   ├── whatsapp_client.php, availability.php,                consulta BananaDesk en tiempo real con caché
│   └── prices_cache.php, config.php (no versionado)
│
├── *.json (rooms, bookings, users, staff, events,         → "base de datos" del sitio: cada archivo es una
│   discounts, expenses, tasks, messages, schedule, ...)     colección persistida como JSON plano
│
├── secrets.example.php                                     → plantilla de credenciales (SMTP, Stripe, Claude,
│                                                              WhatsApp); las reales viven fuera del webroot en
│                                                              /storagedir/secrets.php
├── PHPMailer-master/                                        → librería vendorizada usada para el envío de mails
└── assets/                                                  → build de frontend (JS/CSS compilados)
```

### Flujo de reserva

1. El huésped elige fechas y habitación en `book.php`, que consulta disponibilidad/precio vía `room_availability.php` / `rooms_for_dates.php` contra **BananaDesk** (motor de reservas externo).
2. `bananadesk_reserve.php` crea la reserva en BananaDesk y se guarda un registro local en `bookings.json`.
3. Se cobra el depósito con **Stripe** (`create_checkout_session.php` + `pay.php`), y `stripe_webhook.php` marca la reserva como pagada.
4. Se envía el mail de confirmación con `mail_argentina.php` o `mail_extranjero.php` (según el país del huésped) usando PHPMailer.

### Panel administrativo

`login.php` autentica contra `users.json`/`staff.json` y da acceso a `admin.php` (gestión completa) o `staff.php`/`guest.php`/`checklist.php` (vistas acotadas para el equipo), todo basado en sesiones PHP.

### Agente de WhatsApp

Subsistema aparte en `whatsapp/`, documentado en detalle en [whatsapp/README.md](whatsapp/README.md): responde consultas de huéspedes por WhatsApp usando Claude como motor de lenguaje y BananaDesk como fuente de disponibilidad/precios en tiempo real.
