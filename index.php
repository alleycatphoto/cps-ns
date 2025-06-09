<?php
// --- CORS & PREFLIGHT HANDLING ---

// Allow requests from any origin (less secure, use your specific domain in production)
header("Access-Control-Allow-Origin: *"); 
// Or, for a specific origin:
// header("Access-Control-Allow-Origin: http://localhost:your_dev_port");
// header("Access-Control-Allow-Origin: https://your_production_domain.com");

header("Access-Control-Allow-Methods: GET, OPTIONS"); // Specify allowed methods
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Specify allowed headers

// Handle preflight OPTIONS requests (important for some CORS scenarios)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  http_response_code(200);
  exit();
}

// --- API LOGIC ---

header('Content-Type: application/json');

// --- CONFIGURATION ---

// Base URL constant
const BASE_URL = 'https://alleycatphoto.net/galleries/2_TIPTOP_PHOTO/2025%20Jobs/';

//-- CACHE: Caching Configuration
const CACHE_ENABLED = true; // Set to false to disable caching entirely
const CACHE_DIR = __DIR__ . '/cache'; // The directory to store cache files
const CACHE_LIFETIME = 3600; // Cache lifetime in seconds (3600 = 1 hour)

// --- CACHE HELPER FUNCTIONS ---

function get_cache($key) {
    if (!CACHE_ENABLED) return false;
    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0775, true);
    }

    $file = CACHE_DIR . '/' . $key . '.cache';
    if (file_exists($file) && (time() - filemtime($file)) < CACHE_LIFETIME) {
        $data = @unserialize(file_get_contents($file));
        return $data !== false ? $data : false;
    }
    return false;
}

function set_cache($key, $data) {
    if (!CACHE_ENABLED || !is_dir(CACHE_DIR) || !is_writable(CACHE_DIR)) {
        return;
    }
    $file = CACHE_DIR . '/' . $key . '.cache';
    file_put_contents($file, serialize($data), LOCK_EX);
}


// --- API FUNCTIONS ---

function getJpgFilesInDir($directory, $event = '', $album = '') {
    $cacheKey = 'jpgs_in_dir_' . md5($event . '_' . $album);
    $cachedData = get_cache($cacheKey);
    if ($cachedData !== false) {
        return $cachedData;
    }

    $result = [];
    $path = rtrim($directory, DIRECTORY_SEPARATOR);

    if ($event) {
        $path .= DIRECTORY_SEPARATOR . basename($event);
    }
    if ($album) {
        $album_path_parts = explode('/', $album);
        $safe_album_path = implode(DIRECTORY_SEPARATOR, array_map('basename', $album_path_parts));
        $path .= DIRECTORY_SEPARATOR . $safe_album_path;
    }

    if (!is_dir($path)) {
        return ['error' => 'Directory does not exist'];
    }

    foreach (new DirectoryIterator($path) as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'jpg') {
            $filePath = $file->getRealPath();
            list($imgW, $imgH) = @getimagesize($filePath);
            $result[] = [
                'filename' => $file->getFilename(),
                'path'     => str_replace($directory . DIRECTORY_SEPARATOR, '', $filePath),
                'size'     => $file->getSize(),
                'width'    => $imgW,
                'height'   => $imgH,
                'modified' => date('c', $file->getMTime()),
            ];
        }
    }
    
    set_cache($cacheKey, $result);
    return $result;
}


function getJpgFilesRecursive($directory, $event = '', $album = '') {
    $cacheKey = 'jpgs_recursive_' . md5($event . '_' . $album);
    $cachedData = get_cache($cacheKey);
    if ($cachedData !== false) {
        return $cachedData;
    }

    $result = [];
    $event = basename($event);

    $path = rtrim($directory, DIRECTORY_SEPARATOR);
    if ($event) {
        $path .= DIRECTORY_SEPARATOR . $event;
    }
    if ($album) {
        $album_path_parts = explode('/', $album);
        $safe_album_path = implode(DIRECTORY_SEPARATOR, array_map('basename', $album_path_parts));
        $path .= DIRECTORY_SEPARATOR . $safe_album_path;
    }

    if (!is_dir($path)) {
        return ['error' => 'Directory does not exist'];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'jpg') {
            $filePath = $file->getRealPath();
            list($imgW, $imgH) = @getimagesize($filePath);
            $result[] = [
                'filename' => $file->getFilename(),
                'path'     => str_replace($directory . DIRECTORY_SEPARATOR, '', $filePath),
                'size'     => $file->getSize(),
                'width'    => $imgW,
                'height'   => $imgH,
                'modified' => date('c', $file->getMTime()),
            ];
        }
    }
    set_cache($cacheKey, $result);
    return $result;
}

