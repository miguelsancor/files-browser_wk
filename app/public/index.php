<?php
declare(strict_types=1);

require __DIR__ . "/../src/config.php";
require __DIR__ . "/../src/local_list.php";

$mode = appMode();
$dir  = $_GET["dir"] ?? "";
$dir  = is_string($dir) ? $dir : "";
$dir  = normalizeRel($dir);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }
function fmtSize(?int $bytes): string {
  if ($bytes === null) return "-";
  $units = ["B","KB","MB","GB","TB"];
  $i = 0; $b = (float)$bytes;
  while ($b >= 1024 && $i < count($units)-1) { $b /= 1024; $i++; }
  return rtrim(rtrim(number_format($b, 2), "0"), ".") . " " . $units[$i];
}
function fmtTime(?int $t): string { return $t ? date("Y-m-d H:i:s", $t) : "-"; }
function redirectTo(string $dir): void {
  $q = $dir !== "" ? ("?dir=" . urlencode($dir)) : "";
  header("Location: /" . $q);
  exit;
}

$base = envv("LOCAL_BASE_PATH", "/mnt/windows_share");
$message = null;
$errorMsg = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";
  $action = is_string($action) ? $action : "";
  $action = trim($action);

  // Siempre operamos en el "dir" actual
  $currentDir = $_POST["dir"] ?? $dir;
  $currentDir = is_string($currentDir) ? $currentDir : "";
  $currentDir = normalizeRel($currentDir);

  if ($action === "create_folder") {
    $name = (string)($_POST["folder_name"] ?? "");
    $res = createFolder($base, $currentDir, $name);
    if ($res["ok"]) $message = "Carpeta creada.";
    else $errorMsg = $res["error"] ?? "Error creando carpeta.";
    redirectTo($currentDir);
  }

  if ($action === "delete") {
    $name = (string)($_POST["name"] ?? "");
    $res = deleteItem($base, $currentDir, $name);
    if ($res["ok"]) $message = "Elemento eliminado.";
    else $errorMsg = $res["error"] ?? "Error eliminando.";
    redirectTo($currentDir);
  }

  if ($action === "rename") {
    $old = (string)($_POST["old_name"] ?? "");
    $new = (string)($_POST["new_name"] ?? "");
    $res = renameItem($base, $currentDir, $old, $new);
    if ($res["ok"]) $message = "Elemento renombrado.";
    else $errorMsg = $res["error"] ?? "Error renombrando.";
    redirectTo($currentDir);
  }

  if ($action === "upload") {
    $file = $_FILES["file"] ?? null;
    if (!is_array($file)) {
      $errorMsg = "No se recibió archivo.";
      redirectTo($currentDir);
    }
    $res = uploadFile($base, $currentDir, $file);
    if ($res["ok"]) $message = "Archivo subido.";
    else $errorMsg = $res["error"] ?? "Error subiendo archivo.";
    redirectTo($currentDir);
  }

  redirectTo($currentDir);
}

$result = localList($base, $dir);

$pathNow = $dir === "" ? "/" : ("/" . trim($dir, "/") . "/");
$parent = "";
if ($dir !== "") {
  $parts = explode("/", trim($dir, "/"));
  array_pop($parts);
  $parent = implode("/", $parts);
}

