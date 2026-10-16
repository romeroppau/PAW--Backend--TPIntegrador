<?php

// PHPUnit ejecuta este archivo una vez, antes de todas las pruebas.
// Carga las clases que las pruebas necesitan: las de comun/ (ApiGateway) y las de Backend.

// Dónde está comun/: si se indica con la variable COMUN_DIR, se usa esa ruta.
// Si no, se busca dos carpetas más arriba, que es donde queda cuando Backend está adentro de ApiGateway.
$comun = getenv('COMUN_DIR') ?: __DIR__ . '/../../comun';

if (!is_dir($comun)) {
    echo "No se encontró la carpeta comun/ en: $comun\n";
    echo "Indicá dónde está con la variable COMUN_DIR.\n";
    exit(1);
}

// Auth necesita a Config, Logger, Response y Request, así que se cargan antes.
require_once "$comun/config.php";
require_once "$comun/logger.php";
require_once "$comun/response.php";
require_once "$comun/request.php";
require_once "$comun/auth.php";

// Las clases de Backend.
require_once __DIR__ . '/../src/Jwt.php';
require_once __DIR__ . '/../src/ValidadorRegistro.php';
