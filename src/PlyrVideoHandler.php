<?php

namespace MediaWiki\Extension\PlyrMediaHandler;

use MediaHandler;

class PlyrVideoHandler extends MediaHandler {

    private function getExtensionFromFile( $file ): string {
        if ( is_object( $file ) && method_exists( $file, 'getName' ) ) {
            $name = $file->getName();
        } elseif ( is_object( $file ) && method_exists( $file, 'getTitle' ) && $file->getTitle() ) {
            $name = $file->getTitle()->getText();
        } else {
            $name = '';
        }

        return strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    }

    private function shouldRenderAsAudio( $file ): bool {
        $extension = $this->getExtensionFromFile( $file );

        return in_array( $extension, [
            'mp3',
            'm4a',
            'ogg',
            'oga',
            'opus',
            'flac',
            'wav'
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
            return $file->getMimeType();
        }

        return null;
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
        if ( $this->shouldRenderAsAudio( $file ) ) {
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

    public function getImageSize( $file, $path ) {
        if ( $this->shouldRenderAsAudio( $file ) ) {
            return [
                MetadataReader::DEFAULT_AUDIO_WIDTH,
                MetadataReader::DEFAULT_AUDIO_HEIGHT
            ];
        }

        $mime = $this->getMimeFromFile( $file );
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

    public function getSizeAndMetadata( $file, $path ) {
        $mime = $this->getMimeFromFile( $file );

        if ( $this->shouldRenderAsAudio( $file ) ) {
            $metadata = MetadataReader::readPathMetadata( $path, $mime, 'audio' );

            return [
                'width' => MetadataReader::DEFAULT_AUDIO_WIDTH,
                'height' => MetadataReader::DEFAULT_AUDIO_HEIGHT,
                'metadata' => serialize( $metadata )
            ];
        }

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

    public function doTransform( $file, $dstPath, $dstUrl, $params, $flags = 0 ) {
        $this->normaliseParams( $file, $params );

        if ( $this->shouldRenderAsAudio( $file ) ) {
            return new PlyrTransformOutput( $file, $params, 'audio' );
        }

        return new PlyrTransformOutput( $file, $params, 'video' );
    }

    public function getMetadataType( $image ) {
        return 'plyr-video-metadata';
    }

    public function isMetadataValid( $image, $metadata ) {
        return self::METADATA_GOOD;
    }

    public function getDimensionsString( $file ) {
        if ( $this->shouldRenderAsAudio( $file ) ) {
            return MetadataReader::DEFAULT_AUDIO_WIDTH . ' × ' . MetadataReader::DEFAULT_AUDIO_HEIGHT;
        }

        $path = $this->getPathFromFile( $file );
        $mime = $this->getMimeFromFile( $file );
        $metadata = MetadataReader::readPathMetadata( $path, $mime, 'video' );

        if ( empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
            return '';
        }

        return $metadata['width'] . ' × ' . $metadata['height'];
    }

    public function getLongDesc( $file ) {
        $metadata = MetadataReader::readFileMetadata( $file );
        $parts = [];

        if (
            !$this->shouldRenderAsAudio( $file ) &&
            !empty( $metadata['width'] ) &&
            !empty( $metadata['height'] )
        ) {
            $parts[] = $metadata['width'] . ' × ' . $metadata['height'];
        }

        if ( !empty( $metadata['duration'] ) ) {
            $parts[] = MetadataReader::formatDuration( $metadata['duration'] );
        }

        if ( !empty( $metadata['mime'] ) ) {
            $parts[] = $metadata['mime'];
        }

        return implode( ', ', $parts );
    }
}