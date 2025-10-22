<?php

/**
 * Plugin Name: KM Events Manager (Custom CPT + Registrations)
 * Description: Custom Events CPT with event registration table, AJAX filter, and attendee tracking.
 * Version: 1.0.0
 * Author: Kirsty Marks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create registrations table on activation
 */
register_activation_hook(__FILE__, 'em_create_event_registrations_table');
function em_create_event_registrations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_event_user (event_id,user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}


add_action('wp_enqueue_scripts', 'em_enqueue_scripts');
function em_enqueue_scripts() {
    wp_enqueue_script('jquery');

    wp_enqueue_script(
        'ajax',
        plugin_dir_url(__FILE__) . 'js/ajax.js',
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'js/ajax.js'),
        true
    );

    wp_localize_script('ajax', 'ajaxSearch', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ajax-search-nonce')
    ));
}

add_action('wp_enqueue_scripts', 'em_enqueue_tailwind');
function em_enqueue_tailwind() {
    wp_enqueue_style(
        'tailwind',
        'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
        array(),
        '2.2.19'
    );
}

add_action('init', 'em_register_cpt');
function em_register_cpt() {
    $labels = array(
        'name'          => 'Events',
        'singular_name' => 'Event',
        'menu_name'     => 'Events',
        'add_new_item'  => 'Add New Event',
        'edit_item'     => 'Edit Event',
        'view_item'     => 'View Event',
        'all_items'     => 'All Events',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-clipboard',
        'supports'           => array('title', 'editor', 'excerpt', 'thumbnail'),
        'has_archive'        => false,
        'rewrite'            => array('slug' => 'events', 'with_front' => false),
        'menu_position'      => 20,
    );

    register_post_type('events_cpt', $args);
}

add_action('init', 'em_register_taxonomy');
function em_register_taxonomy() {
    $labels = array(
        'name'          => 'Event Categories',
        'singular_name' => 'Event Category',
    );

    register_taxonomy('event_category', array('events_cpt'), array(
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => 'event-category'),
    ));
}

/**
 * Add Meta Box
 */
add_action('add_meta_boxes', 'em_add_meta_box');
function em_add_meta_box() {
    add_meta_box(
        'events_cpt_meta',
        'Event Details & Registrations',
        'em_meta_box_callback',
        'events_cpt',
        'normal',
        'default'
    );
}

function em_meta_box_callback($post) {
    $event_date = get_post_meta($post->ID, 'event_date', true);
    $location   = get_post_meta($post->ID, 'location', true);
    $capacity   = get_post_meta($post->ID, 'capacity', true);
    wp_nonce_field('events_cpt_save_meta', 'events_cpt_nonce');

    ?>
    <p>
        <label for="event_date">Event Date:</label><br>
        <input type="datetime-local" id="event_date" name="event_date" value="<?php echo esc_attr($event_date); ?>" />
    </p>
    <p>
        <label for="location">Location:</label><br>
        <input type="text" id="location" name="location" value="<?php echo esc_attr($location); ?>" size="40" />
    </p>
    <p>
        <label for="capacity">Capacity:</label><br>
        <input type="number" id="capacity" name="capacity" value="<?php echo esc_attr($capacity); ?>" min="1" />
    </p>
    <hr>
    <h4>Current Registrations</h4>
    <?php
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $event_id   = $post->ID;
    $registrations = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name WHERE event_id = %d", $event_id)
    );

    if ($registrations) {
        echo '<ul>';
        foreach ($registrations as $reg) {
            $user_info = get_userdata($reg->user_id);
            if ($user_info) {
                echo '<li>' . esc_html($user_info->display_name) . ' (' . esc_html($user_info->user_email) . ')</li>';
            }
        }
        echo '</ul>';
    } else {
        echo '<p>No one has registered yet.</p>';
    }
}

