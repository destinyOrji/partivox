<?php
function env($key, $default = null) {
    // Try getenv first (works with Render environment variables)
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    
    // Fallback to $_ENV superglobal
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    
    return $default;
}

return [
    'api_key' => env('TWITTER_CONSUMER_KEY'),
    'api_secret' => env('TWITTER_CONSUMER_SECRET'),
    'access_token' => env('TWITTER_ACCESS_TOKEN'),
    'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET'),
    'callback_url' => env('TWITTER_OAUTH_CALLBACK'),
];