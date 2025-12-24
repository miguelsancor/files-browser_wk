<?php
declare(strict_types=1);

/**
 * Seguridad básica:
 * - Bloquea traversal (..)
 * - Normaliza separadores
 * - Solo opera dentro de LOCAL_BASE_PATH
 */

function normalizeRel(string $rel): string {
  $rel = str_replace("\\", "/", $rel);
  $rel = preg_replace("#/+#", "/", $rel);
  $rel = trim($rel, "/");

  if ($rel === "") return "";
  if (str_contains($rel, "..")) return ""; // bloquea traversal
  return $rel;
}

function joinPath(string $base, string $rel): string {
  $base = rtrim($base, "/");
  $rel = normalizeRel($rel);
  return $base . ($rel !== "" ? "/" . $rel : "");
}

function isWithinBase(string $base, string $path): bool {
  $baseReal = realpath($base);
  $pathReal = realpath($path);
  if ($baseReal === false || $pathReal === false) return false;
  $baseReal = rtrim(str_replace("\\", "/", $baseReal), "/") . "/";
  $pathReal = rtrim(str_replace("\\", "/", $pathReal), "/") . "/";
  return str_starts_with($pathReal, $baseReal);
}

function localList(string $basePath, string $relDir): array {
  $basePath = rtrim($basePath, "/");
  $relDir = normalizeRel($relDir);
  $target = joinPath($basePath, $relDir);

  if (!is_dir($target)) {
    return ["ok"=>false,"error"=>"Directorio no disponible: $target","items"=>[],"target"=>$target];
  }

  $entries = @scandir($target);
  if ($entries === false) {
    return ["ok"=>false,"error"=>"No se pudo listar el directorio (permisos?).","items"=>[],"target"=>$target];
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

  usort($items, function($a, $b) {
    if ($a["type"] !== $b["type"]) return $a["type"] === "dir" ? -1 : 1;
    return strcasecmp($a["name"], $b["name"]);
  });

  $writable = is_writable($target);

  return ["ok"=>true,"error"=>null,"items"=>$items,"target"=>$target,"writable"=>$writable];
}

/**
 * Acciones de gestión
 */

function createFolder(string $basePath, string $relDir, string $folderName): array {
  $basePath = rtrim($basePath, "/");
  $relDir = normalizeRel($relDir);

  $folderName = trim($folderName);
  if ($folderName === "") return ["ok"=>false,"error"=>"Nombre de carpeta vacío."];

  // Sanitiza nombre (evita separadores/rutas)
  if (str_contains($folderName, "/") || str_contains($folderName, "\\") || str_contains($folderName, "..")) {
    return ["ok"=>false,"error"=>"Nombre de carpeta inválido."];
  }

  $targetDir = joinPath($basePath, $relDir);
  if (!is_dir($targetDir)) return ["ok"=>false,"error"=>"Directorio destino no existe."];

  $newPath = $targetDir . "/" . $folderName;
  if (file_exists($newPath)) return ["ok"=>false,"error"=>"La carpeta ya existe."];

  if (!@mkdir($newPath, 0775, false)) {
    return ["ok"=>false,"error"=>"No se pudo crear la carpeta (permisos?)."];
  }

  return ["ok"=>true,"error"=>null];
}

function deleteItem(string $basePath, string $relDir, string $name): array {
  $basePath = rtrim($basePath, "/");
  $relDir = normalizeRel($relDir);
  $name = trim($name);

  if ($name === "" || str_contains($name, "/") || str_contains($name, "\\") || str_contains($name, "..")) {
    return ["ok"=>false,"error"=>"Nombre inválido."];
  }

  $targetDir = joinPath($basePath, $relDir);
  $path = $targetDir . "/" . $name;

  if (!file_exists($path)) return ["ok"=>false,"error"=>"El elemento no existe."];

  // Seguridad: debe estar dentro del base
  if (!isWithinBase($basePath, $path)) return ["ok"=>false,"error"=>"Operación no permitida."];

  if (is_dir($path)) {
    // Solo borra carpeta vacía
    $entries = @scandir($path);
    if ($entries === false) return ["ok"=>false,"error"=>"No se pudo acceder a la carpeta."];
    if (count(array_diff($entries, [".",".."])) > 0) {
      return ["ok"=>false,"error"=>"Solo se permite borrar carpetas vacías."];
    }
    if (!@rmdir($path)) return ["ok"=>false,"error"=>"No se pudo borrar la carpeta (permisos?)."];
    return ["ok"=>true,"error"=>null];
  } else {
    if (!@unlink($path)) return ["ok"=>false,"error"=>"No se pudo borrar el archivo (permisos?)."];
    return ["ok"=>true,"error"=>null];
  }
}

function renameItem(string $basePath, string $relDir, string $oldName, string $newName): array {
  $basePath = rtrim($basePath, "/");
  $relDir = normalizeRel($relDir);

  $oldName = trim($oldName);
  $newName = trim($newName);

  foreach ([$oldName,$newName] as $n) {
    if ($n === "" || str_contains($n, "/") || str_contains($n, "\\") || str_contains($n, "..")) {
      return ["ok"=>false,"error"=>"Nombre inválido."];
    }
  }

  $targetDir = joinPath($basePath, $relDir);
  $oldPath = $targetDir . "/" . $oldName;
  $newPath = $targetDir . "/" . $newName;

  if (!file_exists($oldPath)) return ["ok"=>false,"error"=>"El elemento origen no existe."];
  if (file_exists($newPath)) return ["ok"=>false,"error"=>"Ya existe un elemento con el nuevo nombre."];

  if (!isWithinBase($basePath, $oldPath) || !isWithinBase($basePath, $targetDir)) {
    return ["ok"=>false,"error"=>"Operación no permitida."];
  }

  if (!@rename($oldPath, $newPath)) return ["ok"=>false,"error"=>"No se pudo renombrar (permisos?)."];
  return ["ok"=>true,"error"=>null];
}

function uploadFile(string $basePath, string $relDir, array $file): array {
  $basePath = rtrim($basePath, "/");
  $relDir = normalizeRel($relDir);

  $targetDir = joinPath($basePath, $relDir);
  if (!is_dir($targetDir)) return ["ok"=>false,"error"=>"Directorio destino no existe."];

  if (!isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK) {
    return ["ok"=>false,"error"=>"Error subiendo archivo."];
  }

  $origName = (string)($file["name"] ?? "file");
  $origName = trim($origName);
  $origName = str_replace(["/","\\"], "_", $origName);
  $origName = str_replace("..", "_", $origName);

  if ($origName === "") $origName = "file_upload";

  // Si existe, agrega sufijo
  $dest = $targetDir . "/" . $origName;
  if (file_exists($dest)) {
    $pi = pathinfo($origName);
    $base = $pi["filename"] ?? "file";
    $ext  = isset($pi["extension"]) ? ".".$pi["extension"] : "";
    $i = 1;
    do {
      $dest = $targetDir . "/" . $base . "_" . $i . $ext;
      $i++;
    } while (file_exists($dest));
  }

  $tmp = (string)$file["tmp_name"];
  if (!@move_uploaded_file($tmp, $dest)) {
    return ["ok"=>false,"error"=>"No se pudo guardar el archivo (permisos?)."];
  }

  return ["ok"=>true,"error"=>null];
}
