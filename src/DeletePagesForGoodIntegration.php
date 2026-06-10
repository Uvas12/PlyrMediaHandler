<?php

namespace MediaWiki\Extension\PlyrMediaHandler;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDatabase;

class DeletePagesForGoodIntegration {

    /**
     * Hook personalizado lanzado por DeletePagesForGood antes de borrar
     * permanentemente una página del namespace Archivo.
     *
     * @param Title $title
     * @param mixed $user
     * @return void
     */
    public static function onBeforePermanentFileDelete( $title, $user = null ): void {
        if ( !$title instanceof Title ) {
            return;
        }

        if ( $title->getNamespace() !== NS_FILE ) {
            return;
        }

        self::deleteFileDataCompletely( $title );
    }

    /**
     * Elimina archivo actual, versiones anteriores, thumbnails y registros.
     *
     * @param Title $title
     * @return void
     */
    private static function deleteFileDataCompletely( Title $title ): void {
        $services = MediaWikiServices::getInstance();
        $repoGroup = $services->getRepoGroup();
        $localRepo = $repoGroup->getLocalRepo();

        $file = $repoGroup->findFile( $title );
        $fileName = $title->getDBkey();

        $paths = [];

        if ( $file ) {
            $paths = array_merge( $paths, self::collectCurrentFilePaths( $file ) );
            $paths = array_merge( $paths, self::collectThumbPaths( $file ) );
        }

        $paths = array_merge(
            $paths,
            self::collectArchivedPathsFromDatabase( $fileName, $localRepo )
        );

        self::deletePhysicalPaths( $paths );
        self::deleteDatabaseRows( $fileName );

        $services->getLinkCache()->clear();
    }

    /**
     * @param mixed $file
     * @return array
     */
    private static function collectCurrentFilePaths( $file ): array {
        $paths = [];

        if ( method_exists( $file, 'getPath' ) ) {
            $path = $file->getPath();

            if ( $path ) {
                $paths[] = $path;
            }
        }

        if ( method_exists( $file, 'getHistory' ) ) {
            try {
                $history = $file->getHistory();

                if ( is_array( $history ) ) {
                    foreach ( $history as $oldFile ) {
                        if ( is_object( $oldFile ) && method_exists( $oldFile, 'getPath' ) ) {
                            $oldPath = $oldFile->getPath();

                            if ( $oldPath ) {
                                $paths[] = $oldPath;
                            }
                        }
                    }
                }
            } catch ( \Throwable $e ) {
                wfDebugLog(
                    'PlyrMediaHandler',
                    'No se pudo leer historial físico del archivo: ' . $e->getMessage()
                );
            }
        }

        return $paths;
    }

    /**
     * @param mixed $file
     * @return array
     */
    private static function collectThumbPaths( $file ): array {
        $paths = [];

        if ( method_exists( $file, 'getThumbPath' ) ) {
            $thumbPath = $file->getThumbPath();

            if ( $thumbPath ) {
                $paths[] = $thumbPath;
            }
        }

        return $paths;
    }

    /**
     * Intenta recolectar rutas físicas desde oldimage y filearchive.
     *
     * @param string $fileName
     * @param mixed $localRepo
     * @return array
     */
    private static function collectArchivedPathsFromDatabase( string $fileName, $localRepo ): array {
        $paths = [];

        $services = MediaWikiServices::getInstance();
        $dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );

        if ( !method_exists( $localRepo, 'getZonePath' ) || !method_exists( $localRepo, 'getHashPath' ) ) {
            return $paths;
        }

        $publicPath = rtrim( $localRepo->getZonePath( 'public' ), '/' );
        $deletedPath = rtrim( $localRepo->getZonePath( 'deleted' ), '/' );
        $hashPath = $localRepo->getHashPath( $fileName );

        if ( $dbr->tableExists( 'oldimage', __METHOD__ ) ) {
            $oldRows = $dbr->newSelectQueryBuilder()
                ->select( [ 'oi_archive_name' ] )
                ->from( 'oldimage' )
                ->where( [ 'oi_name' => $fileName ] )
                ->caller( __METHOD__ )
                ->fetchResultSet();

            foreach ( $oldRows as $row ) {
                if ( !empty( $row->oi_archive_name ) ) {
                    $paths[] = $publicPath . '/archive/' . $hashPath . $row->oi_archive_name;
                }
            }
        }

        if ( $dbr->tableExists( 'filearchive', __METHOD__ ) ) {
            $fileArchiveRows = $dbr->newSelectQueryBuilder()
                ->select( [ 'fa_storage_key', 'fa_archive_name' ] )
                ->from( 'filearchive' )
                ->where( [ 'fa_name' => $fileName ] )
                ->caller( __METHOD__ )
                ->fetchResultSet();

            foreach ( $fileArchiveRows as $row ) {
                if ( !empty( $row->fa_storage_key ) ) {
                    $paths[] = $deletedPath . '/' . $row->fa_storage_key;
                }

                if ( !empty( $row->fa_archive_name ) ) {
                    $paths[] = $publicPath . '/archive/' . $hashPath . $row->fa_archive_name;
                }
            }
        }

        return $paths;
    }

    /**
     * @param array $paths
     * @return void
     */
    private static function deletePhysicalPaths( array $paths ): void {
        $paths = array_unique( array_filter( $paths ) );

        foreach ( $paths as $path ) {
            if ( !is_string( $path ) || $path === '' ) {
                continue;
            }

            if ( is_dir( $path ) ) {
                self::deleteDirectoryRecursive( $path );
                continue;
            }

            if ( is_file( $path ) ) {
                @unlink( $path );
            }
        }
    }

    /**
     * @param string $directory
     * @return void
     */
    private static function deleteDirectoryRecursive( string $directory ): void {
        if ( !is_dir( $directory ) ) {
            return;
        }

        $items = scandir( $directory );

        if ( !is_array( $items ) ) {
            return;
        }

        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if ( is_dir( $path ) ) {
                self::deleteDirectoryRecursive( $path );
            } elseif ( is_file( $path ) ) {
                @unlink( $path );
            }
        }

        @rmdir( $directory );
    }

    /**
     * @param string $fileName
     * @return void
     */
    private static function deleteDatabaseRows( string $fileName ): void {
        $services = MediaWikiServices::getInstance();
        $dbw = $services->getDBLoadBalancer()->getConnection( DB_PRIMARY );

        self::deleteRowsIfTableExists(
            $dbw,
            'image',
            [ 'img_name' => $fileName ]
        );

        self::deleteRowsIfTableExists(
            $dbw,
            'oldimage',
            [ 'oi_name' => $fileName ]
        );

        self::deleteRowsIfTableExists(
            $dbw,
            'filearchive',
            [ 'fa_name' => $fileName ]
        );
    }

    /**
     * @param IDatabase $dbw
     * @param string $table
     * @param array $where
     * @return void
     */
    private static function deleteRowsIfTableExists( IDatabase $dbw, string $table, array $where ): void {
        if ( !$dbw->tableExists( $table, __METHOD__ ) ) {
            return;
        }

        $dbw->newDeleteQueryBuilder()
            ->deleteFrom( $table )
            ->where( $where )
            ->caller( __METHOD__ )
            ->execute();
    }
}