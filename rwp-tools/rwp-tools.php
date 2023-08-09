<?php
/**
 * Plugin Name: RWP Tools
 * Description: A simple plugin to delete images from trashed WooCommerce products.
 * Version: 1.0
 * Author: Raluca Manea
 */

function rwptools_enqueue_scripts($hook) {
    if(!in_array($hook, ['rwp-tools_page_delete-product-images','rwp-tools_page_remove-unused-images'])) {
        return;
    }

    wp_enqueue_script('rwp-tools-script', plugin_dir_url(__FILE__) . 'js/rwp-tools-script.js', array('jquery'), null, true);

    // Pass ajax_url to script.js
    wp_localize_script('rwp-tools-script', 'rwp_tools', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('admin_enqueue_scripts', 'rwptools_enqueue_scripts');


// Handle AJAX request for removing unused images
function rwp_handle_remove_unused_images() {
    // Get the 'page' parameter from the AJAX request
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;

    $folder_url = isset($_POST['folder-url']) ? sanitize_text_field($_POST['folder-url']) : null;
    $result = rwp_remove_unattached_images($page, $folder_url);

    // Return a JSON response
    wp_send_json_success(array(
        'message' => "{$result['deleted']} unused images were removed on page {$page}.",
        'processed' => $result['checked'] > 0,
        'checked' => $result['checked'],
        'removed_images' => $result['removed_images']
    ));
}
add_action('wp_ajax_rwp_remove_unused_images', 'rwp_handle_remove_unused_images');

// Server-side function to identify and remove unattached images in batches
// Logic to:
// 1. Scan the wp-content/uploads/ directory
// 2. Compare image list with attached images in the WordPress database
// 3. Delete unattached images in batches
function rwp_remove_unattached_images($page = 1, $folder_url = null) {
    // Define the batch size
    $batch_size = 50;


    // Get the absolute path to the wp-content/uploads directory
    $uploads_dir = wp_get_upload_dir();
    // If a folder URL is provided, convert it to a path
    if ($folder_url) {
        $uploads_path = str_replace($uploads_dir['baseurl'], $uploads_dir['basedir'], $folder_url);
    }else{
        $uploads_path = $uploads_dir['basedir'];
    }


    // Recursive function to gather all image files from a directory and its subdirectories
    function get_image_files($directory) {
        $image_files = glob($directory . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        $subdirs = glob($directory . '/*', GLOB_ONLYDIR);
        foreach ($subdirs as $subdir) {
            $image_files = array_merge($image_files, get_image_files($subdir));
        }
        return $image_files;
    }

    // Get all image files
    $all_image_files = get_image_files($uploads_path);

    // Determine the starting index based on the page number
    $start = ($page - 1) * $batch_size;
    $end = $start + $batch_size;

    $checked = 0;
    $removed_images = [];

    for ($i = $start; $i < $end && $i < count($all_image_files); $i++) {
        $image_file = $all_image_files[$i];
        $image_filename = basename($image_file);

        // Check if the image is attached to any post
        $args = array(
            'post_type' => 'attachment',
            'name' => pathinfo($image_filename, PATHINFO_FILENAME),
            'post_status' => 'inherit',
            'posts_per_page' => 1,
        );
        $attachments = get_posts($args);

        if (empty($attachments)) {
            // If the image isn't attached to any post, delete the file and add its URL to the array
            if (unlink($image_file)) {
                $relative_path = str_replace($uploads_path, '', $image_file);
                $removed_images[] = $uploads_dir['baseurl'] . $relative_path;
            }
        }

        $checked++;
    }

    return [
        'checked' => $checked,
        'deleted' => count($removed_images),
        'removed_images' => $removed_images
    ];
}






function my_plugin_handle_delete_images() {
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;

    $deleted_count = my_plugin_delete_images($page);

//    wp_send_json_success(array('message' => "still loading... other {$deleted_count} products were processed."));
    wp_send_json_success(array('message' => "still loading... other {$deleted_count} products were processed.", 'actualdeletednumber' => "{$deleted_count}"));
}

add_action('wp_ajax_delete_images', 'my_plugin_handle_delete_images'); // If you want it to work for logged out users too, use wp_ajax_nopriv_delete_images


function my_plugin_admin_menu() {
    // Create the main menu "RWP Tools" if it doesn't exist
    if (empty($GLOBALS['admin_page_hooks']['wp-tools'])) {
        add_menu_page(
            'RWP Tools',
            'RWP Tools',
            'manage_options',
            'wp-tools',
            'my_plugin_main_menu_page', // You can set this to display some content or information
            null,
            100 // this is the position in the menu order
        );
    }

    // Add "RWP Tools" as a submenu of "RWP Tools"
    add_submenu_page(
        'wp-tools', // slug of the parent menu
        'Delete Woo Product Images',
        'Delete Woo Product Images',
        'manage_options',
        'delete-product-images',
        'my_plugin_admin_page'
    );

    // Add new submenu "Remove Unused Images"
    add_submenu_page(
        'wp-tools', // slug of the parent menu
        'Remove Unused Images',
        'Remove Unused Images',
        'manage_options',
        'remove-unused-images',
        'rwp_remove_unused_images_page'
    );
}
add_action('admin_menu', 'my_plugin_admin_menu');

// New function to render content for the "Remove Unused Images" submenu
function rwp_remove_unused_images_page() {
    echo '<div class="wrap">';
    echo '<h1>Remove Unused Images</h1>';
    echo '<form method="post" id="rwp-remove-images-form" onsubmit="return false;">';
    echo '<label for="folder-url">Folder URL:</label>';
    echo '<input type="text" id="folder-url" name="folder-url" value="' . esc_url( wp_get_upload_dir()['baseurl'] ) . '" style="width: 100%; margin-bottom: 10px;">';
    echo '<button type="submit" class="button button-primary" id="remove-unused-images-button">Remove unused images</button>';
    echo '</form>';
    echo '<div id="rwp-remove-images-message"></div>';  // Placeholder for the message
    echo '<div id="rwp-checked-images-count"></div>';
    echo '<ul id="rwp-removed-images-list"></ul>';
    echo '</div>';
}


// Dummy function for the main menu content. You can customize this.
function my_plugin_main_menu_page() {
    echo '<h1>RWP Tools</h1>';
    echo '<p>Welcome to RWP Tools. Use the submenus to access various tools.</p>';
}

// ... (rest of your plugin code)


function my_plugin_admin_page() {
    echo '<div class="wrap">';
    echo '<h1>Delete Images from Trashed Products</h1>';
    echo '<form method="post" id="my-plugin-form" onsubmit="return false;">';  // Update the form's onsubmit attribute to prevent default behavior
    echo '<button type="submit" class="button button-primary" id="delete-images-button">Delete Images</button>';
    echo '</form>';
    echo '<div id="my-plugin-message"></div>';  // Placeholder for the message
    echo '</div>';
}


function my_plugin_delete_images($page = 1) {
    // Ensure WooCommerce is active
    if (!function_exists('wc_get_product')) return 0;

    $products_per_page = 10; // Define how many products to process per request

    $args = array(
        'post_type' => 'product',
        'post_status' => 'trash',
        'posts_per_page' => $products_per_page,
        'paged' => $page
    );

    $processed_count = 0;
    $trashed_products = get_posts($args);

    foreach ($trashed_products as $product) {
        $product_id = $product->ID;

        // Get gallery image IDs
        $attachment_ids = get_post_meta($product_id, '_product_image_gallery', true);
        $attachment_ids = explode(',', $attachment_ids);

        foreach ($attachment_ids as $attachment_id) {
            // Check if this attachment is used by any other products
            $is_used_elsewhere = my_plugin_check_image_usage($attachment_id);

            // If the image is not used by any other product, delete it
            if (!$is_used_elsewhere) {
                wp_delete_attachment($attachment_id, true);
            }
        }
        $processed_count++;
    }

    return $processed_count;
}


function my_plugin_check_image_usage($attachment_id) {
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_product_image_gallery',
                'value' => (string) $attachment_id,
                'compare' => 'LIKE'
            )
        )
    );

    $query = new WP_Query($args);
    return $query->have_posts();
}

?>
