<?php
/**
 * Copy to config.php and adjust for your environment.
 *
 * Local Docker API (same VPS):
 *   'kokoro_api_url' => 'http://127.0.0.1:4123/v1/audio/speech',
 *
 * Remote Hostinger VPS API:
 *   'kokoro_api_url' => 'https://tts.yourdomain.com/v1/audio/speech',
 *   'api_key' => 'your-api-key-from-.env',
 */

return [
    'kokoro_api_url' => 'http://127.0.0.1:4123/v1/audio/speech',
    'kokoro_health_url' => 'http://127.0.0.1:4123/health',
    'kokoro_voices_url' => 'http://127.0.0.1:4123/v1/voices',

    // Optional: sent as Authorization: Bearer *** when calling the Docker API
    'api_key' => '',

    // Default voice (af_heart, af_bella, am_adam, am_eric, etc.)
    'default_voice' => 'af_heart',

    // Playback speed (0.5-2.0, 1.0 = normal)
    'default_speed' => 1.0,

    'request_timeout' => 60,
];