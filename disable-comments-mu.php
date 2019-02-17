<?php
/**
 * Plugin Name: Disable Comments (Must Use version)
 * Plugin URI: https://github.com/devgeniem/disable-comments-mu
 * Description: Disables all WordPress comment functionality on the entire network.
 * Version: 1.2.1
 * Author: Jaakko Lehtonen
 * Author URI: https://www.geniem.fi/
 * License: GPL-2.0
 * GitHub Plugin URI: https://github.com/devgeniem/disable-comments-mu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Disable_Comments_MU
 */
class Disable_Comments_MU {

    /**
     * Initalizes the plugin.
     *
     * @return  void
     */
    public function __construct() {
        // These need to happen now
        add_filter( 'wp_headers', array( $this, 'filter_wp_headers' ) );
        add_action( 'template_redirect', array( $this, 'filter_query' ), 9 ); // Before redirect_canonical

        // Admin bar filtering has to happen here since WP 3.6
        add_action( 'template_redirect', array( $this, 'filter_admin_bar' ) );
        add_action( 'admin_init', array( $this, 'filter_admin_bar' ) );

        // These can happen later
        add_action( 'wp_loaded', array( $this, 'setup_filters' ) );
    }

    /**
     * Setup filters.
     *
     * @return  void
     */
    public function setup_filters() {
        $types = array_keys( get_post_types( array( 'public' => true ), 'objects' ) );
        if ( ! empty( $types ) ) {
            foreach ( $types as $type ) {
                // We need to know what native support was for later
                if ( post_type_supports( $type, 'comments' ) ) {
                    remove_post_type_support( $type, 'comments' );
                    remove_post_type_support( $type, 'trackbacks' );
                }
            }
        }

        // Filters for the admin only
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'filter_admin_menu' ), 9999 ); // Do this as late as possible
            add_action( 'admin_print_styles-index.php', array( $this, 'admin_css' ) );
            add_action( 'admin_print_styles-profile.php', array( $this, 'admin_css' ) );
            add_action( 'wp_dashboard_setup', array( $this, 'filter_dashboard' ) );
            add_filter( 'pre_option_default_pingback_flag', '__return_zero' );
        }
        // Filters for front end only
        else {
            add_filter( 'comments_open', array( $this, 'filter_comment_status' ), 20, 2 );
            add_filter( 'pings_open', array( $this, 'filter_comment_status' ), 20, 2 );

            // remove comments links from feed
            add_filter( 'post_comments_feed_link', '__return_false', 10, 1 );
            add_filter( 'comments_link_feed', '__return_false', 10, 1 );
            add_filter( 'comment_link', '__return_false', 10, 1 );

            // remove comment count from feed
            add_filter( 'get_comments_number', '__return_false', 10, 2 );

            // Remove feed link from header
            add_filter( 'feed_links_show_comments_feed', '__return_false' );
        }
    }

    /**
     * Filters the HTTP headers before they're sent to the browser.
     *
     * @param array $headers The list of headers to be sent.
     * @return array
     */
    public function filter_wp_headers( $headers ) {
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    /**
     * Filters comment query.
     *
     * @return void
     */
    public function filter_query() {
        if ( is_comment_feed() ) {
            // We are inside a comment feed
            wp_die( esc_html( 'Comments are closed.' ), '', array( 'response' => 403 ) );
        }
    }

    /**
     * Filters admin bar.
     *
     * @return void
     */
    public function filter_admin_bar() {
        if ( is_admin_bar_showing() ) {
            // Remove comments links from admin bar
            remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
            if ( is_multisite() ) {
                add_action( 'admin_bar_menu', array( $this, 'remove_network_comment_links' ), 500 );
            }
        }
    }

    /**
     * Removes network comment links.
     *
     * @param array $wp_admin_bar The array.
     * @return void
     */
    public function remove_network_comment_links( $wp_admin_bar ) {
        if ( is_user_logged_in() ) {
            foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {
                $wp_admin_bar->remove_menu( 'blog-' . $blog->userblog_id . '-c' );
            }
        }
    }

    /**
     * Filters admin menu.
     *
     * @return void
     */
    public function filter_admin_menu() {
        global $pagenow;

        if ( in_array( $pagenow, array( 'comment.php', 'edit-comments.php', 'options-discussion.php' ), true ) ) {
            wp_die( esc_html( 'Comments are closed.' ), '', array( 'response' => 403 ) );
        }

        remove_menu_page( 'edit-comments.php' );
        remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    }

    /**
     * Filters dashboard.
     *
     * @return void
     */
    public function filter_dashboard() {
        remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
    }

    /**
     * Add admin styles.
     *
     * @return void
     */
    public function admin_css() {
        echo '<style>
            #dashboard_right_now .comment-count,
            #dashboard_right_now .comment-mod-count,
            #latest-comments,
            #welcome-panel .welcome-comments,
            .user-comment-shortcuts-wrap {
                display: none !important;
            }
        </style>';
    }

    /**
     * Filters comment status.
     *
     * @return boolean
     */
    public function filter_comment_status() {
        return false;
    }
}

new Disable_Comments_MU();
