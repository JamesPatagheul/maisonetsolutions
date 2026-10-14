<?php
// build.php : lancé automatiquement par Netlify à chaque publication.
// Lit content/data.json et génère le site HTML complet dans le dossier dist/.
define('ROOT', __DIR__);
define('OUT', __DIR__.'/dist');
putenv('MS_DB='.sys_get_temp_dir().'/ms-'.getmypid().'.db'); putenv('MS_NOSEED=1');
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "ERREUR : PHP sans SQLite (pdo_sqlite). Voir LISEZMOI.txt.\n"); exit(1); }
require __DIR__.'/system/lib.php';
require __DIR__.'/system/render.php';

$data = json_decode(file_get_contents(ROOT.'/content/data.json'), true);
if (!is_array($data)) { fwrite(STDERR, "ERREUR : content/data.json illisible.\n"); exit(1); }
$d = db();
foreach (['categories','services','articles','pages','projects','reviews','faqs','team','items'] as $t) {
  $cols = array_column($d->query("PRAGMA table_info($t)")->fetchAll(), 'name');
  $d->exec("DELETE FROM $t");
  foreach ($data[$t] ?? [] as $r) {
    foreach (['body'] as $h) if (isset($r[$h])) $r[$h] = clean_html($r[$h]);   // sécurité : on nettoie le HTML
    $r = array_intersect_key($r, array_flip($cols));
    $d->prepare("INSERT INTO $t(".implode(',', array_keys($r)).") VALUES(:".implode(',:', array_keys($r)).")")->execute($r);
  }
}
foreach (($data['settings'] ?? []) as $k => $v) S_set($k, (string)$v);

function rrm($p){ if(!file_exists($p)) return; if(is_file($p)||is_link($p)){ unlink($p); return; } foreach(scandir($p) as $f) if($f!=='.'&&$f!=='..') rrm("$p/$f"); rmdir($p); }
function rcp($a,$b){ if(is_file($a)){ @mkdir(dirname($b),0755,true); copy($a,$b); return; } @mkdir($b,0755,true); foreach(scandir($a) as $f) if($f!=='.'&&$f!=='..') rcp("$a/$f","$b/$f"); }
rrm(OUT); mkdir(OUT, 0755, true);
$err = build_all();
if ($err) { fwrite(STDERR, "ERREUR d'écriture : ".implode(', ', $err)."\n"); exit(1); }
foreach (['assets','uploads','admin'] as $dir) if (is_dir(ROOT.'/'.$dir)) rcp(ROOT.'/'.$dir, OUT.'/'.$dir);
@unlink(OUT.'/uploads/.gitkeep');
file_put_contents(OUT.'/_headers', "/admin/*\n  X-Robots-Tag: noindex\n  Cache-Control: no-store\n/assets/*\n  Cache-Control: public, max-age=604800\n/uploads/*\n  Cache-Control: public, max-age=2592000\n");
@unlink(getenv('MS_DB'));
echo "Site généré : ".count(glob(OUT.'/*.html'))." pages à la racine, ".count($data['services'] ?? [])." services, ".count($data['articles'] ?? [])." articles.\n";
