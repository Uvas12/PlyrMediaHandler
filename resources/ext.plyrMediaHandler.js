( function () {
    'use strict';

    var MIN_AUDIO_WIDTH = 400;
    var MIN_VIDEO_WIDTH = 360;

    var AUDIO_EXTENSIONS = [
        'mp3',
        'flac',
        'opus',
        'wav',
        'ogg'
    ];

    var VIDEO_EXTENSIONS = [
        'mp4',
        'mkv'
    ];

    console.log( 'PlyrMediaHandler: modulo JS cargado.' );

    function isInsideVisualEditor( element ) {
        return !!element.closest(
            '.ve-ui-surface, .ve-ce-surface, .ve-ce-documentNode, .ve-init-mw-desktopArticleTarget'
        );
    }

    function isInsideTable( element ) {
        return !!element.closest( 'table' );
    }

    function getAvailableCellWidth( element ) {
        var cell = element.closest( 'td, th' );
        var width;

        if ( !cell ) {
            return null;
        }

        width = cell.clientWidth || cell.offsetWidth || null;

        if ( !width ) {
            return null;
        }

        return Math.max( 90, width - 12 );
    }

    function getControlsForElement( element ) {
        var type = element.getAttribute( 'data-plyr-mediahandler' );
        var compact = isInsideTable( element );

        if ( compact && type === 'audio' ) {
            return [
                'play',
                'progress'
            ];
        }

        if ( compact && type === 'video' ) {
            return [
                'play-large',
                'play',
                'progress',
                'current-time',
                'mute',
                'fullscreen'
            ];
        }

        if ( type === 'audio' ) {
            return [
                'play',
                'progress',
                'current-time',
                'duration',
                'mute',
                'volume',
                'settings'
            ];
        }

        return [
            'play-large',
            'play',
            'progress',
            'current-time',
            'duration',
            'mute',
            'volume',
            'settings',
            'fullscreen'
        ];
    }

    function getSettingsForElement( element ) {
        var type = element.getAttribute( 'data-plyr-mediahandler' );
        var compact = isInsideTable( element );

        if ( compact ) {
            return [];
        }

        if ( type === 'audio' || type === 'video' ) {
            return [
                'speed'
            ];
        }

        return [];
    }

    function applyNativeEditorMode( element ) {
        var wrapper = element.closest( '.mw-plyr-wrapper' );
        var type = element.getAttribute( 'data-plyr-mediahandler' );
        var width = parseInt( element.getAttribute( 'data-plyr-width' ), 10 );
        var height = parseInt( element.getAttribute( 'data-plyr-height' ), 10 );

        if ( !wrapper ) {
            return;
        }

        if ( !width || width < 1 ) {
            width = type === 'audio' ? MIN_AUDIO_WIDTH : 640;
        }

        if ( type === 'audio' ) {
            width = Math.max( MIN_AUDIO_WIDTH, width );

            wrapper.style.width = width + 'px';
            wrapper.style.minWidth = MIN_AUDIO_WIDTH + 'px';
            wrapper.style.maxWidth = '100%';
            wrapper.style.overflow = 'hidden';
            wrapper.style.marginLeft = '0';
            wrapper.style.marginRight = '0';

            element.style.width = '100%';
            element.style.minWidth = MIN_AUDIO_WIDTH + 'px';
            element.style.maxWidth = '100%';
            element.style.height = '42px';
            element.style.minHeight = '42px';
            element.style.display = 'block';
            element.controls = true;

            wrapper.classList.add( 'mw-plyr-ve-native' );
            return;
        }

        if ( type === 'video' ) {
            width = Math.max( MIN_VIDEO_WIDTH, width );

            if ( !height || height < 1 ) {
                height = Math.round( width * 9 / 16 );
            }

            wrapper.style.width = width + 'px';
            wrapper.style.minWidth = MIN_VIDEO_WIDTH + 'px';
            wrapper.style.maxWidth = '100%';
            wrapper.style.overflow = 'hidden';
            wrapper.style.marginLeft = '0';
            wrapper.style.marginRight = '0';
            wrapper.style.background = '#000';

            element.style.width = '100%';
            element.style.minWidth = MIN_VIDEO_WIDTH + 'px';
            element.style.maxWidth = '100%';
            element.style.aspectRatio = width + ' / ' + height;
            element.style.display = 'block';
            element.controls = true;

            wrapper.classList.add( 'mw-plyr-ve-native' );
        }
    }

    function destroyPlyrIfNeeded( element ) {
        if ( element._plyrMediaHandlerInstance ) {
            try {
                element._plyrMediaHandlerInstance.destroy();
            } catch ( e ) {
                console.warn( 'PlyrMediaHandler: no se pudo destruir Plyr en VisualEditor.', e );
            }

            element._plyrMediaHandlerInstance = null;
            element.dataset.plyrReady = '0';
        }

        if ( element.plyr && typeof element.plyr.destroy === 'function' ) {
            try {
                element.plyr.destroy();
            } catch ( e2 ) {
                console.warn( 'PlyrMediaHandler: no se pudo destruir element.plyr.', e2 );
            }

            element.dataset.plyrReady = '0';
        }
    }

    function syncVideoIntrinsicRatio( element ) {
        var wrapper = element.closest( '.mw-plyr-wrapper' );
        var plyrContainer;
        var videoWidth;
        var videoHeight;

        if ( element.getAttribute( 'data-plyr-mediahandler' ) !== 'video' ) {
            return;
        }

        if ( !element.videoWidth || !element.videoHeight ) {
            return;
        }

        videoWidth = element.videoWidth;
        videoHeight = element.videoHeight;

        if ( videoWidth < 1 || videoHeight < 1 ) {
            return;
        }

        element.setAttribute( 'data-plyr-intrinsic-width', String( videoWidth ) );
        element.setAttribute( 'data-plyr-intrinsic-height', String( videoHeight ) );

        element.style.aspectRatio = videoWidth + ' / ' + videoHeight;
        element.style.objectFit = 'contain';
        element.style.objectPosition = 'center center';

        if ( wrapper ) {
            plyrContainer = wrapper.querySelector( '.plyr' );

            if ( plyrContainer ) {
                plyrContainer.style.aspectRatio = videoWidth + ' / ' + videoHeight;
            }
        }
    }

    function applyWrapperSize( element ) {
        var wrapper = element.closest( '.mw-plyr-wrapper' );
        var originalWidth = parseInt( element.getAttribute( 'data-plyr-width' ), 10 );
        var height = parseInt( element.getAttribute( 'data-plyr-height' ), 10 );
        var type = element.getAttribute( 'data-plyr-mediahandler' );
        var tableMode = isInsideTable( element );
        var availableCellWidth = getAvailableCellWidth( element );
        var width = originalWidth;
        var plyrContainer;
        var computedHeight;
        var intrinsicWidth;
        var intrinsicHeight;

        if ( !wrapper ) {
            return;
        }

        if ( isInsideVisualEditor( element ) ) {
            applyNativeEditorMode( element );
            return;
        }

        if ( !width || width < 1 ) {
            width = type === 'audio' ? MIN_AUDIO_WIDTH : 640;
        }

        if ( type === 'audio' ) {
            width = Math.max( MIN_AUDIO_WIDTH, width );
        }

        if ( type === 'video' ) {
            width = Math.max( MIN_VIDEO_WIDTH, width );
        }

        if ( tableMode ) {
            if ( type === 'video' ) {
                width = Math.min( width, 260 );
            }

            if ( type === 'audio' ) {
                width = Math.min( width, 230 );
            }

            if ( availableCellWidth ) {
                width = Math.min( width, availableCellWidth );
            }

            if ( type === 'audio' ) {
                width = Math.max( 190, width );
            } else {
                width = Math.max( 90, width );
            }
        }

        wrapper.style.width = width + 'px';
        wrapper.style.maxWidth = '100%';
        wrapper.style.display = 'block';

        if ( tableMode ) {
            wrapper.style.overflow = 'hidden';
            wrapper.style.marginLeft = 'auto';
            wrapper.style.marginRight = 'auto';
            wrapper.style.textAlign = 'center';
        } else {
            wrapper.style.overflow = 'visible';
            wrapper.style.marginLeft = '0';
            wrapper.style.marginRight = '0';
            wrapper.style.textAlign = 'left';
        }

        plyrContainer = wrapper.querySelector( '.plyr' );

        if ( plyrContainer ) {
            plyrContainer.style.width = '100%';
            plyrContainer.style.maxWidth = '100%';

            if ( tableMode ) {
                plyrContainer.style.overflow = 'hidden';
                plyrContainer.style.marginLeft = 'auto';
                plyrContainer.style.marginRight = 'auto';
            } else {
                plyrContainer.style.overflow = 'visible';
                plyrContainer.style.marginLeft = '0';
                plyrContainer.style.marginRight = '0';
            }
        }

        if ( type === 'video' ) {
            intrinsicWidth = parseInt( element.getAttribute( 'data-plyr-intrinsic-width' ), 10 );
            intrinsicHeight = parseInt( element.getAttribute( 'data-plyr-intrinsic-height' ), 10 );

            if ( intrinsicWidth && intrinsicHeight ) {
                computedHeight = Math.round( width * intrinsicHeight / intrinsicWidth );
            } else {
                if ( !height || height < 1 ) {
                    height = Math.round( width * 9 / 16 );
                }

                if ( originalWidth && originalWidth > 0 ) {
                    computedHeight = Math.round( width * height / originalWidth );
                } else {
                    computedHeight = Math.round( width * 9 / 16 );
                }
            }

            if ( !computedHeight || computedHeight < 1 ) {
                computedHeight = Math.round( width * 9 / 16 );
            }

            element.style.width = '100%';
            element.style.maxWidth = '100%';
            element.style.height = 'auto';
            element.style.objectFit = 'contain';
            element.style.objectPosition = 'center center';
            element.style.aspectRatio = width + ' / ' + computedHeight;

            if ( plyrContainer ) {
                plyrContainer.style.aspectRatio = width + ' / ' + computedHeight;
            }
        }

        if ( type === 'audio' ) {
            element.style.width = '100%';
            element.style.maxWidth = '100%';

            if ( plyrContainer ) {
                plyrContainer.style.width = '100%';
                plyrContainer.style.maxWidth = '100%';
            }
        }
    }

    function getMediaSourceUrl( element ) {
        var source = element.querySelector( 'source' );

        if ( source && source.getAttribute( 'src' ) ) {
            return source.getAttribute( 'src' );
        }

        return element.currentSrc || element.src || '';
    }

    function getPosterStorageKey( element ) {
        var src = getMediaSourceUrl( element );

        if ( !src ) {
            return null;
        }

        return 'PlyrMediaHandlerPoster:' + src;
    }

    function loadStoredPoster( element ) {
        var key;
        var poster;

        if ( element.getAttribute( 'data-plyr-mediahandler' ) !== 'video' ) {
            return false;
        }

        if ( element.getAttribute( 'poster' ) ) {
            return true;
        }

        key = getPosterStorageKey( element );

        if ( !key ) {
            return false;
        }

        try {
            poster = window.localStorage.getItem( key );
        } catch ( e ) {
            return false;
        }

        if ( poster ) {
            element.setAttribute( 'poster', poster );
            element.dataset.plyrPosterStatus = 'done';
            return true;
        }

        return false;
    }

    function saveStoredPoster( element, poster ) {
        var key = getPosterStorageKey( element );

        if ( !key || !poster ) {
            return;
        }

        try {
            window.localStorage.setItem( key, poster );
        } catch ( e ) {}
    }

    function canGeneratePosterForVideo( element ) {
        var type = element.getAttribute( 'data-plyr-mediahandler' );

        if ( type !== 'video' ) {
            return false;
        }

        if ( element.getAttribute( 'poster' ) ) {
            return false;
        }

        if ( element.dataset.plyrPosterStatus === 'generating' ) {
            return false;
        }

        if ( element.dataset.plyrPosterStatus === 'done' ) {
            return false;
        }

        if ( isInsideVisualEditor( element ) ) {
            return false;
        }

        return true;
    }

    function updatePlyrPosterVisibility( element ) {
        var wrapper = element.closest( '.mw-plyr-wrapper' );
        var plyrPoster;

        if ( !wrapper ) {
            return;
        }

        plyrPoster = wrapper.querySelector( '.plyr__poster' );

        if ( !plyrPoster ) {
            return;
        }

        if ( !element.paused && !element.ended ) {
            plyrPoster.style.opacity = '0';
            plyrPoster.style.pointerEvents = 'none';
            return;
        }

        if ( element.currentTime && element.currentTime > 0.1 ) {
            plyrPoster.style.opacity = '0';
            plyrPoster.style.pointerEvents = 'none';
            return;
        }

        plyrPoster.style.opacity = '1';
        plyrPoster.style.pointerEvents = '';
    }

    function installPosterVisibilityHandlers( element ) {
        if ( element.dataset.plyrPosterVisibilityHandlers === '1' ) {
            return;
        }

        element.dataset.plyrPosterVisibilityHandlers = '1';

        element.addEventListener( 'play', function () {
            updatePlyrPosterVisibility( element );
        } );

        element.addEventListener( 'playing', function () {
            updatePlyrPosterVisibility( element );
        } );

        element.addEventListener( 'timeupdate', function () {
            updatePlyrPosterVisibility( element );
        } );

        element.addEventListener( 'pause', function () {
            updatePlyrPosterVisibility( element );
        } );

        element.addEventListener( 'ended', function () {
            updatePlyrPosterVisibility( element );
        } );

        element.addEventListener( 'seeked', function () {
            updatePlyrPosterVisibility( element );
        } );
    }

    function applyPosterToPlyrInterface( element, poster ) {
        var wrapper = element.closest( '.mw-plyr-wrapper' );
        var plyrPoster;

        if ( !wrapper || !poster ) {
            return;
        }

        plyrPoster = wrapper.querySelector( '.plyr__poster' );

        if ( plyrPoster ) {
            plyrPoster.style.backgroundImage = 'url("' + poster + '")';
            plyrPoster.style.backgroundSize = 'cover';
            plyrPoster.style.backgroundPosition = 'center center';
            updatePlyrPosterVisibility( element );
        }
    }

    function generatePosterFromVideo( element ) {
        return new Promise( function ( resolve ) {
            var canvas;
            var context;
            var timeoutId;
            var originalMuted = element.muted;
            var originalCurrentTime = 0;
            var wrapper = element.closest( '.mw-plyr-wrapper' );
            var maxPosterWidth = 960;
            var posterWidth;
            var posterHeight;
            var poster;
            var duration;

            if ( loadStoredPoster( element ) ) {
                applyPosterToPlyrInterface( element, element.getAttribute( 'poster' ) );
                resolve();
                return;
            }

            if ( !canGeneratePosterForVideo( element ) ) {
                resolve();
                return;
            }

            element.dataset.plyrPosterStatus = 'generating';
            element.preload = 'auto';
            element.muted = true;

            if ( wrapper ) {
                wrapper.classList.add( 'mw-plyr-poster-loading' );
            }

            try {
                originalCurrentTime = element.currentTime || 0;
            } catch ( e ) {
                originalCurrentTime = 0;
            }

            function cleanup() {
                clearTimeout( timeoutId );
                element.removeEventListener( 'loadedmetadata', onLoadedMetadata );
                element.removeEventListener( 'seeked', onSeeked );
                element.removeEventListener( 'error', onError );
                element.muted = originalMuted;

                if ( wrapper ) {
                    wrapper.classList.remove( 'mw-plyr-poster-loading' );
                }
            }

            function finishSuccess() {
                element.dataset.plyrPosterStatus = 'done';
                cleanup();
                resolve();
            }

            function finishFail() {
                element.dataset.plyrPosterStatus = 'failed';
                cleanup();
                resolve();
            }

            function onError() {
                finishFail();
            }

            function onLoadedMetadata() {
                duration = element.duration;

                syncVideoIntrinsicRatio( element );

                try {
                    if ( duration && isFinite( duration ) && duration > 3 ) {
                        element.currentTime = Math.min( 1.2, duration * 0.08 );
                    } else if ( duration && isFinite( duration ) && duration > 1 ) {
                        element.currentTime = 0.5;
                    } else {
                        element.currentTime = 0.1;
                    }
                } catch ( e ) {
                    captureFrame();
                }
            }

            function onSeeked() {
                setTimeout( captureFrame, 60 );
            }

            function captureFrame() {
                try {
                    if ( !element.videoWidth || !element.videoHeight ) {
                        finishFail();
                        return;
                    }

                    posterWidth = element.videoWidth;
                    posterHeight = element.videoHeight;

                    if ( posterWidth > maxPosterWidth ) {
                        posterHeight = Math.round( posterHeight * maxPosterWidth / posterWidth );
                        posterWidth = maxPosterWidth;
                    }

                    canvas = document.createElement( 'canvas' );
                    canvas.width = posterWidth;
                    canvas.height = posterHeight;

                    context = canvas.getContext( '2d' );

                    if ( !context ) {
                        finishFail();
                        return;
                    }

                    context.drawImage( element, 0, 0, posterWidth, posterHeight );

                    poster = canvas.toDataURL( 'image/jpeg', 0.82 );

                    if ( !poster || poster.length < 100 ) {
                        finishFail();
                        return;
                    }

                    element.setAttribute( 'poster', poster );
                    saveStoredPoster( element, poster );
                    applyPosterToPlyrInterface( element, poster );

                    try {
                        element.currentTime = originalCurrentTime;
                    } catch ( e2 ) {}

                    finishSuccess();
                } catch ( e ) {
                    finishFail();
                }
            }

            timeoutId = setTimeout( function () {
                if ( !element.getAttribute( 'poster' ) && element.videoWidth && element.videoHeight ) {
                    captureFrame();
                    return;
                }

                if ( element.getAttribute( 'poster' ) ) {
                    finishSuccess();
                    return;
                }

                finishFail();
            }, 8000 );

            element.addEventListener( 'loadedmetadata', onLoadedMetadata );
            element.addEventListener( 'seeked', onSeeked );
            element.addEventListener( 'error', onError );

            try {
                element.load();
            } catch ( e ) {
                finishFail();
                return;
            }

            if ( element.readyState >= 1 ) {
                onLoadedMetadata();
            }
        } );
    }

    function initPlyrInstance( element ) {
        generatePosterFromVideo( element ).then( function () {
            try {
                element._plyrMediaHandlerInstance = new window.Plyr( element, {
                    controls: getControlsForElement( element ),
                    settings: getSettingsForElement( element ),
                    hideControls: true,
                    speed: {
                        selected: 1,
                        options: [
                            0.5,
                            0.75,
                            1,
                            1.25,
                            1.5,
                            2
                        ]
                    }
                } );

                installPosterVisibilityHandlers( element );
                syncVideoIntrinsicRatio( element );
                applyPosterToPlyrInterface( element, element.getAttribute( 'poster' ) );
                applyWrapperSize( element );

                setTimeout( function () {
                    installPosterVisibilityHandlers( element );
                    syncVideoIntrinsicRatio( element );
                    applyPosterToPlyrInterface( element, element.getAttribute( 'poster' ) );
                    applyWrapperSize( element );
                }, 100 );

                setTimeout( function () {
                    syncVideoIntrinsicRatio( element );
                    applyPosterToPlyrInterface( element, element.getAttribute( 'poster' ) );
                    applyWrapperSize( element );
                }, 500 );

                setTimeout( function () {
                    syncVideoIntrinsicRatio( element );
                    applyPosterToPlyrInterface( element, element.getAttribute( 'poster' ) );
                    applyWrapperSize( element );
                }, 1000 );

                console.log( 'PlyrMediaHandler: Plyr inicializado correctamente.', element );
            } catch ( e ) {
                element.classList.add( 'mw-plyr-native-fallback' );
                applyWrapperSize( element );
                console.warn( 'PlyrMediaHandler: error inicializando Plyr.', e );
            }
        } );
    }

    function initPlyrPlayers( $content ) {
        var players = $content.find( '.mw-plyr-player' );

        console.log( 'PlyrMediaHandler: reproductores encontrados:', players.length );
        console.log( 'PlyrMediaHandler: typeof window.Plyr =', typeof window.Plyr );

        if ( !players.length ) {
            return;
        }

        players.each( function () {
            var element = this;

            if ( element.getAttribute( 'data-plyr-mediahandler' ) === 'video' ) {
                element.addEventListener( 'loadedmetadata', function () {
                    syncVideoIntrinsicRatio( element );
                    applyWrapperSize( element );
                } );
            }

            loadStoredPoster( element );

            if ( isInsideVisualEditor( element ) ) {
                destroyPlyrIfNeeded( element );
                applyNativeEditorMode( element );
                return;
            }

            if ( typeof window.Plyr === 'undefined' ) {
                console.warn(
                    'PlyrMediaHandler: Plyr no esta cargado. Revisa resources/lib/plyr/plyr.min.js y extension.json.'
                );

                element.classList.add( 'mw-plyr-native-fallback' );
                applyWrapperSize( element );
                return;
            }

            if ( element.dataset.plyrReady === '1' ) {
                applyWrapperSize( element );
                return;
            }

            element.dataset.plyrReady = '1';

            initPlyrInstance( element );
        } );
    }

    function getFileExtensionFromTitle( title ) {
        var cleanTitle = title.split( '#' )[ 0 ].split( '?' )[ 0 ];
        var parts = cleanTitle.split( '.' );

        if ( parts.length < 2 ) {
            return '';
        }

        return parts.pop().toLowerCase();
    }

    function getMinimumForFileTitle( fileTitle ) {
        var extension = getFileExtensionFromTitle( fileTitle );

        if ( AUDIO_EXTENSIONS.indexOf( extension ) !== -1 ) {
            return MIN_AUDIO_WIDTH;
        }

        if ( VIDEO_EXTENSIONS.indexOf( extension ) !== -1 ) {
            return MIN_VIDEO_WIDTH;
        }

        return null;
    }

    function normalizeFileLinkSize( linkText ) {
        var inner = linkText.slice( 2, -2 );
        var parts = inner.split( '|' );
        var fileTitle;
        var minWidth;
        var changed = false;
        var hasSize = false;
        var i;
        var option;
        var singleMatch;
        var doubleMatch;
        var width;
        var width2;

        if ( parts.length < 1 ) {
            return linkText;
        }

        fileTitle = parts[ 0 ].trim();

        if ( !/^(file|archivo|image|imagen):/i.test( fileTitle ) ) {
            return linkText;
        }

        minWidth = getMinimumForFileTitle( fileTitle );

        if ( !minWidth ) {
            return linkText;
        }

        for ( i = 1; i < parts.length; i++ ) {
            option = parts[ i ].trim();

            singleMatch = option.match( /^(\d+)px$/i );
            doubleMatch = option.match( /^(\d+)x(\d+)px$/i );

            if ( singleMatch ) {
                hasSize = true;
                width = parseInt( singleMatch[ 1 ], 10 );

                if ( width < minWidth ) {
                    parts[ i ] = option.replace( /^(\d+)px$/i, minWidth + 'px' );
                    changed = true;
                }
            } else if ( doubleMatch ) {
                hasSize = true;
                width2 = parseInt( doubleMatch[ 1 ], 10 );

                if ( width2 < minWidth ) {
                    parts[ i ] = option.replace( /^(\d+)x(\d+)px$/i, minWidth + 'px' );
                    changed = true;
                }
            }
        }

        if ( !hasSize || !changed ) {
            return linkText;
        }

        return '[[' + parts.join( '|' ) + ']]';
    }

    function normalizeWikitextMediaSizes( text ) {
        return text.replace(
            /\[\[(?:File|Archivo|Image|Imagen):[^\[\]\n]+?\]\]/gi,
            function ( match ) {
                return normalizeFileLinkSize( match );
            }
        );
    }

    function installWikiEditorSubmitNormalizer() {
        var textarea = document.getElementById( 'wpTextbox1' );
        var form;
        var saveButton;

        if ( !textarea ) {
            return;
        }

        form = textarea.closest( 'form' );

        if ( !form || form.dataset.plyrMediaHandlerNormalizer === '1' ) {
            return;
        }

        form.dataset.plyrMediaHandlerNormalizer = '1';

        form.addEventListener( 'submit', function () {
            textarea.value = normalizeWikitextMediaSizes( textarea.value );
        } );

        saveButton = document.getElementById( 'wpSave' );

        if ( saveButton ) {
            saveButton.addEventListener( 'click', function () {
                textarea.value = normalizeWikitextMediaSizes( textarea.value );
            } );
        }
    }

    mw.hook( 'wikipage.content' ).add( function ( $content ) {
        initPlyrPlayers( $content );
    } );

    mw.hook( 've.activationComplete' ).add( function () {
        setTimeout( function () {
            $( '.ve-ui-surface .mw-plyr-player, .ve-ce-surface .mw-plyr-player' ).each( function () {
                destroyPlyrIfNeeded( this );
                applyNativeEditorMode( this );
            } );
        }, 100 );

        setTimeout( function () {
            $( '.ve-ui-surface .mw-plyr-player, .ve-ce-surface .mw-plyr-player' ).each( function () {
                destroyPlyrIfNeeded( this );
                applyNativeEditorMode( this );
            } );
        }, 500 );

        setTimeout( function () {
            $( '.ve-ui-surface .mw-plyr-player, .ve-ce-surface .mw-plyr-player' ).each( function () {
                destroyPlyrIfNeeded( this );
                applyNativeEditorMode( this );
            } );
        }, 1000 );
    } );

    $( function () {
        initPlyrPlayers( $( document.body ) );
        installWikiEditorSubmitNormalizer();
    } );

    $( window ).on( 'resize', function () {
        $( '.mw-plyr-player' ).each( function () {
            if ( isInsideVisualEditor( this ) ) {
                destroyPlyrIfNeeded( this );
                applyNativeEditorMode( this );
            } else {
                applyWrapperSize( this );
            }
        } );
    } );
}() );