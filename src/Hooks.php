<?php

namespace MediaWiki\Extension\PlyrMediaHandler;

use File;
use OutputPage;
use Skin;
use MediaWiki\Html\Html;
use RequestContext;

class Hooks {

    public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
        $out->addModules( 'ext.plyrMediaHandler' );
    }

    public static function onFileUpload( File $file, $reupload, $hasDescription ): void {
        if ( self::getSupportedMediaTypeFromFile( $file ) === null ) {
            return;
        }
    
        MetadataReader::readFileMetadata( $file );
    }

    public static function onArticlePurge( $wikiPage ): void {
        return;
    }

    public static function onImageOpenShowImageInlineBefore( $imagePage, $output ): bool {
        $file = method_exists( $imagePage, 'getFile' ) ? $imagePage->getFile() : null;

        if ( !$file ) {
            return true;
        }

        if ( self::getSupportedMediaTypeFromFile( $file ) === null ) {
            return true;
        }

        $out = RequestContext::getMain()->getOutput();
        $out->addModules( 'ext.plyrMediaHandler' );

        return true;
    }

    /**
     * En varias versiones de MediaWiki, ImagePageAfterImageLinks recibe:
     *
     *   ImagePage $imagePage, string &$html
     *
     * No recibe OutputPage. Por eso aquí añadimos contenido a &$html.
     */
    public static function onImagePageAfterImageLinks( $imagePage, &$html ): void {
        $file = method_exists( $imagePage, 'getFile' ) ? $imagePage->getFile() : null;

        if ( !$file ) {
            return;
        }

        $mediaType = self::getSupportedMediaTypeFromFile( $file );
        
        if ( $mediaType === null ) {
            return;
        }
        
        $mime = self::getMimeFromFile( $file );
        
        RequestContext::getMain()->getOutput()->addModules( 'ext.plyrMediaHandler' );
        
        $metadata = MetadataReader::readFileMetadata( $file );
        $metadata = self::normalizeMetadataForFile( $file, $metadata, $mediaType );

        $box = Html::openElement( 'div', [ 'class' => 'mw-plyrmediahandler-info' ] );
        $box .= Html::element( 'h2', [], wfMessage( 'plyrmediahandler-file-info' )->text() );
        $box .= Html::openElement( 'ul' );

        $box .= self::infoLine(
            wfMessage( 'plyrmediahandler-mime' )->text(),
            $metadata['mime'] ?? $mime
        );

        if ( !empty( $metadata['duration'] ) ) {
            $box .= self::infoLine(
                wfMessage( 'plyrmediahandler-duration' )->text(),
                MetadataReader::formatDuration( $metadata['duration'] )
            );
        }

        if (
            !empty( $metadata['width'] ) &&
            !empty( $metadata['height'] ) &&
            isset( $metadata['media_type'] ) &&
            $metadata['media_type'] === 'video'
        ) {
            $box .= self::infoLine(
                wfMessage( 'plyrmediahandler-resolution' )->text(),
                (int)$metadata['width'] . ' × ' . (int)$metadata['height']
            );
        }

        if ( !empty( $metadata['video_codec'] ) ) {
            $box .= self::infoLine(
                wfMessage( 'plyrmediahandler-video-codec' )->text(),
                $metadata['video_codec']
            );
        }

        if ( !empty( $metadata['audio_codec'] ) ) {
            $box .= self::infoLine(
                wfMessage( 'plyrmediahandler-audio-codec' )->text(),
                $metadata['audio_codec']
            );
        }

        if ( !empty( $metadata['bitrate'] ) ) {
            $box .= self::infoLine(
                wfMessage( 'plyrmediahandler-bitrate' )->text(),
                MetadataReader::formatBitrate( $metadata['bitrate'] )
            );
        }

        $recommendedWidth = $mediaType === 'audio' ? 400 : 640;

        $syntax = '[[File:' . $file->getName() . '|' . $recommendedWidth . 'px]]';

        $box .= self::infoLine(
            wfMessage( 'plyrmediahandler-recommended-syntax' )->text(),
            $syntax
        );

        $box .= Html::closeElement( 'ul' );
        $box .= Html::closeElement( 'div' );

        $html .= $box;
    }

    public static function onImagePageFileHistoryLine( $imagePage, $file, &$line ): void {
        if ( !$file ) {
            return;
        }

        $mediaType = self::getSupportedMediaTypeFromFile( $file );
        
        if ( $mediaType === null ) {
            return;
        }
        
        $metadata = MetadataReader::readFileMetadata( $file );
        $metadata = self::normalizeMetadataForFile( $file, $metadata, $mediaType );
        
        $extra = [];

        if ( !empty( $metadata['duration'] ) ) {
            $extra[] = MetadataReader::formatDuration( $metadata['duration'] );
        }

        if (
            !empty( $metadata['width'] ) &&
            !empty( $metadata['height'] ) &&
            isset( $metadata['media_type'] ) &&
            $metadata['media_type'] === 'video'
        ) {
            $extra[] = (int)$metadata['width'] . '×' . (int)$metadata['height'];
        }

        if ( $extra ) {
            $line .= ' <span class="mw-plyrmediahandler-history">(' .
                htmlspecialchars( implode( ', ', $extra ) ) .
                ')</span>';
        }
    }
    
    
    public static function onSetupAfterCache(): void {
        global $wgFileExtensions, $wgMimeTypeAliases;
    
        $plyrExtensions = [
            'mp4',
            'mkv',
            'mp3',
            'flac',
            'opus',
            'wav',
            'ogg'
        ];
    
        if ( !is_array( $wgFileExtensions ) ) {
            $wgFileExtensions = [];
        }
    
        foreach ( $plyrExtensions as $extension ) {
            if ( !in_array( $extension, $wgFileExtensions, true ) ) {
                $wgFileExtensions[] = $extension;
            }
        }
    
        if ( !is_array( $wgMimeTypeAliases ) ) {
            $wgMimeTypeAliases = [];
        }
    
        // MKV / Matroska
        $wgMimeTypeAliases['video/x-matroska'] = 'video/x-matroska';
        $wgMimeTypeAliases['video/matroska'] = 'video/x-matroska';
        $wgMimeTypeAliases['application/x-matroska'] = 'video/x-matroska';
    
        // OGG / OPUS detectados como application/*
        $wgMimeTypeAliases['application/ogg'] = 'audio/ogg';
        $wgMimeTypeAliases['application/opus'] = 'audio/opus';
    
        // FLAC / WAV variantes comunes
        $wgMimeTypeAliases['audio/x-flac'] = 'audio/flac';
        $wgMimeTypeAliases['audio/x-wav'] = 'audio/wav';
    }
    
    private static function getMimeFromFile( $file ): ?string {
        if ( is_object( $file ) && method_exists( $file, 'getMimeType' ) ) {
            $mime = $file->getMimeType();
    
            if ( is_string( $mime ) && $mime !== '' ) {
                return strtolower( $mime );
            }
        }
    
        return null;
    }
    
    private static function getExtensionFromFile( $file ): string {
        if ( is_object( $file ) && method_exists( $file, 'getName' ) ) {
            $name = $file->getName();
        } elseif (
            is_object( $file ) &&
            method_exists( $file, 'getTitle' ) &&
            $file->getTitle()
        ) {
            $name = $file->getTitle()->getText();
        } else {
            $name = '';
        }
    
        return strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    }
    
    private static function getSupportedMediaTypeFromFile( $file ): ?string {
        $mime = self::getMimeFromFile( $file );
        $extension = self::getExtensionFromFile( $file );
    
        if ( is_string( $mime ) && strpos( $mime, 'video/' ) === 0 ) {
            return 'video';
        }
    
        if ( is_string( $mime ) && strpos( $mime, 'audio/' ) === 0 ) {
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
    
        return null;
    }

    private static function normalizeMetadataForFile( $file, array $metadata, string $mediaType ): array {
        $mime = self::getMimeFromFile( $file );
        $extension = self::getExtensionFromFile( $file );
    
        if ( empty( $metadata['media_type'] ) || $metadata['media_type'] === 'unknown' ) {
            $metadata['media_type'] = $mediaType;
        }
    
        if ( empty( $metadata['mime'] ) ) {
            if ( $mime ) {
                $metadata['mime'] = $mime;
            } elseif ( $extension === 'ogg' ) {
                $metadata['mime'] = 'audio/ogg';
            } elseif ( $extension === 'opus' ) {
                $metadata['mime'] = 'audio/opus';
            }
        }
    
        if ( $mediaType === 'audio' && empty( $metadata['audio_codec'] ) ) {
            if (
                $extension === 'opus' ||
                $mime === 'audio/opus' ||
                $mime === 'application/opus'
            ) {
                $metadata['audio_codec'] = 'opus';
            } elseif (
                $extension === 'ogg' ||
                $mime === 'audio/ogg' ||
                $mime === 'application/ogg'
            ) {
                $metadata['audio_codec'] = 'ogg';
            } elseif ( $extension === 'flac' ) {
                $metadata['audio_codec'] = 'flac';
            } elseif ( $extension === 'mp3' ) {
                $metadata['audio_codec'] = 'mp3';
            } elseif ( $extension === 'wav' ) {
                $metadata['audio_codec'] = 'wav';
            }
        }
    
        if ( $mediaType === 'audio' ) {
            $metadata['width'] = MetadataReader::DEFAULT_AUDIO_WIDTH;
            $metadata['height'] = MetadataReader::DEFAULT_AUDIO_HEIGHT;
        }
    
        return $metadata;
    }

    private static function infoLine( string $label, $value ): string {
        if ( $value === null || $value === false ) {
            return '';
        }
    
        $value = (string)$value;
    
        if ( $value === '' ) {
            return '';
        }
    
        return Html::rawElement(
            'li',
            [],
            Html::element( 'strong', [], $label . ':' ) . ' ' . Html::element( 'span', [], $value )
        );
    }
}