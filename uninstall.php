<?php
/**
 * Maintenance Mode Free - Uninstall
 *
 * Cleans up all plugin data when the plugin is uninstalled.
 *
 * @package Complete_Maintenance_Mode
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

// Main options
delete_option( 'mmf_options' );
delete_option( 'mmf_meta' );

// Multisite: delete from all blogs
if ( is_multisite() ) {
    $blog_ids = wp_get_sites();
    if ( is_array( $blog_ids ) ) {
        foreach ( $blog_ids as $blog ) {
            switch_to_blog( $blog['blog_id'] );
            delete_option( 'mmf_options' );
            delete_option( 'mmf_meta' );
            restore_current_blog();
        }
    }
}
