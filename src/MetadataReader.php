<?php

namespace MediaWiki\Extension\PlyrMediaHandler;

class MetadataReader {

    public const DEFAULT_VIDEO_WIDTH = 1280;
    public const DEFAULT_VIDEO_HEIGHT = 720;
    public const DEFAULT_AUDIO_WIDTH = 400;
    public const DEFAULT_AUDIO_HEIGHT = 54;

    public static function readFileMetadata( $file ): array {
        $mime = null;
        $path = null;

        if ( is_object( $file ) && method_exists( $file, 'getMimeType' ) ) {
            $mime = $file->getMimeType();
        }

        if ( is_object( $file ) && method_exists( $file, 'getLocalRefPath' ) ) {
            $path = $file->getLocalRefPath();
        }

        if ( !$path && is_object( $file ) && method_exists( $file, 'getPath' ) ) {
            $path = $file->getPath();
        }

        if ( !$path && is_string( $file ) ) {
            $path = $file;
        }

        return self::readPathMetadata( $path, $mime, null );
    }

    public static function readPathMetadata( ?string $path, ?string $mime = null, ?string $forcedMediaType = null ): array {
        $mediaType = $forcedMediaType ?: self::detectMediaTypeFromMime( $mime );

        if ( $mediaType === 'unknown' ) {
            $mediaType = self::detectMediaTypeFromPath( $path );
        }

        $default = [
            'mime' => $mime,
            'duration' => null,
            'width' => null,
            'height' => null,
            'video_codec' => null,
            'audio_codec' => null,
            'bitrate' => null,
            'media_type' => $mediaType
        ];

        $default = self::applyExtensionFallbacks( $default, $path, $mime );

        if ( !$path || !is_readable( $path ) ) {
            return self::applyFallbacks( $default );
        }

        self::loadGetId3();

        if ( !class_exists( '\getID3' ) ) {
            return self::applyFallbacks( $default );
        }

        try {
            $getID3 = new \getID3();
            $getID3->setOption( [ 'encoding' => 'UTF-8' ] );

            $info = $getID3->analyze( $path );

            $metadata = $default;

            if ( isset( $info['mime_type'] ) && !$metadata['mime'] ) {
                $metadata['mime'] = (string)$info['mime_type'];
            }

            if ( $metadata['media_type'] === 'unknown' ) {
                $metadata['media_type'] = self::detectMediaTypeFromMime( $metadata['mime'] );
            }

            if ( $metadata['media_type'] === 'unknown' ) {
                $metadata['media_type'] = self::detectMediaTypeFromPath( $path );
            }

            if ( isset( $info['playtime_seconds'] ) ) {
                $metadata['duration'] = (float)$info['playtime_seconds'];
            }

            if ( isset( $info['bitrate'] ) ) {
                $metadata['bitrate'] = (int)$info['bitrate'];
            } elseif ( isset( $info['audio']['bitrate'] ) ) {
                $metadata['bitrate'] = (int)$info['audio']['bitrate'];
            }

            if ( isset( $info['video']['resolution_x'] ) ) {
                $metadata['width'] = (int)$info['video']['resolution_x'];
            }

            if ( isset( $info['video']['resolution_y'] ) ) {
                $metadata['height'] = (int)$info['video']['resolution_y'];
            }

            if ( isset( $info['video']['codec'] ) ) {
                $metadata['video_codec'] = (string)$info['video']['codec'];
            } elseif ( isset( $info['video']['fourcc_lookup'] ) ) {
                $metadata['video_codec'] = (string)$info['video']['fourcc_lookup'];
            } elseif ( isset( $info['video']['dataformat'] ) ) {
                $metadata['video_codec'] = (string)$info['video']['dataformat'];
            }

            if ( isset( $info['audio']['codec'] ) ) {
                $metadata['audio_codec'] = (string)$info['audio']['codec'];
            } elseif ( isset( $info['audio']['dataformat'] ) ) {
                $metadata['audio_codec'] = (string)$info['audio']['dataformat'];
            } elseif ( isset( $info['audio']['data_format'] ) ) {
                $metadata['audio_codec'] = (string)$info['audio']['data_format'];
            } elseif ( isset( $info['fileformat'] ) && $metadata['media_type'] === 'audio' ) {
                $metadata['audio_codec'] = (string)$info['fileformat'];
            }

            $metadata = self::applyExtensionFallbacks( $metadata, $path, $metadata['mime'] ?? $mime );

            return self::applyFallbacks( $metadata );
        } catch ( \Throwable $e ) {
            return self::applyFallbacks( $default );
        }
    }

    private static function loadGetId3(): void {
        if ( class_exists( '\getID3' ) ) {
            return;
        }

        $paths = [
            dirname( __DIR__ ) . '/vendor/autoload.php',
            dirname( __DIR__, 3 ) . '/vendor/autoload.php'
        ];

        foreach ( $paths as $path ) {
            if ( is_readable( $path ) ) {
                require_once $path;

                if ( class_exists( '\getID3' ) ) {
                    return;
                }
            }
        }
    }

