<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'print_bridge' => [
        'secret' => env('PRINT_BRIDGE_SECRET'),
        'url' => env('PRINT_BRIDGE_URL', 'http://192.168.100.16:3001'),
    ],

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'openwa'),
        'provider_switching_enabled' => (bool) env('WHATSAPP_PROVIDER_SWITCHING_ENABLED', false),
        'comprobantes_admin_enabled' => (bool) env('WHATSAPP_COMPROBANTES_ADMIN_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenWA — pasarela de WhatsApp
    |--------------------------------------------------------------------------
    |
    | OpenWA debe ser alcanzable desde este servidor: el sistema le hace
    | peticiones salientes para enviar mensajes. Un OpenWA en localhost solo
    | funciona si ambos corren en la misma máquina.
    |
    | 'webhook_secret' es el secreto HMAC con el que se firma el webhook
    | entrante (comprobantes de pago). Debe coincidir con el 'secret' que se
    | registró en OpenWA al dar de alta el webhook.
    |
    */
    'openwa' => [
        'base_url' => rtrim((string) env('OPENWA_BASE_URL', 'http://localhost:2785'), '/'),
        'api_key' => env('OPENWA_API_KEY'),
        'session_id' => env('OPENWA_SESSION_ID'),
        'webhook_secret' => env('OPENWA_WEBHOOK_SECRET'),

        // Lada del país sin '+'. Se antepone a los teléfonos de 10 dígitos.
        'codigo_pais' => env('OPENWA_CODIGO_PAIS', '52'),

        // Segundos antes de abortar una petición a OpenWA.
        'timeout' => (int) env('OPENWA_TIMEOUT', 20),
    ],

    'meta_whatsapp' => [
        'enabled' => (bool) env('META_WHATSAPP_ENABLED', false),
        'base_url' => rtrim((string) env('META_WHATSAPP_BASE_URL', 'https://graph.facebook.com'), '/'),
        'version' => env('META_WHATSAPP_VERSION', 'v23.0'),
        'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
        'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('META_WHATSAPP_APP_SECRET'),
        'codigo_pais' => env('META_WHATSAPP_CODIGO_PAIS', '52'),
        'timeout' => (int) env('META_WHATSAPP_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI — lectura de comprobantes de pago
    |--------------------------------------------------------------------------
    |
    | Se usa solo para PRE-LLENAR los datos de un comprobante que llegó por
    | WhatsApp. Nunca decide nada: un cobrador valida antes de que se genere
    | recibo. Ver App\Services\Comprobantes\ComprobanteExtractorService.
    |
    | Confirma el nombre del modelo contra tu panel de OpenAI: el catálogo
    | cambia y un modelo dado de baja responde 404.
    |
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),

        // Debe soportar visión (entrada de imágenes).
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

        // Modelo conversacional para el bot. Se separa del modelo de visión
        // para poder optimizar costo y latencia sin alterar la extracción.
        'bot_model' => env('OPENAI_BOT_MODEL', 'gpt-5-nano'),
        'bot_reasoning_effort' => env('OPENAI_BOT_REASONING_EFFORT', 'minimal'),
        'bot_max_output_tokens' => (int) env('OPENAI_BOT_MAX_OUTPUT_TOKENS', 140),
        'bot_history_limit' => (int) env('OPENAI_BOT_HISTORY_LIMIT', 3),
        'bot_timeout' => (int) env('OPENAI_BOT_TIMEOUT', 30),
        'bot_hourly_limit' => (int) env('OPENAI_BOT_HOURLY_LIMIT', 8),
        'bot_daily_limit' => (int) env('OPENAI_BOT_DAILY_LIMIT', 20),
        'bot_max_message_chars' => (int) env('OPENAI_BOT_MAX_MESSAGE_CHARS', 500),
        'bot_support_phone' => env('OPENAI_BOT_SUPPORT_PHONE', '878 785 1461'),

        // USD por un millón de tokens. Se copian en cada registro para conservar
        // el costo histórico aunque las tarifas cambien después.
        'pricing' => [
            'gpt-5-nano' => ['input' => 0.05, 'cached_input' => 0.005, 'output' => 0.40],
            'gpt-4o-mini' => ['input' => 0.15, 'cached_input' => 0.075, 'output' => 0.60],
        ],

        // Lado mayor al que se reduce la imagen antes de subirla. Menos píxeles,
        // menos tokens: un comprobante de transferencia se lee bien muy por
        // debajo de la resolución de la cámara.
        'max_lado_px' => (int) env('OPENAI_MAX_LADO_PX', 1568),

        'timeout' => (int) env('OPENAI_TIMEOUT', 90),
    ],

];
