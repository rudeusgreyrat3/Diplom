<?php

// Вход в админку. Перед выкладкой в интернет обязательно поменяй
// ADMIN_LOGIN и ADMIN_PASSWORD в файле .env — admin/admin только для разработки.
return [
    'login'    => env('ADMIN_LOGIN', 'admin'),
    'password' => env('ADMIN_PASSWORD', 'admin'),
];
