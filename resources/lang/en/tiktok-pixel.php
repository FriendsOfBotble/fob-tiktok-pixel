<?php

return [
    'name' => 'TikTok Pixel',

    'settings' => [
        'title' => 'TikTok Pixel',
        'description' => 'Configure TikTok Pixel and Events API for e-commerce conversion tracking and ad optimization.',

        'enable' => 'Enable TikTok Pixel',
        'enable_help' => 'Inject TikTok Pixel tracking code into your storefront pages',

        'pixel_id' => 'Pixel ID',
        'pixel_id_help' => 'Find your Pixel ID in TikTok Ads Manager under Assets → Events → Web Events',

        'access_token' => 'Events API Access Token',
        'access_token_help' => 'Required for server-side event tracking. Generate in TikTok Ads Manager under Assets → Events → Manage → Settings. Token is encrypted before storage.',
        'access_token_placeholder' => 'Paste your Events API access token here...',

        'token_configured' => 'Access token is configured.',
        'token_not_configured' => 'No access token configured. Enter your Events API access token below to enable server-side tracking.',
        'token_store_failed' => 'Failed to store the access token. Please try again.',

        'events_api_enable' => 'Enable Server-Side Events API',
        'events_api_enable_help' => 'Send conversion events server-side for improved accuracy. Requires a valid access token.',
        'events_api_not_enabled' => 'Events API is not enabled or access token is missing. Enable it above and save settings first.',

        'test_event_code' => 'Test Event Code',
        'test_event_code_help' => 'Optional. Set a test event code from TikTok Events Manager to send events in test mode. Remove after testing.',

        'event_tracking' => 'Event Tracking',
        'event_tracking_help' => 'Choose which events to track. Disabling an event prevents it from firing on both client-side and server-side.',

        'test_connection' => 'Test Connection',
        'testing' => 'Testing...',
        'connection_success' => 'Connection successful! Events API is working.',
        'connection_failed' => 'Connection failed. Please check your access token and pixel ID.',

        'setup_title' => 'Setup Instructions',
        'setup_step_1' => 'Go to TikTok Ads Manager and navigate to Assets → Events',
        'setup_step_2' => 'Create a Web Event pixel or select an existing one',
        'setup_step_3' => 'Copy the Pixel ID and paste it above',
        'setup_step_4' => 'For server-side tracking: go to Settings → Generate Access Token',
        'setup_step_5' => 'Paste the access token above and enable Events API',
        'open_ads_manager' => 'Open TikTok Ads Manager',
    ],
];
