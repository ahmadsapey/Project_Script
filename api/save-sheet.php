<?php
header('Content-Type: application/json');

$baseDir = realpath(__DIR__ . '/..');
$imgDir = $baseDir . '/Gambar';

if (!is_dir($imgDir)) {
    mkdir($imgDir, 0755, true);
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
    echo json_encode(['success' => false, 'error' => 'Payload invalid. Expected JSON { items: [...] }.']);
    exit;
}

function normalizeDriveImageUrl($url) {
    if (!is_string($url) || trim($url) === '') {
        return $url;
    }
    $clean = trim($url);
    if (preg_match('/(?:drive\.google\.com\/file\/d\/|drive\.google\.com\/open\?id=|drive\.google\.com\/uc\?id=|docs\.google\.com\/uc\?id=)([a-zA-Z0-9_-]+)/', $clean, $m)) {
        return 'https://drive.google.com/uc?export=view&id=' . $m[1];
    }
    return $clean;
}

function downloadRemoteImage($url, $imgDir) {
    $url = normalizeDriveImageUrl($url);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $content = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($content === false || $httpCode >= 400) {
        return null;
    }

    $ext = 'jpg';
    if ($contentType) {
        if (stripos($contentType, 'png') !== false) $ext = 'png';
        elseif (stripos($contentType, 'jpeg') !== false || stripos($contentType, 'jpg') !== false) $ext = 'jpg';
        elseif (stripos($contentType, 'gif') !== false) $ext = 'gif';
        elseif (stripos($contentType, 'webp') !== false) $ext = 'webp';
        elseif (stripos($contentType, 'svg') !== false) $ext = 'svg';
    }

    $filename = uniqid('img_') . '.' . $ext;
    $path = $imgDir . '/' . $filename;

    if (file_put_contents($path, $content) === false) {
        return null;
    }

    return $filename;
}

function isRemoteUrl($url) {
    return is_string($url) && preg_match('/^https?:\/\//i', trim($url));
}

$items = $data['items'];
$updated = [];

foreach ($items as $item) {
    if (!is_array($item)) {
        $updated[] = $item;
        continue;
    }

    $copy = $item;
    if (isset($copy['image']) && isRemoteUrl($copy['image'])) {
        $localImage = downloadRemoteImage($copy['image'], $imgDir);
        if ($localImage !== null) {
            $copy['image'] = '/Gambar/' . $localImage;
        } else {
            $copy['image'] = normalizeDriveImageUrl($copy['image']);
        }
    } elseif (isset($copy['image'])) {
        $copy['image'] = normalizeDriveImageUrl($copy['image']);
    }

    $updated[] = $copy;
}

$jsContent = 'window.DATA_SHEET = ' . json_encode($updated, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . ';';
$savePath = $baseDir . '/data_sheet.js';

if (file_put_contents($savePath, $jsContent) === false) {
    echo json_encode(['success' => false, 'error' => 'Gagal menulis data_sheet.js ke server.']);
    exit;
}

echo json_encode(['success' => true, 'items' => $updated, 'path' => '/data_sheet.js']);
