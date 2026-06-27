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

    // Steam Web API（ゲームプレイ時間の取り込みに使用）
    'steam' => [
        'key' => env('STEAM_API_KEY'),
    ],

    // Discord 連携（Bot トークン / OAuth。ゲーム時間反映の拡張用）
    'discord' => [
        'token' => env('DISCORD_BOT_TOKEN'),
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
    ],

    // Discord Bot 管理API（同一VPSのlocalhostで動くBotの設定を中継編集）
    // ADMIN_KEY はサーバー側にのみ保持し、ブラウザには出さない
    'discord_bot' => [
        'url' => env('BOT_ADMIN_URL', 'http://localhost:3000'),
        'key' => env('BOT_ADMIN_KEY'),
    ],

];