function getAlbumsList($eventDir, $baseDir) {
    $cacheKey = 'albumlist_' . md5($eventDir);
    $cachedData = get_cache($cacheKey);
    if ($cachedData !== false) {
        return $cachedData;
    }

    $albums = [];
    if (!is_dir($eventDir)) {
        return $albums;
    }
    foreach (new DirectoryIterator($eventDir) as $item) {
        if ($item->isDir() && !$item->isDot()) {
            $jpgFiles = glob($item->getRealPath() . DIRECTORY_SEPARATOR . '*.jpg');
            $subDirs = glob($item->getRealPath() . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            if (!empty($jpgFiles) || !empty($subDirs)) {
                $albumName = $item->getFilename();
                $albums[] = [
                    'album'     => $albumName,
                    'path'      => str_replace($baseDir . DIRECTORY_SEPARATOR, '', $item->getRealPath()),
                    'jpg_count' => count($jpgFiles),
                    'url'       => BASE_URL
                                   . '?event='   . urlencode(basename($eventDir))
                                   . '&album='   . urlencode($albumName),
                ];
            }
        }
    }
    set_cache($cacheKey, $albums);
    return $albums;
}

function listSubfolders($currentDir, $event) {
    $cacheKey = 'subfolders_' . md5($currentDir);
    $cachedData = get_cache($cacheKey);
    if ($cachedData !== false) {
        return $cachedData;
    }

    $subalbums = [];
    if (!is_dir($currentDir)) {
        return $subalbums;
    }
    
    foreach (new DirectoryIterator($currentDir) as $item) {
        if ($item->isDir() && !$item->isDot()) {
            $jpgFiles = glob($item->getRealPath() . DIRECTORY_SEPARATOR . '*.jpg');
            $subDirs = glob($item->getRealPath() . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            
            if (!empty($jpgFiles) || !empty($subDirs)) {
                $fullItemPath = str_replace(DIRECTORY_SEPARATOR, '/', $item->getRealPath());
                $eventPart = '/' . basename($event) . '/';
                $newAlbumPath = $item->getFilename();
                
                $eventPosition = strpos($fullItemPath, $eventPart);
                if ($eventPosition !== false) {
                    $newAlbumPath = substr($fullItemPath, $eventPosition + strlen($eventPart));
                }

                $subalbums[] = [
                    'album'     => $item->getFilename(),
                    'path'      => str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $item->getRealPath()),
                    'jpg_count' => count($jpgFiles),
                    'url'       => BASE_URL
                                   . '?event=' . urlencode($event)
                                   . '&album=' . urlencode($newAlbumPath),
                ];
            }
        }
    }
    set_cache($cacheKey, $subalbums);
    return $subalbums;
}

function getEventsOverview($baseDir) {
    $cacheKey = 'events_overview';
    $cachedData = get_cache($cacheKey);
    if ($cachedData !== false) {
        return $cachedData;
    }

    $events = [];
    foreach (new DirectoryIterator($baseDir) as $item) {
        if ($item->isDir() && !$item->isDot()) {
            if (in_array($item->getFilename(), ['.git', '.vscode', 'cache'])) {
                continue;
            }
            $eventName = $item->getFilename();
            $eventPath = $item->getRealPath();
            $albums    = getAlbumsList($eventPath, $baseDir);
            
            $events[]  = [
                'event'       => $eventName,
                'album_count' => count($albums),
                'url'         => BASE_URL . '?event=' . urlencode($eventName),
            ];
        }
    }
    set_cache($cacheKey, $events);
    return $events;
}

function serveImage($filePath, $width = null, $height = null, $watermarkText = null) {
    if (!file_exists($filePath)) {
        http_response_code(404);
        // Ensure the output is JSON for the error message
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Image not found: ' . $filePath]);
        exit;
    }
    $img = @imagecreatefromjpeg($filePath);
    if (!$img) {
        http_response_code(415);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unsupported image format']);
        exit;
    }

    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($filePath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: $img = imagerotate($img, 180, 0); break;
                case 6: $img = imagerotate($img, -90, 0); break;
                case 8: $img = imagerotate($img, 90, 0); break;
            }
        }
    }
    $origW = imagesx($img);
    $origH = imagesy($img);

    if ($width && $height) {
        $newW = $width; $newH = $height;
    } elseif ($width) {
        $newW = $width;
        $newH = intval(($origH / $origW) * $width);
    } elseif ($height) {
        $newH = $height;
        $newW = intval(($origW / $origH) * $height);
    } else {
        $newW = $origW; $newH = $origH;
    }

    $tmp = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    if ($watermarkText) {
        $fontFile = __DIR__ . '/arial.ttf';
        if (file_exists($fontFile)) {
            $thumbW = 250;
            $thumbFont = 12;
            $fontSize = max($thumbFont, intval($thumbFont * ($newW / $thumbW)));
            $angle    = 45;
            $repeat   = str_replace(' ', ' · ', $watermarkText);
            $box      = imagettfbbox($fontSize, $angle, $fontFile, $repeat);
            $textW    = abs($box[2] - $box[0]);
            $textH    = abs($box[7] - $box[1]);
            $stepX    = intval($textW * 1.2);
            $stepY    = intval($textH * 6);
            $alpha    = intval(127 * 0.7);
            $color    = imagecolorallocatealpha($tmp, 255, 255, 255, $alpha);
            $startX = -$stepX;
            $startY = -$stepY;
            for ($y = $startY; $y < $newH * 2; $y += $stepY) {
                for ($x = $startX; $x < $newW * 2; $x += $stepX) {
                    imagettftext($tmp, $fontSize, $angle, $x, $y, $color, $fontFile, $repeat);
                }
            }
        }
    }

    // Set the correct header right before sending the image
    header('Content-Type: image/jpeg');
    imagejpeg($tmp);
    imagedestroy($img);
    imagedestroy($tmp);
    exit;
}

