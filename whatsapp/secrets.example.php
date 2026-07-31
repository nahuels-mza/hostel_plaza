<?php
/**
 * PLANTILLA de secrets.php
 *
 *  Este archivo NO va en Git. Es solo la referencia de qué claves usar.
 *
 *  1. Copiá este archivo como:      /storagedir/secrets.php
 *     (fuera de public_html, así no queda expuesto por web)
 *  2. Reemplazá los valores placeholder por los reales.
 *  3. Permisos recomendados:        chmod 600 /storagedir/secrets.php
 *
 *  De dónde salen los valores:
 *   - wa_phone_number_id  → Meta Developer → App → WhatsApp → API Setup
 *                           (Identificador de número de teléfono)
 *   - wa_access_token     → misma pantalla. Temporal dura 24 hs.
 *                           Para producción, generar uno permanente vía System User.
 *   - wa_verify_token     → cadena arbitraria que vos elegís.
 *                           Debe coincidir con la que cargues en Meta al configurar el webhook.
 *   - claude_api_key      → https://console.anthropic.com → API Keys
 */

return [
    // WhatsApp Cloud API (Meta)
    // Phone Number ID   → Meta Developer → App → WhatsApp → API/Configuración
    // WABA ID (1376978691032107) no va acá; solo lo dejo como referencia.
    'wa_phone_number_id' => '1255457637652531',                      // Hostel Plaza · +54 9 261 259-2729
    'wa_access_token'    => 'EAAJZAxxxxxxxxxxxxxxxxxx_REPLACE_ME',   // token de Meta (temporal 24h, o permanente vía System User)
    'wa_verify_token'    => 'hostelplaza_bot_2026_xk9m',              // cadena que vos elegís; MISMA en Meta y acá

    // Claude API (Anthropic)
    'claude_api_key'     => 'sk-ant-api03-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx_REPLACE_ME',
];
