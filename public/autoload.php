<?php

/**
 * Autoloader PSR-4 simple, sin dependencias externas (sin Composer).
 *
 * Mapea el namespace "App\" al directorio /src, de modo que
 * App\Models\Camiseta corresponde a /src/Models/Camiseta.php, etc.
 */
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
