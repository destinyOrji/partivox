<?php
return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '64299511030-2f1tp4utqi1mppehjno7rm9n8nbvpg9d.apps.googleusercontent.com',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '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',
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost:8000/pages/admin_dashboard/signup.html',
    'scopes' => [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile'
    ]
];
