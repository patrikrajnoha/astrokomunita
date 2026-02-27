<?php

return [
    'allow_insecure_ssl' => filter_var(env('HTTP_CLIENT_ALLOW_INSECURE_SSL', false), FILTER_VALIDATE_BOOLEAN),
];