$writable = (bool)($result["writable"] ?? false);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>File Browser (<?= h($mode) ?>)</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:24px;background:#0b1220;color:#e5e7eb}
    .card{background:#111a2e;border:1px solid #223155;border-radius:12px;padding:16px;max-width:1100px}
    a{color:#93c5fd;text-decoration:none}
    a:hover{text-decoration:underline}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:10px;border-bottom:1px solid #223155;text-align:left;font-size:14px}
    .muted{color:#94a3b8}
    .err{background:#3b1020;border:1px solid #6b1a2f;padding:12px;border-radius:10px}
    .ok{background:#0f2f1d;border:1px solid #1f5a33;padding:12px;border-radius:10px}
    .top{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .pill{display:inline-block;background:#0b2447;border:1px solid #1d3b66;padding:6px 10px;border-radius:999px;font-size:12px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}
    .panel{background:#0d162b;border:1px solid #223155;border-radius:12px;padding:12px}
    input,button{font-size:14px}
    input[type="text"]{width:100%;padding:10px;border-radius:10px;border:1px solid #223155;background:#0b1220;color:#e5e7eb}
    input[type="file"]{width:100%;color:#e5e7eb}
    button{padding:10px 12px;border-radius:10px;border:1px solid #223155;background:#142042;color:#e5e7eb;cursor:pointer}
    button:hover{background:#172a55}
    .danger{border-color:#6b1a2f;background:#2a0f18}
    .danger:hover{background:#3b1020}
    .row{display:flex;gap:10px;align-items:center}
    .row > div{flex:1}
    .small{font-size:12px}
    .right{display:flex;gap:8px;justify-content:flex-end}
  </style>
</head>
<body>
  <div class="card">
    <div class="top">
      <div>
        <div class="pill">Mode: <b><?= h($mode) ?></b></div>
        <div class="pill">Path: <b><?= h($pathNow) ?></b></div>
        <?php if (!empty($result["target"])): ?>
          <div class="pill">Target: <b><?= h((string)$result["target"]) ?></b></div>
        <?php endif; ?>
        <div class="pill">Writable: <b><?= $writable ? "YES" : "NO" ?></b></div>
      </div>
      <div class="muted">Gestión: Upload / Crear carpeta / Renombrar / Borrar</div>
    </div>

    <?php if (!$result["ok"]): ?>
      <div class="err" style="margin-top:14px;">
        <b>Error:</b> <?= h((string)$result["error"]) ?><br/>
        <span class="muted">Tip: monta/crea contenido en <b>/mnt/windows_share</b> y verifica permisos.</span>
      </div>
    <?php endif; ?>

    <?php if (!$writable): ?>
      <div class="err" style="margin-top:14px;">
        <b>Atención:</b> El directorio actual no tiene permisos de escritura. Podrás navegar/listar, pero no subir/crear/renombrar/borrar.
      </div>
    <?php endif; ?>

    <div style="margin-top:14px;">
      <?php if ($dir !== ""): ?>
        <a href="?dir=<?= h($parent) ?>">⬅ Volver</a>
      <?php endif; ?>
    </div>

    <div class="grid">
      <div class="panel">
        <b>Subir archivo</b>
        <div class="muted small">Guarda el archivo en la carpeta actual.</div>
        <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="dir" value="<?= h($dir) ?>">
          <input type="file" name="file" required>
          <div class="right" style="margin-top:10px;">
            <button type="submit" <?= $writable ? "" : "disabled" ?>>Subir</button>
          </div>
        </form>
      </div>

      <div class="panel">
        <b>Crear carpeta</b>
        <div class="muted small">Crea una subcarpeta dentro de la carpeta actual.</div>
        <form method="post" style="margin-top:10px;">
          <input type="hidden" name="action" value="create_folder">
          <input type="hidden" name="dir" value="<?= h($dir) ?>">
          <input type="text" name="folder_name" placeholder="Ej: Documentos_2025" required>
          <div class="right" style="margin-top:10px;">
            <button type="submit" <?= $writable ? "" : "disabled" ?>>Crear</button>
          </div>
        </form>
      </div>

      <div class="panel">
        <b>Renombrar</b>
        <div class="muted small">Renombra un archivo o carpeta (en la carpeta actual).</div>
        <form method="post" style="margin-top:10px;">
          <input type="hidden" name="action" value="rename">
          <input type="hidden" name="dir" value="<?= h($dir) ?>">
          <div class="row">
            <div><input type="text" name="old_name" placeholder="Nombre actual" required></div>
            <div><input type="text" name="new_name" placeholder="Nuevo nombre" required></div>
          </div>
          <div class="right" style="margin-top:10px;">
            <button type="submit" <?= $writable ? "" : "disabled" ?>>Renombrar</button>
          </div>
        </form>
      </div>

      <div class="panel">
        <b>Borrar</b>
        <div class="muted small">Borra un archivo o una carpeta vacía (en la carpeta actual).</div>
        <form method="post" style="margin-top:10px;">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="dir" value="<?= h($dir) ?>">
          <input type="text" name="name" placeholder="Nombre exacto a borrar" required>
          <div class="right" style="margin-top:10px;">
            <button class="danger" type="submit" <?= $writable ? "" : "disabled" ?>
              onclick="return confirm('¿Seguro que deseas borrar este elemento?');">Borrar</button>
          </div>
        </form>
      </div>
    </div>

    <table>
      <thead>
        <tr><th>Nombre</th><th>Tipo</th><th>Tamaño</th><th>Modificado</th></tr>
      </thead>
      <tbody>
        <?php foreach (($result["items"] ?? []) as $it): ?>
          <?php
            $name = (string)$it["name"];
            $type = (string)$it["type"];
            $nextDir = trim($dir . "/" . $name, "/");
          ?>
          <tr>
            <td>
              <?php if ($type === "dir"): ?>
                📁 <a href="?dir=<?= h($nextDir) ?>"><?= h($name) ?></a>
              <?php else: ?>
                📄 <?= h($name) ?>
              <?php endif; ?>
            </td>
            <td><?= h($type) ?></td>
            <td><?= h(fmtSize($it["size"] ?? null)) ?></td>
            <td><?= h(fmtTime($it["mtime"] ?? null)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
