<?php

return [
    'url'     => env('WA_URL'),
    'secret'  => env('WA_SECRET'),
    'timeout' => 30,   // gateway menunggu antrean + jeda acak, jadi jangan terlalu pendek
];