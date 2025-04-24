<?php
/**
 * NOTE: Deployment work based on the `WP2StaticS3` addon by Leon Stafford.
 */

namespace WP2StaticCloudflareR2;

class Controller {
    public function run() : void {
        add_filter(
            'wp2static_add_menu_items',
            [ 'WP2StaticCloudflareR2\Controller', 'addSubmenuPage' ]
        );

        add_action(
            'admin_post_wp2static_r2_save_options',
            [ $this, 'saveOptionsFromUI' ],
            15,
            1
        );

        add_action(
            'wp2static_deploy',
            [ $this, 'deploy' ],
            15,
            2
        );

        add_action(
            'admin_menu',
            [ $this, 'addOptionsPage' ],
            15,
            1
        );

        do_action(
            'wp2static_register_addon',
            'wp2static-addon-cloudflare-r2',
            'deploy',
            'R2 Deployment',
            'https://wp2static.com/addons/r2/',
            'Deploys to Cloudflare R2'
        );

        if ( defined( 'WP_CLI' ) ) {
            \WP_CLI::add_command(
                'wp2static r2',
                [ CLI::class, 'r2' ]
            );
        }
    }

    /**
     *  Get all add-on options
     *
     *  @return mixed[] All options
     */
    public static function getOptions() : array {
        global $wpdb;
        $options = [];

        $table_name = $wpdb->prefix . 'wp2static_addon_r2_options';

        $rows = $wpdb->get_results( "SELECT * FROM $table_name" );

        foreach ( $rows as $row ) {
            $options[ $row->name ] = $row;
        }

        return $options;
    }

    /**
     * Seed options
     */
    public static function seedOptions() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_r2_options';

        $query_string =
            "INSERT IGNORE INTO $table_name (name, value, label, description) " .
            'VALUES (%s, %s, %s, %s);';


        $query = $wpdb->prepare(
            $query_string,
            'bucket',
            '',
            'R2 Bucket name',
            ''
        );

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            'cfAccountId',
            '',
            'Cloudflare Account ID',
            ''
        );

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            'pathPrefix',
            '',
            'Path Prefix in Bucket',
            ''
        );

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            'endpoint',
            '',
            'R2 Endpoint URL',
            ''
        );

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            'cfApiKey',
            '',
            'Cloudflare API Key',
            ''
        );

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            'region',
            'auto',
            'Region',
            ''
        );

        $wpdb->query( $query );

    }

    /**
     * Save options
     *
     * @param mixed $value option value to save
     */
    public static function saveOption( string $name, $value ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_r2_options';

        $wpdb->update(
            $table_name,
            [ 'value' => $value ],
            [ 'name' => $name ]
        );
    }

    public static function renderR2Page() : void {
        self::createOptionsTable();
        self::seedOptions();

        $view = [];
        $view['nonce_action'] = 'wp2static-r2-options';
        $view['uploads_path'] = \WP2Static\SiteInfo::getPath( 'uploads' );
        $s3_path = \WP2Static\SiteInfo::getPath( 'uploads' ) . 'wp2static-processed-site.r2';

        $view['options'] = self::getOptions();

        $view['r2_url'] =
            is_file( $r2_path ) ?
                \WP2Static\SiteInfo::getUrl( 'uploads' ) . 'wp2static-processed-site.r2' : '#';

        require_once __DIR__ . '/../views/r2-page.php';
    }


    public function deploy( string $processed_site_path, string $enabled_deployer ) : void {
        if ( $enabled_deployer !== 'wp2static-addon-cloudflare-r2' ) {
            return;
        }

        \WP2Static\WsLog::l( 'Cloudflare R2 Addon deploying' );

        $s3_deployer = new Deployer();
        $s3_deployer->uploadFiles( $processed_site_path );
    }

    public static function createOptionsTable() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_r2_options';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            value VARCHAR(255) NOT NULL,
            label VARCHAR(255) NULL,
            description VARCHAR(255) NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // dbDelta doesn't handle unique indexes well.
        $indexes = $wpdb->query( "SHOW INDEX FROM $table_name WHERE key_name = 'name'" );
        if ( 0 === $indexes ) {
            $result = $wpdb->query( "CREATE UNIQUE INDEX name ON $table_name (name)" );
            if ( false === $result ) {
                \WP2Static\WsLog::l( "Failed to create 'name' index on $table_name." );
            }
        }
    }

    public static function activateForSingleSite(): void {
        self::createOptionsTable();
        self::seedOptions();
    }

    public static function deactivateForSingleSite() : void {
    }

    public static function deactivate( bool $network_wide = null ) : void {
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::deactivateForSingleSite();
            }

            restore_current_blog();
        } else {
            self::deactivateForSingleSite();
        }
    }

    public static function activate( bool $network_wide = null ) : void {
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::activateForSingleSite();
            }

            restore_current_blog();
        } else {
            self::activateForSingleSite();
        }
    }

    /**
     * Add WP2Static submenu
     *
     * @param mixed[] $submenu_pages array of submenu pages
     * @return mixed[] array of submenu pages
     */
    public static function addSubmenuPage( array $submenu_pages ) : array {
        $submenu_pages['r2'] = [ 'WP2StaticCloudflareR2\Controller', 'renderR2Page' ];

        return $submenu_pages;
    }

    public static function saveOptionsFromUI() : void {
        check_admin_referer( 'wp2static-r2-options' );

        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_r2_options';

        $wpdb->update(
            $table_name,
            [ 'value' => sanitize_text_field( $_POST['endpoint'] ) ],
            [ 'name' => 'endpoint' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => sanitize_text_field( $_POST['pathPrefix'] ) ],
            [ 'name' => 'pathPrefix' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => sanitize_text_field( $_POST['bucket'] ) ],
            [ 'name' => 'bucket' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => sanitize_text_field( $_POST['accountId'] ) ],
            [ 'name' => 'accountId' ]
        );

        $secret_access_key =
            $_POST['apiKey'] ?
            \WP2Static\CoreOptions::encrypt_decrypt(
                'encrypt',
                sanitize_text_field( $_POST['apiKey'] )
            ) : '';


        $wpdb->update(
            $table_name,
            [ 'value' => $secret_access_key ],
            [ 'name' => 'apiKey' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => sanitize_text_field( $_POST['region'] ) ],
            [ 'name' => 'region' ]
        );


        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-addon-cloudflare-r2' ) );
        exit;
    }

    /**
     * Get option value
     *
     * @return string option value
     */
    public static function getValue( string $name ) : string {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_r2_options';

        $sql = $wpdb->prepare(
            "SELECT value FROM $table_name WHERE" . ' name = %s LIMIT 1',
            $name
        );

        $option_value = $wpdb->get_var( $sql );

        if ( ! is_string( $option_value ) ) {
            return '';
        }

        return $option_value;
    }

    public function addOptionsPage() : void {
        add_submenu_page(
            '',
            'R2 Deployment Options',
            'R2 Deployment Options',
            'manage_options',
            'wp2static-addon-cloudflare-r2',
            [ $this, 'renderR2Page' ]
        );
    }
}

