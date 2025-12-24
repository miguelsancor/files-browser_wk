<?php
declare(strict_types=1);

function normalizeRel(string $rel): string {
  $rel = str_replace("\\", "/", $rel);
  $rel = preg_replace("#/+#", "/", $rel);
  $rel = trim($rel, "/");

  if ($rel === "") return "";

  // Bloquea traversal
  if (str_contains($rel, "..")) return "";

  return $rel;
}

function localList(string $basePath, string $relDir): array {
  $basePath = rtrim($basePath, "/");
  $relDir = normalizeRel($relDir);

  $target = $basePath . ($relDir !== "" ? "/" . $relDir : "");

  if (!is_dir($target)) {
    return [
      "ok" => false,
      "error" => "Directorio no disponible: $target",
      "items" => [],
      "target" => $target
    ];
  }

  $entries = @scandir($target);
  if ($entries === false) {
    return [
      "ok" => false,
      "error" => "No se pudo listar el directorio (permisos?).",
      "items" => [],
      "target" => $target
    ];
  }

  $items = [];
  foreach ($entries as $e) {
    if ($e === "." || $e === "..") continue;

    $full = $target . "/" . $e;
    $isDir = is_dir($full);

    $items[] = [
      "name" => $e,
      "type" => $isDir ? "dir" : "file",
      "size" => $isDir ? null : (@filesize($full) ?: null),
      "mtime" => (@filemtime($full) ?: null),
    ];
  }

  // Directorios primero
  usort($items, function($a, $b) {
    if ($a["type"] !== $b["type"]) return $a["type"] === "dir" ? -1 : 1;
    return strcasecmp($a["name"], $b["name"]);
  });

  return ["ok" => true, "error" => null, "items" => $items, "target" => $target];
}
