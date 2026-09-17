<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sales funnel pacing for influencer DMs
    |--------------------------------------------------------------------------
    |
    | Early turns should flirt and build tension. Selling private photos/videos
    | should wait until the user is warmed up or shows clear buy intent.
    |
    */

    'sales_funnel' => [
        'warmup_max_user_messages' => (int) env('CHAT_WARMUP_MAX_USER_MESSAGES', 4),
        'tease_max_user_messages' => (int) env('CHAT_TEASE_MAX_USER_MESSAGES', 8),
        'buy_intent_keywords' => [
            'pic', 'pics', 'photo', 'photos', 'nude', 'nudes', 'video', 'videos',
            'custom', 'customs', 'pay', 'price', 'buy', 'purchase', 'send me',
            'onlyfans', 'ppv', 'content', 'private',
        ],
    ],

];