    public static function detectMediaTypeFromMime( ?string $mime ): string {
        if ( !is_string( $mime ) || $mime === '' ) {
            return 'unknown';
        }

        $mime = strtolower( $mime );

        if ( strpos( $mime, 'video/' ) === 0 ) {
            return 'video';
        }

        if ( strpos( $mime, 'audio/' ) === 0 ) {
            return 'audio';
        }

        if ( in_array( $mime, [
            'application/ogg',
            'application/opus',
            'application/x-ogg',
            'application/x-opus'
        ], true ) ) {
            return 'audio';
        }

        return 'unknown';
    }

    public static function detectMediaTypeFromPath( ?string $path ): string {
        if ( !$path ) {
            return 'unknown';
        }

        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        if ( in_array( $extension, [
            'mp4',
            'mkv'
        ], true ) ) {
            return 'video';
        }

        if ( in_array( $extension, [
            'mp3',
            'flac',
            'opus',
            'wav',
            'ogg'
        ], true ) ) {
            return 'audio';
        }

        return 'unknown';
    }

    private static function applyExtensionFallbacks( array $metadata, ?string $path, ?string $mime = null ): array {
        $extension = '';

        if ( $path ) {
            $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        }

        $mime = is_string( $mime ) ? strtolower( $mime ) : '';

        if ( empty( $metadata['media_type'] ) || $metadata['media_type'] === 'unknown' ) {
            $metadata['media_type'] = self::detectMediaTypeFromMime( $mime );

            if ( $metadata['media_type'] === 'unknown' ) {
                $metadata['media_type'] = self::detectMediaTypeFromPath( $path );
            }
        }

        if ( empty( $metadata['mime'] ) ) {
            if ( $extension === 'ogg' ) {
                $metadata['mime'] = 'audio/ogg';
            } elseif ( $extension === 'opus' ) {
                $metadata['mime'] = 'audio/opus';
            }
        }

        if ( $metadata['media_type'] === 'audio' && empty( $metadata['audio_codec'] ) ) {
            if ( $extension === 'opus' || $mime === 'audio/opus' || $mime === 'application/opus' ) {
                $metadata['audio_codec'] = 'opus';
            } elseif ( $extension === 'ogg' || $mime === 'audio/ogg' || $mime === 'application/ogg' ) {
                $metadata['audio_codec'] = 'ogg';
            } elseif ( $extension === 'flac' ) {
                $metadata['audio_codec'] = 'flac';
            } elseif ( $extension === 'mp3' ) {
                $metadata['audio_codec'] = 'mp3';
            } elseif ( $extension === 'wav' ) {
                $metadata['audio_codec'] = 'wav';
            }
        }

        return $metadata;
    }

    public static function isVideo( $file ): bool {
        if ( is_object( $file ) && method_exists( $file, 'getMimeType' ) ) {
            return self::detectMediaTypeFromMime( $file->getMimeType() ) === 'video';
        }

        if ( is_object( $file ) && method_exists( $file, 'getLocalRefPath' ) ) {
            return self::detectMediaTypeFromPath( $file->getLocalRefPath() ) === 'video';
        }

        return false;
    }

    public static function isAudio( $file ): bool {
        if ( is_object( $file ) && method_exists( $file, 'getMimeType' ) ) {
            $type = self::detectMediaTypeFromMime( $file->getMimeType() );

            if ( $type === 'audio' ) {
                return true;
            }
        }

        if ( is_object( $file ) && method_exists( $file, 'getLocalRefPath' ) ) {
            return self::detectMediaTypeFromPath( $file->getLocalRefPath() ) === 'audio';
        }

        return false;
    }

    public static function applyFallbacks( array $metadata ): array {
        if ( empty( $metadata['media_type'] ) ) {
            $metadata['media_type'] = self::detectMediaTypeFromMime( $metadata['mime'] ?? null );
        }

        if ( $metadata['media_type'] === 'video' ) {
            if ( empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
                $metadata['width'] = self::DEFAULT_VIDEO_WIDTH;
                $metadata['height'] = self::DEFAULT_VIDEO_HEIGHT;
            }
        }

        if ( $metadata['media_type'] === 'audio' ) {
            $metadata['width'] = self::DEFAULT_AUDIO_WIDTH;
            $metadata['height'] = self::DEFAULT_AUDIO_HEIGHT;
        }

        return $metadata;
    }

    public static function formatDuration( ?float $seconds ): string {
        if ( $seconds === null ) {
            return '';
        }

        $seconds = max( 0, (int)round( $seconds ) );
        $hours = intdiv( $seconds, 3600 );
        $minutes = intdiv( $seconds % 3600, 60 );
        $secs = $seconds % 60;

        if ( $hours > 0 ) {
            return sprintf( '%d:%02d:%02d', $hours, $minutes, $secs );
        }

        return sprintf( '%d:%02d', $minutes, $secs );
    }

    public static function formatBitrate( ?int $bitrate ): string {
        if ( !$bitrate ) {
            return '';
        }

        return number_format( $bitrate / 1000, 0 ) . ' kbps';
    }
}