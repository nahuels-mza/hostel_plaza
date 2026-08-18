# Agente de WhatsApp · Hostel Plaza

Agente conversacional para responder consultas de huéspedes por WhatsApp
sobre precios y disponibilidad de habitaciones. La fuente de verdad es
el motor de reservas **BananaDesk** (se consulta en tiempo real, con
caché de 5 min). Usa **Claude (Anthropic)** como motor de lenguaje, y
**WhatsApp Cloud API** de Meta como canal.

### Fuente de datos

El agente consulta en cada mensaje el endpoint público de BananaDesk:

```
GET https://bananadesk.com/booking-engine/hostel-plaza/room-type-availability
    ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&room_type=both
```

La respuesta es un array de tipos de habitación con `availability`
(unidades libres), `price` y `currency` para ese rango. Cada respuesta
queda cacheada en `whatsapp/cache/` por 5 minutos para no martillar el
endpoint si llegan varios mensajes seguidos.

## Estructura

```
whatsapp/
├── webhook.php          # endpoint público que recibe los mensajes de Meta
├── agent.php            # loop principal con Claude + memoria por número
├── availability.php     # cliente HTTP a BananaDesk + normalización + caché
├── whatsapp_client.php  # cliente Cloud API (Graph)
├── claude_client.php    # cliente API de Anthropic
├── config.php           # credenciales (NO subir a git)
├── config.example.php   # plantilla con placeholders
├── .htaccess            # bloquea acceso web a config / cache
└── cache/               # respuestas cacheadas de BananaDesk (TTL 5 min)
```

Los logs del bot NO viven acá — van a `/logs/whatsapp/AAAA-MM-DD.log`
(carpeta compartida en la raíz del sitio, junto con mail/payway/paypal),
un archivo por día, protegida por `/logs/.htaccess`. Ver `logger.php`.

---

## 1. Configurar WhatsApp Cloud API (Meta)

1. Entrá a [business.facebook.com](https://business.facebook.com) y creá
   una cuenta de **Meta Business**.
2. En **Configuración del negocio → Cuentas → Cuentas de WhatsApp**,
   creá una **WhatsApp Business Account (WABA)**.
3. Agregá un **número de teléfono** (puede ser uno nuevo o uno que no
   tenga WhatsApp activo). Vas a recibir un código por SMS para verificarlo.
4. En [developers.facebook.com](https://developers.facebook.com) creá una
   **App de tipo Business** y agregale el producto **WhatsApp**.
5. En el panel de WhatsApp vas a ver el **Phone Number ID** y el
   **temporary access token** (24 hs).
6. Para producción generá un **Permanent Token**:
   - Business Settings → Users → **System Users** → crear "Hostel Bot".
   - Asignarle la WABA con permiso *Manage*.
   - Generar token, scopes: `whatsapp_business_messaging`,
     `whatsapp_business_management`.

Copiá ambos valores a `config.php`:

```php
'phone_number_id' => '123456789012345',
'access_token'    => 'EAAJZA...',
```

## 2. Configurar el webhook

1. Subí la carpeta `whatsapp/` a tu hosting. El webhook quedará en
   `https://hostelplaza.com.ar/whatsapp/webhook.php`.
2. En el panel de WhatsApp de tu app de Meta:
   - **Callback URL**: `https://hostelplaza.com.ar/whatsapp/webhook.php`
   - **Verify token**: el mismo string que pusiste en `config.php` →
     `'verify_token' => '...'`.
3. Click en **Verify and save** (Meta hará un GET y nuestro webhook
   responde con el `hub.challenge`).
4. Suscribite al campo **messages** dentro de Webhooks.

## 3. Configurar Claude (Anthropic)

1. Entrá a [console.anthropic.com](https://console.anthropic.com) y creá
   una API key.
2. Pegala en `config.php`:

```php
'claude' => [
    'api_key' => 'sk-ant-...',
    'model'   => 'claude-sonnet-4-5',
],
```

## 4. Notificaciones al dueño

En `config.php`, poné tu número de WhatsApp personal con código de país,
sin `+` ni espacios:

```php
'admin' => [
    'phone'   => '5491155555555',
    'forward' => true,
],
```

Cada vez que un huésped escriba al hostel, vas a recibir un WhatsApp en
tu número con la consulta y la respuesta que dio el bot.

## 5. Probar

### a) Verificación inicial del webhook

Cuando Meta hace `GET /webhook.php?hub.mode=subscribe&hub.verify_token=...&hub.challenge=XYZ`,
el script responde 200 con `XYZ`. Si falla, revisá el `verify_token`.

### b) Mensaje real

Mandá un WhatsApp al número del hostel desde tu celular:

> "Hola, ¿tienen habitación doble para el 5 al 8 de junio?"

El bot debería responder en menos de 5 s con disponibilidad y precio.

### c) Probar la lógica sin Meta

Desde la terminal podés simular una consulta directa a BananaDesk:

```bash
php -r '
  require "whatsapp/availability.php";
  $cfg = require "whatsapp/config.php";
  $res = hp_bananadesk_fetch(
    $cfg["bananadesk"],
    "2026-06-05", "2026-06-08",
    $cfg["paths"]["cache"]
  );
  print_r($res);
'
```

## 6. Permisos

```bash
chmod 600 whatsapp/config.php
chmod 600 whatsapp/conversations.json   # se crea al primer mensaje
chmod 775 whatsapp/cache
chmod 775 logs/whatsapp    # se crea sola al primer mensaje (ver logger.php)
```

## 7. Seguridad

- `config.php` y `conversations.json` están bloqueados por `.htaccess`.
- **Nunca** subas `config.php` al repositorio — agregá a `.gitignore`:
  ```
  whatsapp/config.php
  whatsapp/conversations.json
  whatsapp/cache/
  logs/*/*.log
  ```
- Opcional: validar la firma `X-Hub-Signature-256` que envía Meta usando
  un `App Secret` (recomendado para producción).

## 8. Costos aproximados

- **WhatsApp Cloud API**: las primeras 1.000 conversaciones iniciadas
  por usuarios son gratis cada mes (modelo "user-initiated").
- **Claude API**: con `claude-sonnet-4-5`, una consulta típica
  (~2k tokens entrada + 200 salida) cuesta menos de USD 0.01.

## 9. Cómo agregar funciones

- **Reservar desde WhatsApp**: editar `hp_tools_definition()` en
  `agent.php` y agregar una tool `create_booking` que llame a la API de
  BananaDesk (si está disponible) o que cree un registro local pendiente.
- **Eventos / tours**: agregar otra tool que lea
  `plaza_events.json` o `tourist-events.php`.
- **Otros idiomas**: ya funciona automáticamente — Claude responde en el
  idioma del huésped.

## 10. BananaDesk: notas

- El endpoint es público (no requiere autenticación).
- Devuelve `availability` como entero: cuántas unidades de ese tipo
  están libres en ese rango. `>= 1` significa disponible.
- Devuelve `stay_length_restriction` con un mínimo de noches si aplica.
- Las descripciones suelen venir bilingües, separadas por `///`. El
  módulo `availability.php` las parte automáticamente.
