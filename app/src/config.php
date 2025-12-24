<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Dotenv\Dotenv;

// Carga .env desde la raíz de /var/www/html (dentro del contenedor)
$dotenv = Dotenv::createImmutable(__DIR__ . "/..");
$dotenv->safeLoad();

function envv(string $key, ?string $default = null): string {
  $v = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
  return $v === null ? "" : (string)$v;
}

function appMode(): string {
  $m = strtoupper(trim(envv("APP_MODE", "LOCAL")));
  return $m === "LOCAL" ? "LOCAL" : "LOCAL";
}
