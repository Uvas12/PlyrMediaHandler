
# PlyrMediaHandler

**PlyrMediaHandler** is a MediaWiki extension that adds modern HTML5 audio and video playback using [Plyr](https://plyr.io/).

The extension provides custom MediaWiki media handlers for selected multimedia formats, renders audio and video files with a modern player, displays multimedia information on file pages, and can optionally integrate with **DeletePagesForGood** to perform real permanent cleanup of multimedia files.

---

## Features

- Modern audio and video player powered by Plyr.
- Custom MediaWiki media handlers for selected multimedia formats.
- Support for audio and video playback in wiki pages.
- Responsive audio and video rendering.
- Fullscreen video support.
- Video centering in fullscreen mode.
- Aspect-ratio preservation.
- Prevention of video stretching using `object-fit: contain`.
- Custom audio player styling.
- Video poster/frame generation using browser canvas.
- Multimedia information box on file pages.
- Recommended MediaWiki syntax display.
- Compact rendering inside tables and file history pages.
- VisualEditor-safe fallback mode.
- Optional integration with DeletePagesForGood.
- Permanent deletion cleanup for physical media files.
- Cleanup of current files, old file versions, thumbnails and deleted-file records.
- Cache purge after permanent file deletion.

---

## Supported formats

### Video

```txt
mp4
mkv
```

### Audio

```txt
mp3
flac
opus
wav
ogg
```

### Not supported by this extension

The following formats are intentionally not included:

```txt
webm
avi
mov
m4a
m4v
ogv
oga
aac
```

---

## Requirements

- MediaWiki with extension loading through `wfLoadExtension`.
- PHP compatible with your MediaWiki installation.
- Local MediaWiki file repository.
- Browser support for the uploaded media codecs.
- Plyr JavaScript and CSS files included in the extension.
- `getID3` library for multimedia metadata extraction.
- Optional: DeletePagesForGood for permanent file deletion integration.

---

# Installation

## 1. Copy the extension

Copy the extension folder into your MediaWiki extensions directory:

```txt
extensions/PlyrMediaHandler/
```

The path should look like this:

```txt
extensions/PlyrMediaHandler/extension.json
extensions/PlyrMediaHandler/src/
extensions/PlyrMediaHandler/resources/
extensions/PlyrMediaHandler/vendor.zip
```

---

## 2. Install dependencies

PlyrMediaHandler uses the **getID3** PHP library to read multimedia metadata such as:

- duration,
- bitrate,
- MIME type,
- audio codec,
- video codec,
- resolution,
- media type.

There are two ways to install the dependency:

1. Using Composer.
2. Extracting the included `vendor.zip` if Composer is not available.

---

## Option A: Install dependencies with Composer

Use this option if you have SSH or terminal access.

Go to the extension directory:

```bash
cd extensions/PlyrMediaHandler
```

Then run:

```bash
composer install
```

This will create:

```txt
extensions/PlyrMediaHandler/vendor/
```

The expected structure is:

```txt
extensions/PlyrMediaHandler/vendor/autoload.php
extensions/PlyrMediaHandler/vendor/james-heinrich/getid3/
```

If the extension package does not include a `composer.json`, create one with this content:

```json
{
    "require": {
        "james-heinrich/getid3": "^1.9"
    }
}
```

Then run:

```bash
composer install
```

---

## Option B: Extract the included `vendor.zip`

Use this option if you **do not have access to Composer** on your hosting/server.

The extension includes:

```txt
vendor.zip
```

inside:

```txt
extensions/PlyrMediaHandler/
```

Extract:

```txt
extensions/PlyrMediaHandler/vendor.zip
```

directly inside:

```txt
extensions/PlyrMediaHandler/
```

After extracting, the structure must be:

```txt
extensions/PlyrMediaHandler/vendor/autoload.php
extensions/PlyrMediaHandler/vendor/james-heinrich/getid3/
```

Correct:

```txt
extensions/PlyrMediaHandler/vendor/autoload.php
```

Incorrect:

```txt
extensions/PlyrMediaHandler/vendor/vendor/autoload.php
```

If your file manager creates an extra nested folder, move the inner `vendor/` folder directly into:

```txt
extensions/PlyrMediaHandler/
```

For normal shared hosting installations without Composer access, extracting the included `vendor.zip` is enough.

---

## 3. Enable the extension

Add this line to `LocalSettings.php`:

```php
wfLoadExtension( 'PlyrMediaHandler' );
```

---

## 4. Optional: enable DeletePagesForGood integration

If you use DeletePagesForGood, load both extensions:

```php
wfLoadExtension( 'DeletePagesForGood' );
wfLoadExtension( 'PlyrMediaHandler' );
```

Recommended order:

```php
wfLoadExtension( 'DeletePagesForGood' );
wfLoadExtension( 'PlyrMediaHandler' );
```

Make sure the File namespace is enabled for permanent deletion:

```php
$wgDeletePagesForGoodNamespaces[NS_FILE] = true;
```

PlyrMediaHandler can listen to the custom hook:

```txt
DeletePagesForGood::BeforePermanentFileDelete
```

to clean multimedia files during permanent deletion.

---

## 5. Clear cache

After installation, clear browser cache:

```txt
Ctrl + F5
```

If your server uses OPcache, restart PHP or wait until OPcache refreshes.

---

# Quick installation summary

## With Composer

```bash
cd extensions/PlyrMediaHandler
composer install
```

Then add to `LocalSettings.php`:

```php
wfLoadExtension( 'PlyrMediaHandler' );
```

## Without Composer

Extract the included:

```txt
vendor.zip
```

inside:

```txt
extensions/PlyrMediaHandler/
```

Confirm this file exists:

```txt
extensions/PlyrMediaHandler/vendor/autoload.php
```

Then add to `LocalSettings.php`:

```php
wfLoadExtension( 'PlyrMediaHandler' );
```

---

# Folder structure

## Before extracting `vendor.zip`

```txt
PlyrMediaHandler/
├── extension.json
├── README.md
├── vendor.zip
├── src/
├── i18n/
└── resources/
```

## After extracting `vendor.zip`

```txt
PlyrMediaHandler/
├── extension.json
├── README.md
├── vendor.zip
├── vendor/
│   ├── autoload.php
│   └── james-heinrich/
│       └── getid3/
├── src/
│   ├── Hooks.php
│   ├── MetadataReader.php
│   ├── PlyrAudioHandler.php
│   ├── PlyrOggHandler.php
│   ├── PlyrTransformOutput.php
│   ├── PlyrVideoHandler.php
│   └── DeletePagesForGoodIntegration.php
├── i18n/
│   ├── en.json
│   └── es.json
└── resources/
    ├── ext.plyrMediaHandler.css
    ├── ext.plyrMediaHandler.js
    └── lib/
        └── plyr/
            ├── plyr.min.css
            └── plyr.min.js
```

---

# Usage

After installation, upload a supported media file and embed it using normal MediaWiki file syntax.

## Audio example

```wiki
[[File:Example.flac|400px]]
```

## Video example

```wiki
[[File:Example.mp4|640px]]
```

## MKV example

```wiki
[[File:Example.mkv|640px]]
```

## OPUS example

```wiki
[[File:Example.opus|400px]]
```

## OGG example

```wiki
[[File:Example.ogg|400px]]
```

---

# Player behavior

## Audio

Audio files are rendered with a Plyr audio player.

Supported audio formats:

```txt
mp3
flac
opus
wav
ogg
```

The audio player can include:

- play button,
- progress bar,
- current time,
- duration,
- mute control,
- volume control,
- playback speed settings.

Inside tables and compact contexts, some controls may be hidden to avoid layout issues.

---

## Video

Video files are rendered with a Plyr video player.

Supported video formats:

```txt
mp4
mkv
```

The video player can include:

- large play button,
- play button,
- progress bar,
- current time,
- duration,
- mute control,
- volume control,
- settings menu,
- fullscreen button.

The video player is configured to avoid stretching and preserve the original aspect ratio.

---

# Fullscreen behavior

The extension includes CSS and JavaScript adjustments for fullscreen playback.

Fullscreen behavior includes:

- video is centered,
- video keeps its original aspect ratio,
- video does not stretch,
- video uses `object-fit: contain`,
- controls auto-hide,
- controls return when the mouse moves,
- rounded corners are removed in fullscreen,
- cursor can hide while controls are hidden.

---

# Normal mode controls

In normal page view, video controls fade in and out without sliding downward.

This avoids the default behavior where the control bar visually slides down when disappearing.

---

# Poster generation

For videos without a poster image, the extension can generate a poster frame using JavaScript and browser canvas.

Generated posters are stored in browser `localStorage`.

This improves the appearance of video files before playback.

---

# Multimedia information box

On file pages, the extension can display a multimedia information box.

The box may include:

```txt
MIME type
Duration
Resolution
Video codec
Audio codec
Bitrate
Recommended syntax
```

Example:

```txt
Multimedia information
MIME type: audio/flac
Duration: 1:00
Audio codec: flac
Bitrate: 995 kbps
Recommended syntax: [[File:Example.flac|400px]]
```

Metadata extraction is handled by:

```txt
src/MetadataReader.php
```

When available, metadata is extracted using:

```txt
getID3
```

---

# Metadata notes

Metadata availability depends on:

- file format,
- MIME type detected by MediaWiki,
- whether the file is locally readable,
- whether `getID3` is installed,
- whether the file was uploaded before metadata support was added,
- whether the browser and server can access the media file.

For files uploaded before metadata changes, you may need to:

- upload a new version,
- purge the file page,
- clear MediaWiki cache,
- test with a newly uploaded file.

---

# MIME handling

The extension registers MIME aliases for several formats.

Examples:

```php
$wgMimeTypeAliases['application/ogg'] = 'audio/ogg';
$wgMimeTypeAliases['application/opus'] = 'audio/opus';

$wgMimeTypeAliases['audio/x-flac'] = 'audio/flac';
$wgMimeTypeAliases['audio/x-wav'] = 'audio/wav';

$wgMimeTypeAliases['video/matroska'] = 'video/x-matroska';
$wgMimeTypeAliases['application/x-matroska'] = 'video/x-matroska';
```

This helps MediaWiki treat multimedia files correctly.

---

# VisualEditor support

When media is displayed inside VisualEditor, the extension avoids aggressive Plyr initialization.

Instead, it switches to a native fallback mode.

This helps prevent layout and editing issues while still allowing media to be visible in the editor.

---

# Table and file history support

The extension includes compact behavior for files displayed inside tables, file history pages and datatables.

In compact contexts:

- audio width is reduced,
- video width is limited,
- some controls may be hidden,
- settings menus may be disabled,
- layout is constrained to avoid table overflow.

---

