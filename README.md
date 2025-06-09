# Image Gallery API – Technical & Usage Guide

## Table of Contents

- [Image Gallery API – Technical \& Usage Guide](#image-gallery-api--technical--usage-guide)
  - [Table of Contents](#table-of-contents)
  - [Overview](#overview)
  - [Requirements](#requirements)
  - [Installation \& Configuration](#installation--configuration)
  - [Directory Structure](#directory-structure)
  - [API Workflow](#api-workflow)
  - [Function Reference](#function-reference)
    - [`getEventsOverview($baseDir)`](#geteventsoverviewbasedir)
    - [`getAlbumsList($eventDir, $baseDir)`](#getalbumslisteventdir-basedir)
    - [`getJpgFilesRecursive($directory, $event, $album)`](#getjpgfilesrecursivedirectory-event-album)
    - [`serveImage($filePath, $width, $height, $watermarkText)`](#serveimagefilepath-width-height-watermarktext)
  - [API Endpoints \& Examples](#api-endpoints--examples)
    - [1. List Events](#1-list-events)
    - [2. List Albums](#2-list-albums)
    - [3. List Photos](#3-list-photos)
    - [4. Serve Image](#4-serve-image)
  - [Response Format](#response-format)
  - [Error Handling](#error-handling)
  - [Performance \& Caching](#performance--caching)
  - [Extending \& Depth Recursion](#extending--depth-recursion)
  - [Frontend UI Integration](#frontend-ui-integration)
  - [Best Practices](#best-practices)

---

## Overview

This PHP-based API (`index.php`) dynamically discovers JPEG images in a nested folder hierarchy, exposing endpoints to:

* Enumerate **events** (first-level folders)
* Enumerate **albums** within an event (second-level folders)
* Enumerate **photos** within an album, returning metadata and generate URLs
* **Serve** raw or resized (width/height) images, with optional watermark overlay

The API returns JSON for all list operations and serves JPEG bytes for image requests.

---

## Requirements

* PHP 7.2+ with **GD** extension
* Read access to the gallery root directory
* Font file (`arial.ttf`) in the same directory as `index.php` (or adjust path)
* Web server configuration to route requests to `index.php` (e.g., Apache `mod_rewrite` or Nginx `try_files`)

---

## Installation & Configuration

1. **Place files**:

   ```
   /path/to/galleries/1_CONVENTION_PHOTO/2025/
   ├── index.php
   ├── arial.ttf
   └── [EventName]/...
   ```
2. **Set `BASE_URL`** at the top of `index.php` to your public URL:

   ```php
   const BASE_URL = 'https://alleycatphoto.net/galleries/1_CONVENTION_PHOTO/2025/';
   ```
3. **Ensure** GD extension is enabled (`phpinfo()` or `php -m`).
4. (Optional) Adjust watermark font by replacing `arial.ttf`.

---

## Directory Structure

```
/galleries/1_CONVENTION_PHOTO/2025/
├── index.php
├── arial.ttf
├── EventA/
│   ├── Album1/
│   │   ├── photo1.jpg
│   │   └── photo2.jpg
│   └── Album2/
│       └── ...
└── EventB/
    └── ...
```

* **Events**: first-level subdirectories
* **Albums**: child directories of an event containing `.jpg` files
* **Photos**: `.jpg` files within an album

---

## API Workflow

1. **No `event` parameter** → call `getEventsOverview()` → JSON listing of events
2. **`?event=X` only** → call `getAlbumsList()` → JSON listing of albums for event X
3. **`?event=X&album=Y`** → call `getJpgFilesRecursive()` → JSON listing of photos in album Y
4. **`?event=X&album=Y&image=Z`** → call `serveImage()` → serve raw or resized JPEG bytes

---

## Function Reference

### `getEventsOverview($baseDir)`

* Scans **baseDir** for first-level folders
* Returns array of `['event', 'album_count', 'url']`

### `getAlbumsList($eventDir, $baseDir)`

* Scans **eventDir** for direct subfolders containing `.jpg`
* Returns array of `['album','path','jpg_count','url']`

### `getJpgFilesRecursive($directory, $event, $album)`

* Recursively scans `$directory/$event/$album` for `.jpg` files
* Returns metadata: `filename`, `path` (relative), `size`, `width`, `height`, `modified`

### `serveImage($filePath, $width, $height, $watermarkText)`

* Resizes to `$width` or `$height` (preserves aspect ratio if one missing)
* Overlays watermark if `$watermarkText` provided (45% opacity, diagonal repeat)
* Emits `Content-Type: image/jpeg` and raw JPEG bytes

---

## API Endpoints & Examples

### 1. List Events

```http
GET /index.php
```

Response:

```json
{
  "events": [
    {"event":"EventA","album_count":2,"url":"https://.../?event=EventA"},
    {"event":"EventB","album_count":1,"url":"https://.../?event=EventB"}
  ]
}
```

### 2. List Albums

```http
GET /index.php?event=EventA
```

Response:

```json
{
  "albums": [
    {"album":"Album1","path":"EventA/Album1","jpg_count":5,
     "url":"https://.../?event=EventA&album=Album1"},
    // ...
  ]
}
```

### 3. List Photos

```http
GET /index.php?event=EventA&album=Album1
```

Response:

```json
{
  "images": [
    {
      "filename":"photo1.jpg",
      "path":"EventA/Album1/photo1.jpg",
      "size":123456,
      "width":1920,
      "height":1080,
      "modified":"2025-06-01T14:00:00+00:00",
      "url_thumb":"https://.../?event=EventA&album=Album1&image=photo1.jpg&width=250&watermark=true",
      "url_proof":"https://.../?event=EventA&album=Album1&image=photo1.jpg&width=800&watermark=true",
      "url_orig":"https://.../?event=EventA&album=Album1&image=photo1.jpg"
    }
    // ...
  ]
}
```

### 4. Serve Image

```http
GET /index.php?event=EventA&album=Album1&image=photo1.jpg&width=800&height=600&watermark=true
```

* Returns raw JPEG bytes, resized to 800×600 with watermark overlay.

```http
GET /index.php?event=EventA&album=Album1&image=photo1.jpg
```

* Returns original JPEG without watermark.

---

## Response Format

* JSON for list endpoints (`Content-Type: application/json`)
* JPEG for image endpoint (`Content-Type: image/jpeg`)

---

## Error Handling

Any failure returns:

```json
{ "error": "Description of error" }
```

Common cases:

* Invalid `event` or `album`: **`Directory does not exist`**
* Invalid `image`: **`Image not found`**
* Missing or invalid parameters: **`Invalid parameters`**

---

## Performance & Caching

* **Directory scans** use PHP iterators; consider caching album/event lists in memory or files for large trees.
* **Image resizing** is CPU-intensive; use HTTP caching headers (`Cache-Control`, `ETag`) or CDN.

---

## Extending & Depth Recursion

* **Photos recursion**: `getJpgFilesRecursive()` uses PHP’s `RecursiveIteratorIterator` to scan infinitely deep within a specified event/album path for JPG files.
* **Albums recursion**: To support multi-level nested albums (e.g., sub-albums or thematic folders), replace `getAlbumsList()` with a recursive pattern:

  ```php
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
      \$eventDir, FilesystemIterator::SKIP_DOTS
    ), RecursiveIteratorIterator::SELF_FIRST
  );
  foreach (\$iterator as \$info) {
    if (\$info->isDir()) { /* collect album metadata */ }
  }
  ```
* **UI tree structure**: Build a hierarchical JSON tree of events→albums→sub-albums→photos, then consume using a tree view or accordion in your frontend.

---

## Frontend UI Integration

This API can be consumed directly by your UI agent (React, Vue, Angular, etc.) to build dynamic galleries.

1. **Fetch Events**

   ```js
   fetch(`${BASE_URL}index.php`)
     .then(res => res.json())
     .then(data => setEvents(data.events));
   ```

2. **Fetch Albums** (on event select)

   ```js
   fetch(`${BASE_URL}index.php?event=${encodeURIComponent(eventName)}`)
     .then(res => res.json())
     .then(data => setAlbums(data.albums));
   ```

3. **Fetch Photos** (on album select)

   ```js
   fetch(
     `${BASE_URL}index.php?event=${encodeURIComponent(eventName)}&album=${encodeURIComponent(albumName)}`
   )
     .then(res => res.json())
     .then(data => setPhotos(data.images));
   ```

4. **Display Thumbnails & Proofs**

   * Use `url_thumb` for fast-loading thumbnails.
   * On click, swap to `url_proof` or `url_orig` for larger view.

5. **Recursive Rendering**
   If using nested albums, parse a tree of nodes and render expand/collapse controls. Each node may include:

   ```json
   { id, name, children: [...], images: [...] }
   ```

6. **Error Handling**

   * Check for `error` in JSON and display user-friendly messages.
   * Provide loading spinners during fetch operations.

---

## Best Practices

* **URL-encode** parameters with spaces or special chars.
* Configure **AllowEncodedSlashes** for embedded slashes in album names.
* Store **securely** if gallery contains private images (add authentication layer).
* Use **CDN** for high-traffic scenarios.

---

*End of Guide*
