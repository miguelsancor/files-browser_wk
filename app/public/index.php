<?php
declare(strict_types=1);

require __DIR__ . "/../src/config.php";
require __DIR__ . "/../src/local_list.php";

$mode = appMode();
$dir  = $_GET["dir"] ?? "";
$dir  = is_string($dir) ? $dir : "";

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }
function fmtSize(?int $bytes): string {
  if ($bytes === null) return "-";
  $units = ["B","KB","MB","GB","TB"];
  $i = 0; $b = (float)$bytes;
  while ($b >= 1024 && $i < count($units)-1) { $b /= 1024; $i++; }
  return rtrim(rtrim(number_format($b, 2), "0"), ".") . " " . $units[$i];
}
function fmtTime(?int $t): string { return $t ? date("Y-m-d H:i:s", $t) : "-"; }

$base = envv("LOCAL_BASE_PATH", "/mnt/windows_share");
$result = localList($base, $dir);

$pathNow = $dir === "" ? "/" : ("/" . trim($dir, "/") . "/");
$parent = "";
if ($dir !== "") {
  $parts = explode("/", trim($dir, "/"));
  array_pop($parts);
  $parent = implode("/", $parts);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>File Browser (<?= h($mode) ?>)</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:24px;background:#0b1220;color:#e5e7eb}
    .card{background:#111a2e;border:1px solid #223155;border-radius:12px;padding:16px;max-width:980px}
    a{color:#93c5fd;text-decoration:none}
    a:hover{text-decoration:underline}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:10px;border-bottom:1px solid #223155;text-align:left;font-size:14px}
    .muted{color:#94a3b8}
    .err{background:#3b1020;border:1px solid #6b1a2f;padding:12px;border-radius:10px}
    .top{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .pill{display:inline-block;background:#0b2447;border:1px solid #1d3b66;padding:6px 10px;border-radius:999px;font-size:12px}
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
      </div>
      <div class="muted">Configurable por <b>.env</b> (LOCAL_BASE_PATH).</div>
    </div>

    <?php if (!$result["ok"]): ?>
      <div class="err" style="margin-top:14px;">
        <b>Error:</b> <?= h((string)$result["error"]) ?><br/>
        <span class="muted">Tip: pon archivos dentro de <b>data/</b> (host) para verlos en el navegador.</span>
      </div>
    <?php endif; ?>

    <div style="margin-top:14px;">
      <?php if ($dir !== ""): ?>
        <a href="?dir=<?= h($parent) ?>">⬅ Volver</a>
      <?php endif; ?>
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
