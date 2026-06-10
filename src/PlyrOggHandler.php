<?php

namespace MediaWiki\Extension\PlyrMediaHandler;

use MediaHandler;

class PlyrOggHandler extends MediaHandler {

    private function getExtensionFromFile( $file ): string {
        if ( is_object( $file ) && method_exists( $file, 'getName' ) ) {
            $name = $file->getName();
        } elseif ( is_object( $file ) && method_exists( $file, 'getTitle' ) && $file->getTitle() ) {
            $name = $file->getTitle()->getText();
        } else {
            $name = '';
        }

        $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

        return $extension;
    }

    private function isVideoFile( $file ): bool {
        return false;
    }

    private function isAudioFile( $file ): bool {
        $extension = $this->getExtensionFromFile( $file );
        $mime = $this->getMimeFromFile( $file );

        if ( in_array( $extension, [
            'ogg',
            'opus'
        ], true ) ) {
            return true;
        }

        return in_array( $mime, [
            'audio/ogg',
            'application/ogg',
            'audio/opus',
            'application/opus'
        ], true );
    }

    private function getPathFromFile( $file ): ?string {
        if ( is_object( $file ) && method_exists( $file, 'getLocalRefPath' ) ) {
            $path = $file->getLocalRefPath();

            if ( $path ) {
                return $path;
            }
        }

        if ( is_object( $file ) && method_exists( $file, 'getPath' ) ) {
            $path = $file->getPath();

            if ( $path ) {
                return $path;
            }
        }

        return null;
    }

    private function getMimeFromFile( $file ): ?string {
        if ( is_object( $file ) && method_exists( $file, 'getMimeType' ) ) {
            $mime = $file->getMimeType();

            if ( $mime ) {
                return $mime;
            }
        }

        return null;
    }

    private function getFallbackMime( $file, ?string $mime ): ?string {
        if ( $mime ) {
            return $mime;
        }

        $extension = $this->getExtensionFromFile( $file );

        if ( $extension === 'opus' ) {
            return 'audio/opus';
        }

        if ( $extension === 'ogg' ) {
            return 'audio/ogg';
        }

        return null;
    }

    private function getFallbackAudioCodec( $file, ?string $mime = null ): string {
        $extension = $this->getExtensionFromFile( $file );

        if (
            $extension === 'opus' ||
            $mime === 'audio/opus' ||
            $mime === 'application/opus'
        ) {
            return 'opus';
        }

        if (
            $extension === 'ogg' ||
            $mime === 'audio/ogg' ||
            $mime === 'application/ogg'
        ) {
            return 'ogg';
        }

        return '';
    }

    private function normalizeAudioMetadata( $file, $metadata, ?string $mime = null ): array {
        if ( !is_array( $metadata ) ) {
            $metadata = [];
        }

        $mime = $this->getFallbackMime( $file, $mime );

        if ( empty( $metadata['mime'] ) && $mime ) {
            $metadata['mime'] = $mime;
        }

        if ( empty( $metadata['media_type'] ) || $metadata['media_type'] === 'unknown' ) {
            $metadata['media_type'] = 'audio';
        }

        if ( empty( $metadata['audio_codec'] ) ) {
            $fallbackCodec = $this->getFallbackAudioCodec( $file, $mime );

            if ( $fallbackCodec !== '' ) {
                $metadata['audio_codec'] = $fallbackCodec;
            }
        }

        return $metadata;
    }

    private function readAudioMetadataFromPath( $file, ?string $path, ?string $mime ): array {
        $mime = $this->getFallbackMime( $file, $mime );
        $metadata = MetadataReader::readPathMetadata( $path, $mime, 'audio' );

        return $this->normalizeAudioMetadata( $file, $metadata, $mime );
    }

    private function readAudioMetadataFromFile( $file ): array {
        $path = $this->getPathFromFile( $file );
        $mime = $this->getMimeFromFile( $file );

        return $this->readAudioMetadataFromPath( $file, $path, $mime );
    }

    public function getParamMap() {
        return [
            'img_width' => 'width'
        ];
    }

    public function validateParam( $name, $value ) {
        if ( $name === 'width' || $name === 'height' ) {
            return is_numeric( $value ) && (int)$value > 0;
        }

        return true;
    }

    public function makeParamString( $params ) {
        if ( isset( $params['width'] ) && (int)$params['width'] > 0 ) {
            return (int)$params['width'] . 'px';
        }

        return '';
    }

    public function parseParamString( $str ) {
        $str = trim( (string)$str );

        if ( preg_match( '/^(\d+)px$/i', $str, $matches ) ) {
            return [
                'width' => (int)$matches[1]
            ];
        }

        if ( preg_match( '/^(\d+)x(\d+)px$/i', $str, $matches ) ) {
            return [
                'width' => (int)$matches[1],
                'height' => (int)$matches[2]
            ];
        }

        return [];
    }

