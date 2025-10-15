return [
    'api_key' => env('TWITTER_CONSUMER_KEY'),
    'api_secret' => env('TWITTER_CONSUMER_SECRET'),
    'access_token' => env('TWITTER_ACCESS_TOKEN'), // If your application requires a static access token, add it to your .env file. Otherwise, it's typically obtained dynamically.
    'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET'), // If your application requires a static access token secret, add it to your .env file. Otherwise, it's typically obtained dynamically.
    'callback_url' => env('TWITTER_OAUTH_CALLBACK'),
];