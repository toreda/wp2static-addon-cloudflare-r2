<?php

namespace WP2StaticCloudflareR2;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\Credentials\Credentials;
use WP2Static\WsLog;

class Deployer {

    const DEFAULT_NAMESPACE = 'wp2static-addon-cloudflare-r2/default';

    // prepare deploy, if modifies URL structure, should be an action
    // $this->prepareDeploy();

    // options - load from addon's static methods

    public function __construct() {}

    public function getR2EndpointUrl(): string {
        $bucket = Controller::getValue('bucket');
        $accountId = Controller::getValue('cfAccountId');

        return 'https://' . $accountId . '.r2.cloudflarestorage.com';
    }

    public function uploadFiles( string $processed_site_path ) : void {
        // check if dir exists
        if ( ! is_dir( $processed_site_path ) ) {
            return;
        }

        $namespace = self::DEFAULT_NAMESPACE;

        $r2EndpointUrl = $this->getR2EndpointUrl();
        // instantiate S3 client
        $s3 = self::s3Client($r2EndpointUrl);

        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processed_site_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        $object_acl = Controller::getValue( 's3ObjectACL' );
        $put_data = [
            'Bucket' => Controller::getValue( 'bucket' ),
            'ACL'    => $object_acl === '' ? 'public-read' : $object_acl,
        ];

        $cache_control = Controller::getValue( 's3CacheControl' );
        if ( $cache_control ) {
            $put_data['CacheControl'] = $cache_control;
        }

        $base_put_data = $put_data;

        $cf_stale_paths = [];

        foreach ( $iterator as $filename => $file_object ) {
            $base_name = basename( $filename );
            if ( $base_name != '.' && $base_name != '..' ) {
                $real_filepath = realpath( $filename );

                // TODO: do filepaths differ when running from WP-CLI (non-chroot)?

                $cache_key = str_replace( $processed_site_path, '', $filename );

                if ( ! $real_filepath ) {
                    $err = 'Trying to deploy unknown file to R2: ' . $filename;
                    \WP2Static\WsLog::l( $err );
                    continue;
                }

                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                if ( ! is_string( $filename ) ) {
                    continue;
                }

                $path_prefix = Controller::getValue( 'pathPrefix' ) ?? '';

                $s3_key =
                    $path_prefix ?
                    $path_prefix . '/' .
                    ltrim( $cache_key, '/' ) :
                    ltrim( $cache_key, '/' );

                $mime_type = MimeTypes::guessMimeType( $filename );
                if ( 'text/' === substr( $mime_type, 0, 5 ) ) {
                    $mime_type = $mime_type . '; charset=UTF-8';
                }

                $put_data['Key'] = $s3_key;
                $put_data['ContentType'] = $mime_type;
                $put_data['ChecksumAlgorithm'] = 'CRC32';
                $put_data_hash = md5( (string) json_encode( $put_data ) );
                $put_data['Body'] = file_get_contents( $filename );
                $body_hash = md5( (string) $put_data['Body'] );
                $hash = md5( $put_data_hash . $body_hash );

                $is_cached = \WP2Static\DeployCache::fileisCached(
                    $cache_key,
                    $namespace,
                    $hash,
                );

                if ( $is_cached ) {
                    continue;
                }

                try {
                    $result = $s3->putObject( $put_data );

                    if ( $result['@metadata']['statusCode'] === 200 ) {
                        \WP2Static\DeployCache::addFile( $cache_key, $namespace, $hash );
                    }
                } catch ( AwsException $e ) {
                    WsLog::l( 'Error uploading file ' . $filename . ': ' . $e->getMessage() . ' with key: ' . $s3_key);
                }
            }
        }

        // Deploy 301 redirects.

        $put_data = $base_put_data;
        $redirects = apply_filters( 'wp2static_list_redirects', [] );


        foreach ( $redirects as $redirect ) {
            $cache_key = $redirect['url'];

            if ( mb_substr( $cache_key, -1 ) === '/' ) {
                $cache_key = $cache_key . 'index.html';
            }
            $path_prefix = Controller::getValue( 'pathPrefix' ) ?? '';

            $s3_key =
                $path_prefix ?
                $path_prefix . '/' .
                ltrim( $cache_key, '/' ) :
                ltrim( $cache_key, '/' );

            $put_data['Key'] = $s3_key;
            $put_data['WebsiteRedirectLocation'] = $redirect['redirect_to'];
            $hash = md5( (string) json_encode( $put_data ) );

            $is_cached = \WP2Static\DeployCache::fileisCached(
                $cache_key,
                $namespace,
                $hash,
            );

            if ( $is_cached ) {
                continue;
            }

            try {
                $result = $s3->putObject( $put_data );

                if ( $result['@metadata']['statusCode'] === 200 ) {
                    \WP2Static\DeployCache::addFile( $cache_key, $namespace, $hash );
                }
            } catch ( AwsException $e ) {
                WsLog::l(
                    'Error uploading redirect ' . $redirect['url'] . ': ' . $e->getMessage()
                );
            }
        }

        $num_stale = count( $cf_stale_paths );

    }

    public static function s3Client($endpoint) : \Aws\S3\S3Client {

        $client_options = [
            'version' => 'latest',
            'driver' => 's3',
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'bucket' => Controller::getValue('bucket'),
            'region' => 'auto',
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            'throw' => true
        ];

        /*
           If no credentials option, SDK attempts to load credentials from
           your environment in the following order:

           - environment variables.
           - a credentials .ini file.
           - an IAM role.
         */
        if (
            Controller::getValue( 'cfAccountId' ) &&
            Controller::getValue( 'cfApiKey' )
        ) {
            $client_options['credentials'] = [
                'key' => Controller::getValue( 'cfAccountId' ),
                'secret' => \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue( 'cfApiKey' )
                ),
            ];
        } else {
            throw new Exception('No credentials');
        }

        return new \Aws\S3\S3Client( $client_options );
    }

}