// --- ROUTING LOGIC ---

$event     = $_GET['event']   ?? '';
$album     = $_GET['album']   ?? '';
$image     = $_GET['image']   ?? null;
$width     = isset($_GET['width'])  ? max(1, (int)$_GET['width'])  : null;
$height    = isset($_GET['height']) ? max(1, (int)$_GET['height']) : null;
$watermark = isset($_GET['watermark']) ? 'CONVENTION PHOTOGRAPHY SERVICES' : null;
$baseDir   = __DIR__;

if ($event && $image) {
    $eventSafe = basename($event);
    $filePath = $baseDir . DIRECTORY_SEPARATOR . $eventSafe;

    if ($album) {
        $albumSegments = array_map('basename', explode('/', $album));
        $filePath .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $albumSegments);
    }

    $filePath .= DIRECTORY_SEPARATOR . basename($image);
    
    serveImage($filePath, $width, $height, $watermark);
    exit;
}

if ($event) {
    $currentPath = $baseDir . DIRECTORY_SEPARATOR . basename($event);
    if ($album) {
        $albumSegments = array_map('basename', explode('/', $album));
        $currentPath .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $albumSegments);
    }
    
    $subalbums = listSubfolders($currentPath, $event);
    
    if ($album) {
        $raw = getJpgFilesInDir($baseDir, $event, $album);
    } else {
        $raw = getJpgFilesRecursive($baseDir, $event, '');
    }
    
    $images = [];
    if (is_array($raw) && !isset($raw['error'])) {
        foreach ($raw as $img) {
            $fn = $img['filename'];
            
            $fullRelPath = str_replace('\\', '/', dirname($img['path']));
            $eventPart = basename($event) . '/';
            $albumPath = '';
            if (strpos($fullRelPath, $eventPart) !== false) {
                 $albumPath = substr($fullRelPath, strpos($fullRelPath, $eventPart) + strlen($eventPart));
            }
            
            $albumParam = urlencode($albumPath);
            $queryBase = '?event=' . urlencode($event)
                       . '&album=' . $albumParam
                       . '&image=' . urlencode($fn);
            
            $images[] = array_merge($img, [
                'url_thumb' => BASE_URL . $queryBase . '&width=250&watermark=true',
                'url_proof' => BASE_URL . $queryBase . '&width=800&watermark=true',
                'url_orig' => BASE_URL . $queryBase
            ]);
        }
    }
    
    echo json_encode([
        'subalbums' => $subalbums,
        'images' => $images
    ], JSON_PRETTY_PRINT);
    exit;
}

if (!$event && !$album && !$image) {
    echo json_encode(['events' => getEventsOverview($baseDir)], JSON_PRETTY_PRINT);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid parameters'], JSON_PRETTY_PRINT);