<?php
/**
 * TEMPLATE de secrets — copiar a /storagedir/secrets.php en el servidor
 * y completar con los valores reales. NUNCA subir con valores a Git.
 *
 * Ruta esperada por la aplicación: un nivel arriba de public_html
 *   /storagedir/secrets.php
 */
return [
    // SMTP — usado por book.php y admin.php para enviar mails de reserva
    'smtp_password' => '',   // contraseña de confirmation@hostelplaza.com.ar en Ferozo

    // Claude API — usado por whatsapp/config.php para el bot
    'claude_api_key' => '',  // sk-ant-api03-...

    // WhatsApp Cloud API — Meta for Developers → App → WhatsApp → Configuration
    'wa_phone_number_id' => '',  // ID numérico del número en Meta (no el número E.164)
    'wa_access_token'    => '',  // Token permanente del sistema
    'wa_verify_token'    => '',  // Token libre que pusiste en el webhook de Meta
];
