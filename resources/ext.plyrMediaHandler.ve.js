( function () {
    'use strict';

    function fixMediaElement( element ) {
        var width;
        var height;
        var ratio;

        if ( !element || !element.classList ) {
            return;
        }

        if (
            !element.classList.contains( 'mw-plyr-player' ) &&
            !element.querySelector( '.mw-plyr-player' )
        ) {
            return;
        }

        if ( element.classList.contains( 'mw-plyr-player' ) ) {
            applyFixToPlayer( element );
            return;
        }

        Array.prototype.forEach.call(
            element.querySelectorAll( '.mw-plyr-player' ),
            applyFixToPlayer
        );
    }

    function applyFixToPlayer( player ) {
        var type = player.getAttribute( 'data-plyr-mediahandler' );
        var width = parseInt( player.getAttribute( 'data-plyr-width' ), 10 );
        var height = parseInt( player.getAttribute( 'data-plyr-height' ), 10 );

        if ( !width || width < 1 ) {
            width = type === 'audio' ? 400 : 640;
        }

        if ( type === 'audio' ) {
            player.style.width = width + 'px';
            player.style.maxWidth = '100%';
            player.style.height = '54px';
            player.style.minHeight = '54px';
            player.style.aspectRatio = 'auto';
            return;
        }

        if ( !height || height < 1 ) {
            height = Math.round( width * 9 / 16 );
        }

        player.style.width = width + 'px';
        player.style.maxWidth = '100%';
        player.style.height = 'auto';
        player.style.aspectRatio = width + ' / ' + height;
    }

    function scanVisualEditorSurface() {
        var surface = document.querySelector( '.ve-ui-surface' );

        if ( !surface ) {
            return;
        }

        Array.prototype.forEach.call(
            surface.querySelectorAll( '.mw-plyr-player, figure, .mw-default-size, .mw-halign-none, .mw-halign-right, .mw-halign-left, .mw-halign-center' ),
            fixMediaElement
        );
    }

    function installObserver() {
        var target = document.querySelector( '.ve-ui-surface' );

        if ( !target || target.dataset.plyrMediaHandlerObserved === '1' ) {
            return;
        }

        target.dataset.plyrMediaHandlerObserved = '1';

        var observer = new MutationObserver( function () {
            scanVisualEditorSurface();
        } );

        observer.observe( target, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: [
                'style',
                'class',
                'width',
                'height'
            ]
        } );

        scanVisualEditorSurface();
    }

    mw.hook( 've.activationComplete' ).add( function () {
        setTimeout( installObserver, 250 );
        setTimeout( scanVisualEditorSurface, 500 );
        setTimeout( scanVisualEditorSurface, 1000 );
    } );

    mw.hook( 'wikipage.content' ).add( function ( $content ) {
        $content.find( '.mw-plyr-player' ).each( function () {
            applyFixToPlayer( this );
        } );
    } );
}() );