add_action('save_post', 'em_save_meta');
function em_save_meta($post_id) {
    if (!isset($_POST['events_cpt_nonce']) || !wp_verify_nonce($_POST['events_cpt_nonce'], 'events_cpt_save_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['event_date'])) {
        update_post_meta($post_id, 'event_date', sanitize_text_field($_POST['event_date']));
    }
    if (isset($_POST['location'])) {
        update_post_meta($post_id, 'location', sanitize_text_field($_POST['location']));
    }
    if (isset($_POST['capacity'])) {
        update_post_meta($post_id, 'capacity', intval($_POST['capacity']));
    }
}

add_shortcode('event_list', 'em_event_list_shortcode');
function em_event_list_shortcode($atts) {
    ob_start();
    ?>
    <div class="events-list-wrapper p-8">
        <select id="event-filter" class="p-2 border rounded bg-white shadow-sm focus:outline-none focus:ring focus:border-indigo-500">
            <option value="">All Categories</option>
            <?php
            $terms = get_terms(array('taxonomy' => 'event_category','hide_empty' => false));
            foreach ($terms as $term) {
                echo '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
            }
            ?>
        </select>
        <div id="event-results" class="pt-8">
            <?php em_render_events(); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_action('wp_ajax_filter_events', 'em_filter_events');
add_action('wp_ajax_nopriv_filter_events', 'em_filter_events');

function em_render_events($filter = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $today  = date('Y-m-d H:i:s');

    $args = array(
        'post_type'      => 'events_cpt',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => 'event_date',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATETIME'
            )
        ),
        'orderby' => 'meta_value',
        'order'   => 'ASC',
        'meta_key'=> 'event_date',
    );

    if (!empty($filter)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'event_category',
                'field'    => 'slug',
                'terms'    => $filter,
            )
        );
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        echo '<ul class="event-list grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">';
        while ($query->have_posts()) {
            $query->the_post();
            $event_id = get_the_ID();
            $capacity = (int) get_post_meta($event_id, 'capacity', true);

            $attendees = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE event_id = %d", $event_id)
            );

            $spots = $capacity - $attendees;

            echo '<li class="bg-white shadow rounded transition event-list__event rounded">';
            echo '<a href="' . get_the_permalink() . '" class="block">';
            if (has_post_thumbnail($event_id)) {
                echo '<div class="mb-3">';
                echo get_the_post_thumbnail($event_id, 'full', array(
                    'class' => 'w-full h-80 object-cover'
                ));
                echo '</div>';
            }
            echo '<div class="p-6 event-list__event">';
            echo '<strong class="text-lg font-semibold ">' . get_the_title() . '</strong><br>';
            $event_date_raw = get_post_meta($event_id, 'event_date', true);
            $event_date_fmt = $event_date_raw ? date_i18n('j M Y', strtotime($event_date_raw)) : '';
            echo '<p class="text-sm">Date: ' . esc_html($event_date_fmt) . '</p>';
            echo '<span class="block text-sm">Location: ' . esc_html(get_post_meta($event_id, 'location', true)) . '</span>';
            echo '<span class="block text-sm font-medium ">Available Spots: ' . esc_html($spots) . '</span>';
            echo '</div>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No future events found.</p>';
    }

    wp_reset_postdata();
}


function em_filter_events() {
    check_ajax_referer('ajax-search-nonce', 'security');
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';

    $today  = date('Y-m-d H:i:s');
    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : '';

    $args = array(
        'post_type'      => 'events_cpt',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => 'event_date',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATETIME'
            )
        ),
        'orderby' => 'meta_value',
        'order'   => 'ASC',
        'meta_key'=> 'event_date',
    );

    if (!empty($filter)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'event_category',
                'field'    => 'slug',
                'terms'    => $filter,
            )
        );
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        echo '<ul class="event-list grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">';
        while ($query->have_posts()) {
            $query->the_post();
            $event_id = get_the_ID();
            $capacity = (int) get_post_meta($event_id, 'capacity', true);

            $attendees = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE event_id = %d", $event_id)
            );

            $spots = $capacity - $attendees;

            echo '<li class="bg-white shadow rounded transition event-list__event rounded">';
            echo '<a href="' . get_the_permalink() . '" class="block">';
            if (has_post_thumbnail($event_id)) {
                echo '<div class="mb-3">';
                echo get_the_post_thumbnail($event_id, 'full', array(
                    'class' => 'w-full h-80 object-cover'
                ));
                echo '</div>';
            }
            echo '<div class="p-6 event-list__event">';
            echo '<strong class="text-lg font-semibold ">' . get_the_title() . '</strong><br>';
            $event_date_raw = get_post_meta($event_id, 'event_date', true);
            $event_date_fmt = $event_date_raw ? date_i18n('j M Y', strtotime($event_date_raw)) : '';
            echo '<p class="text-sm">Date: ' . esc_html($event_date_fmt) . '</p>';
            echo '<span class="block text-sm">Location: ' . esc_html(get_post_meta($event_id, 'location', true)) . '</span>';
            echo '<span class="block text-sm font-medium ">Available Spots: ' . esc_html($spots) . '</span>';
            echo '</div>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No future events found.</p>';
    }
    wp_die();
}

/**
 * AJAX: Register Interest
 */
add_action('wp_ajax_register_event_interest', 'em_register_event_interest');
add_action('wp_ajax_nopriv_register_event_interest', 'em_register_event_interest');
function em_register_event_interest() {
    check_ajax_referer('ajax-search-nonce', 'security');

    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $user_id  = get_current_user_id();
    $event_id = intval($_POST['event_id']);

    $capacity = (int) get_post_meta($event_id, 'capacity', true);
    $attendees = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE event_id = %d", $event_id)
    );

    if ($attendees >= $capacity) {
        wp_send_json_error('Sorry, this event is already full.');
    }

    $inserted = $wpdb->query(
        $wpdb->prepare("INSERT IGNORE INTO $table_name (event_id, user_id) VALUES (%d, %d)", $event_id, $user_id)
    );

    if ($inserted === false) {
        wp_send_json_error('DB error – table might not exist yet.');
    } elseif ($inserted === 0) {
        wp_send_json_error('You have already registered for this event.');
    } else {
        wp_send_json_success('You have successfully registered! Remaining spots: ' . ($capacity - ($attendees + 1)));
    }
}

/**
 * Admin Column: Registrations / Capacity
 */
add_filter('manage_events_cpt_posts_columns', 'em_columns');
function em_columns($columns) {
    $columns['registrations'] = 'Registrations';
    return $columns;
}

add_action('manage_events_cpt_posts_custom_column', 'em_column_content', 10, 2);
function em_column_content($column, $post_id) {
    if ($column === 'registrations') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_registrations';
        $count = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE event_id = %d", $post_id)
        );
        $capacity = (int) get_post_meta($post_id, 'capacity', true);
        echo esc_html($count . ' / ' . $capacity);
    }
}