<?php

use App\Models\Usuario;

return [
    'defaults' => ['guard' => 'web'],
    'guards' => ['web' => ['driver' => 'session', 'provider' => 'usuarios']],
    'providers' => ['usuarios' => ['driver' => 'eloquent', 'model' => Usuario::class]],
];
