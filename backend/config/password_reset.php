<?php

return [
    'code_ttl_minutes' => (int) env('PASSWORD_RESET_CODE_TTL_MINUTES', 20),
    'resend_cooldown_seconds' => (int) env('PASSWORD_RESET_RESEND_COOLDOWN_SECONDS', 60),
    'max_send_per_hour' => (int) env('PASSWORD_RESET_MAX_SEND_PER_HOUR', 5),
    'max_confirm_attempts_per_token' => (int) env('PASSWORD_RESET_MAX_CONFIRM_ATTEMPTS_PER_TOKEN', 8),
    'max_confirm_attempts_per_window' => (int) env('PASSWORD_RESET_MAX_CONFIRM_ATTEMPTS_PER_WINDOW', 10),
    'confirm_window_seconds' => (int) env('PASSWORD_RESET_CONFIRM_WINDOW_SECONDS', 900),
];