    public function normaliseParams( $file, &$params ) {
        if ( $this->isVideoFile( $file ) ) {
            $path = $this->getPathFromFile( $file );
            $mime = $this->getMimeFromFile( $file );

            $metadata = MetadataReader::readPathMetadata( $path, $mime, 'video' );

            $originalWidth = !empty( $metadata['width'] )
                ? (int)$metadata['width']
                : MetadataReader::DEFAULT_VIDEO_WIDTH;

            $originalHeight = !empty( $metadata['height'] )
                ? (int)$metadata['height']
                : MetadataReader::DEFAULT_VIDEO_HEIGHT;

            if ( $originalWidth <= 0 || $originalHeight <= 0 ) {
                $originalWidth = MetadataReader::DEFAULT_VIDEO_WIDTH;
                $originalHeight = MetadataReader::DEFAULT_VIDEO_HEIGHT;
            }

            if ( empty( $params['width'] ) ) {
                $params['width'] = 640;
            }

            $params['width'] = (int)$params['width'];

            if ( $params['width'] < 360 ) {
                $params['width'] = 360;
            }

            if ( $params['width'] > 1920 ) {
                $params['width'] = 1920;
            }

            $params['height'] = (int)round(
                $params['width'] * $originalHeight / $originalWidth
            );

            if ( $params['height'] <= 0 ) {
                $params['height'] = (int)round( $params['width'] * 9 / 16 );
            }

            return true;
        }

        if ( empty( $params['width'] ) ) {
            $params['width'] = MetadataReader::DEFAULT_AUDIO_WIDTH;
        }

        $params['width'] = (int)$params['width'];

        if ( $params['width'] < 400 ) {
            $params['width'] = 400;
        }

        if ( $params['width'] > 1200 ) {
            $params['width'] = 1200;
        }

        unset( $params['height'] );
        unset( $params['img_height'] );

        return true;
    }

    public function getImageSize( $file, $path ) {
        $mime = $this->getMimeFromFile( $file );

        if ( $this->isVideoFile( $file ) ) {
            $metadata = MetadataReader::readPathMetadata( $path, $mime, 'video' );

            $width = !empty( $metadata['width'] )
                ? (int)$metadata['width']
                : MetadataReader::DEFAULT_VIDEO_WIDTH;

            $height = !empty( $metadata['height'] )
                ? (int)$metadata['height']
                : MetadataReader::DEFAULT_VIDEO_HEIGHT;

            return [
                $width,
                $height
            ];
        }

        return [
            MetadataReader::DEFAULT_AUDIO_WIDTH,
            MetadataReader::DEFAULT_AUDIO_HEIGHT
        ];
    }

    public function getSizeAndMetadata( $file, $path ) {
        $mime = $this->getMimeFromFile( $file );

        if ( $this->isVideoFile( $file ) ) {
            $metadata = MetadataReader::readPathMetadata( $path, $mime, 'video' );

            $width = !empty( $metadata['width'] )
                ? (int)$metadata['width']
                : MetadataReader::DEFAULT_VIDEO_WIDTH;

            $height = !empty( $metadata['height'] )
                ? (int)$metadata['height']
                : MetadataReader::DEFAULT_VIDEO_HEIGHT;

            return [
                'width' => $width,
                'height' => $height,
                'metadata' => serialize( $metadata )
            ];
        }

        $metadata = $this->readAudioMetadataFromPath( $file, $path, $mime );

        return [
            'width' => MetadataReader::DEFAULT_AUDIO_WIDTH,
            'height' => MetadataReader::DEFAULT_AUDIO_HEIGHT,
            'metadata' => serialize( $metadata )
        ];
    }

    public function doTransform( $file, $dstPath, $dstUrl, $params, $flags = 0 ) {
        $this->normaliseParams( $file, $params );

        if ( $this->isVideoFile( $file ) ) {
            return new PlyrTransformOutput( $file, $params, 'video' );
        }

        return new PlyrTransformOutput( $file, $params, 'audio' );
    }

    public function getMetadataType( $image ) {
        return 'plyr-ogg-metadata';
    }

    public static function getMetadataVersion() {
        return 2;
    }

    public function isMetadataValid( $image, $metadata ) {
        if ( $metadata === false || $metadata === null || $metadata === '' ) {
            return self::METADATA_BAD;
        }

        return self::METADATA_GOOD;
    }

    public function getDimensionsString( $file ) {
        if ( $this->isVideoFile( $file ) ) {
            $path = $this->getPathFromFile( $file );
            $mime = $this->getMimeFromFile( $file );
            $metadata = MetadataReader::readPathMetadata( $path, $mime, 'video' );

            if ( !empty( $metadata['width'] ) && !empty( $metadata['height'] ) ) {
                return $metadata['width'] . ' × ' . $metadata['height'];
            }

            return '';
        }

        return MetadataReader::DEFAULT_AUDIO_WIDTH . ' × ' . MetadataReader::DEFAULT_AUDIO_HEIGHT;
    }

    public function getShortDesc( $file ) {
        return $this->getLongDesc( $file );
    }

    public function getLongDesc( $file ) {
        $kind = $this->isVideoFile( $file ) ? 'video' : 'audio';

        if ( $kind === 'video' ) {
            $metadata = MetadataReader::readFileMetadata( $file );
        } else {
            $metadata = $this->readAudioMetadataFromFile( $file );
        }

        $parts = [];

        if ( $kind === 'video' && !empty( $metadata['width'] ) && !empty( $metadata['height'] ) ) {
            $parts[] = $metadata['width'] . ' × ' . $metadata['height'];
        }

        if ( !empty( $metadata['duration'] ) ) {
            $parts[] = MetadataReader::formatDuration( $metadata['duration'] );
        }

        if ( !empty( $metadata['audio_codec'] ) ) {
            $parts[] = $metadata['audio_codec'];
        }

        if ( !empty( $metadata['mime'] ) ) {
            $parts[] = $metadata['mime'];
        }

        return implode( ', ', $parts );
    }
    
    
}