<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Third Party Services
  |--------------------------------------------------------------------------
  |
  | This file is for storing the credentials for third party services such
  | as Resend, Postmark, AWS, and more. This file provides the de facto
  | location for this type of information, allowing packages to have
  | a conventional file to locate the various service credentials.
  |
  */

  'smartlog' => [
    'api_key' => env('SMARTLOG_API_KEY'),
    'api_rate_limit_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 60),
    'log_analysis_rate_limit_per_minute' => env('LOG_ANALYSIS_RATE_LIMIT_PER_MINUTE', 10),
    'llm_driver' => env('LLM_DRIVER', 'mock'),
  ],

  'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
    'timeout' => (int) env('OPENAI_TIMEOUT', 30),
    'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 5),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 1500),
  ],

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

];
