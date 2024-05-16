<?php
# Script creating acdh:hasPixelWidth and acdh:hasPixelHeight
# for data already existing in the repository
include '/home/www-data/vendor/autoload.php';

$pdo = new PDO('pgsql:');
$repo = acdhOeaw\arche\lib\RepoDb::factory('/home/www-data/config/yaml/config-repo.yaml');
$ph = $repo->getSchema()->imagePxHeight;
$pw = $repo->getSchema()->imagePxWidth;
$q = $pdo->prepare("
    SELECT id 
    FROM metadata m
    WHERE
        property = 'https://vocabs.acdh.oeaw.ac.at/schema#hasFormat'
	AND value LIKE 'image/%' 
	AND value NOT LIKE 'image/svg%'
        AND value <> 'image/vnd.dxf'
        AND NOT EXISTS (SELECT 1 FROM metadata WHERE id = m.id AND property = ?)
    ORDER BY id
");
$q->execute([$ph]);
$images = $q->fetchAll(PDO::FETCH_COLUMN);
$pdo->beginTransaction();
$q = $pdo->prepare("INSERT INTO metadata (id, property, type, lang, value_n, value) VALUES (?, ?, 'http://www.w3.org/2001/XMLSchema#positiveInteger', '', ?, ?)");
$N = count($images);
foreach($images as $n => $id) {
    if ($n % 100 === 0) {
        echo ($n + 1) . " / $N\n";
        $pdo->commit();
        $pdo->beginTransaction();
    }
    $path = sprintf('/home/www-data/data/%02d/%02d/%d', $id % 100, floor($id / 100) % 100, $id);
    $ret = getimagesize($path);
    if (is_array($ret)) {
        $h = $ret[1];
	$w = $ret[0];
	$q->execute([$id, $ph, $h, $h]);
	$q->execute([$id, $pw, $w, $w]);
    } else {
	// getimagesize() files for gigabyte-size images but the exiftool manages
        exec("exiftool '$path'", $output);
	$w = array_filter($output, fn($x) => str_starts_with($x, 'Image Width'));
	$w = (int) preg_replace('`^.*: *`', '', reset($w));
	$h = array_filter($output, fn($x) => str_starts_with($x, 'Image Height'));
	$h = (int) preg_replace('`^.*: *`', '', reset($h));
	if ($w > 0 && $h > 0) {
	    $q->execute([$id, $ph, $h, $h]);
	    $q->execute([$id, $pw, $w, $w]);
	} else {
            echo "$path doesn't exist or is not an image $w $h\n";
	}
    }
}
$pdo->commit();

