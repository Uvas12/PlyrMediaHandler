<?php

namespace MediaWiki\Extension\PlyrMediaHandler;

use MediaTransformOutput;
use MediaWiki\Html\Html;

class PlyrTransformOutput extends MediaTransformOutput {

    protected $file;
    protected $params;
    protected $kind;
    protected $mime;

    public function __construct( $file, array $params, string $kind ) {
        $this->file = $file;
        $this->params = $params;
        $this->kind = $kind;
        $this->mime = $file->getMimeType();

        $this->width = isset( $params['width'] ) ? (int)$params['width'] : 0;
        $this->height = isset( $params['height'] ) ? (int)$params['height'] : 0;
        $this->path = false;
        $this->url = $file->getFullUrl();
    }

    public function toHtml( $options = [] ) {
        $url = $this->file->getFullUrl();
        $title = $this->file->getTitle();
        $name = $title ? $title->getText() : $this->file->getName();

        $width = max( 1, (int)( $this->params['width'] ?? 640 ) );
        $height = isset( $this->params['height'] ) ? (int)$this->params['height'] : 0;

        if ( $this->kind === 'video' ) {
            return $this->videoHtml( $url, $name, $width, $height );
        }

        return $this->audioHtml( $url, $name, $width );
    }

    private function getFileExtension(): string {
        $name = '';
    
        if ( $this->file && method_exists( $this->file, 'getName' ) ) {
            $name = $this->file->getName();
        } elseif ( $this->file && method_exists( $this->file, 'getTitle' ) && $this->file->getTitle() ) {
            $name = $this->file->getTitle()->getText();
        }
    
        return strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    }

    private function getBrowserSourceAttributes( string $url ): array {
        $attrs = [
            'src' => $url
        ];
    
        $extension = $this->getFileExtension();
    
        /*
         * Algunos contenedores como AVI, MKV y MOV pueden tener códecs variados.
         * Si declaramos un MIME que el navegador no acepta bien, puede descartar
         * el archivo antes de intentar reproducirlo.
         *
         * Por eso, para esos formatos dejamos el <source> sin type.
         */
        if ( in_array( $extension, [ 'avi', 'mkv', 'mov' ], true ) ) {
            return $attrs;
        }
    
        if ( $this->mime ) {
            $attrs['type'] = $this->mime;
        }
    
        return $attrs;
    }

    private function videoHtml( string $url, string $name, int $width, int $height ): string {
        if ( $height <= 0 ) {
            $height = (int)round( $width * 9 / 16 );
        }

        $wrapperStyle = 'width:' . $width . 'px;max-width:100%;';
        $videoStyle = 'width:100%;max-width:100%;aspect-ratio:' . $width . '/' . $height . ';';

        $attrs = [
            'class' => 'mw-plyr-player mw-plyr-video',
            'controls' => true,
            'playsinline' => true,
            'preload' => 'metadata',
            'style' => $videoStyle,
            'data-plyr-mediahandler' => 'video',
            'data-plyr-width' => (string)$width,
            'data-plyr-height' => (string)$height,
            'data-plyr-title' => $name
        ];

        $source = Html::element(
            'source',
            $this->getBrowserSourceAttributes( $url )
        );

        $fallback = Html::rawElement(
            'p',
            [ 'class' => 'mw-plyr-fallback' ],
            Html::element(
                'a',
                [
                    'href' => $url,
                    'target' => '_blank',
                    'rel' => 'noopener'
                ],
                wfMessage( 'plyrmediahandler-player-error' )->text()
            )
        );

        $video = Html::rawElement(
            'video',
            $attrs,
            $source . $fallback
        );

        return Html::rawElement(
            'div',
            [
                'class' => 'mw-plyr-wrapper mw-plyr-video-wrapper',
                'style' => $wrapperStyle,
                'data-plyr-wrapper' => 'video',
                'data-plyr-width' => (string)$width,
                'data-plyr-height' => (string)$height
            ],
            $video
        );
    }

    private function audioHtml( string $url, string $name, int $width ): string {
        $wrapperStyle = 'width:' . $width . 'px;max-width:100%;';
        $audioStyle = 'width:100%;max-width:100%;';

        $attrs = [
            'class' => 'mw-plyr-player mw-plyr-audio',
            'controls' => true,
            'preload' => 'metadata',
            'style' => $audioStyle,
            'data-plyr-mediahandler' => 'audio',
            'data-plyr-width' => (string)$width,
            'data-plyr-title' => $name
        ];

        $source = Html::element(
            'source',
            $this->getBrowserSourceAttributes( $url )
        );

        $fallback = Html::rawElement(
            'p',
            [ 'class' => 'mw-plyr-fallback' ],
            Html::element(
                'a',
                [
                    'href' => $url,
                    'target' => '_blank',
                    'rel' => 'noopener'
                ],
                wfMessage( 'plyrmediahandler-player-error' )->text()
            )
        );

        $audio = Html::rawElement(
            'audio',
            $attrs,
            $source . $fallback
        );

        return Html::rawElement(
            'div',
            [
                'class' => 'mw-plyr-wrapper mw-plyr-audio-wrapper',
                'style' => $wrapperStyle,
                'data-plyr-wrapper' => 'audio',
                'data-plyr-width' => (string)$width
            ],
            $audio
        );
    }

    public function getUrl() {
        return $this->url;
    }

    public function getStoragePath() {
        return false;
    }

    public function isError() {
        return false;
    }
}