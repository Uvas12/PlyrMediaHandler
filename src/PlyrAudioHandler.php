<?php

namespace MediaWiki\Extension\PlyrMediaHandler;

use MediaHandler;

class PlyrAudioHandler extends MediaHandler {

    public function getParamMap() {
        return [
            'img_width' => 'width'
        ];
    }

    public function validateParam( $name, $value ) {
        if ( $name === 'width' ) {
            return is_numeric( $value ) && (int)$value > 0;
        }

        if ( $name === 'height' ) {
            return false;
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

        if ( preg_match( '/^(\d+)x\d+px$/i', $str, $matches ) ) {
            return [
                'width' => (int)$matches[1]
            ];
        }

        return [];
    }

    public function normaliseParams( $file, &$params ) {
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
        return [
            MetadataReader::DEFAULT_AUDIO_WIDTH,
            MetadataReader::DEFAULT_AUDIO_HEIGHT
        ];
    }

    public function getSizeAndMetadata( $file, $path ) {
        $mime = null;

        if ( is_object( $file ) && method_exists( $file, 'getMimeType' ) ) {
            $mime = $file->getMimeType();
        }

        $metadata = MetadataReader::readPathMetadata( $path, $mime, 'audio' );

        return [
            'width' => MetadataReader::DEFAULT_AUDIO_WIDTH,
            'height' => MetadataReader::DEFAULT_AUDIO_HEIGHT,
            'metadata' => serialize( $metadata )
        ];
    }

    public function doTransform( $file, $dstPath, $dstUrl, $params, $flags = 0 ) {
        $this->normaliseParams( $file, $params );

        return new PlyrTransformOutput( $file, $params, 'audio' );
    }

    public function getMetadataType( $image ) {
        return 'plyr-audio-metadata';
    }

    public function isMetadataValid( $image, $metadata ) {
        return self::METADATA_GOOD;
    }

    public function getDimensionsString( $file ) {
        return MetadataReader::DEFAULT_AUDIO_WIDTH . ' × ' . MetadataReader::DEFAULT_AUDIO_HEIGHT;
    }

    public function getLongDesc( $file ) {
        $metadata = MetadataReader::readFileMetadata( $file );
        $parts = [];

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