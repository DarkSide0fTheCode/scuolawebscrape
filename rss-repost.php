<?php
/*
Plugin Name: RSS Feed to Blog Post
Description: Fetches RSS feed items and posts them as blog posts with customization options.
Version: 0.8
Author: DarkSideOfTheCode
*/

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');


function echo_log($what)
{
    echo '<pre>' . print_r($what, true) . '</pre>';
}

function get_channel_link($rss_data)
{
    // Load the RSS data into a SimpleXMLElement
    $rss = new SimpleXMLElement($rss_data);

    // Get the <channel> element
    $channel = $rss->channel;

    // Get the <link> element inside the <channel>
    $link = $channel->link;

    // Return the link as a string
    return (string) $link;
}

// function postRequest($url, $data) {
//     $options = array(
//         CURLOPT_URL => $url,
//         CURLOPT_POST => true,
//         CURLOPT_POSTFIELDS => json_encode($data),
//         CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
//         CURLOPT_RETURNTRANSFER => true
//     );

//     echo_log("Preparing to post: " . $url . "\n");
    
//     $ch = curl_init();
//     curl_setopt_array($ch, $options);
//     $result = curl_exec($ch);

//     if (curl_errno($ch)) {
//         echo 'Error scraping:' . curl_error($ch);
//     }

//     curl_close($ch);

//     return $result;
// }

function delete_all_articles()
{
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'numberposts' => -1
    );

    $all_posts = get_posts($args);

    foreach ($all_posts as $post) {
        wp_delete_post($post->ID, true);
    }

    echo '<p>All articles have been deleted.</p>';
}

// Function to add a new menu item in the WordPress admin dashboard
function my_admin_menu()
{
    // add_menu_page function adds a new top-level menu to the WordPress admin interface
    // Parameters are: page title, menu title, capability, menu slug, function to display the page content
    add_menu_page('MalibúTech RSS', 'MalibúTech RSS', 'manage_options', 'malibutech_feed', 'malibutech_feed_callback');
}

// Function to display the content of the custom admin page
function malibutech_feed_callback()
{
    // Start of the page content
    echo '<div class="wrap">';
    // Instructions for the user
    echo '<p>Clicca il bottone per scaricare le news.</p>';
    // Start of the form
    echo '<form method="POST" style="display: flex; flex-direction: column;">';
    // Submit button
    echo '<input type="submit" name="fetch_rss_feed" value="Scarica News dai Feed RSS">';
    echo '<input type="submit" name="delete_all_articles" value="Elimina articoli">';
    // End of the form
    echo '</form>';
    // End of the page content
    echo '</div>';

    // Check if the form has been submitted
    if (isset($_POST['fetch_rss_feed'])) {
        // Call the function to fetch the RSS feed and post to blog
        fetch_rss_feed_and_post_to_blog();
        // Display a message to indicate that the RSS feed has been fetched and posted to blog
        echo '<p>News scaricate dai Feed RSS e postate sul blog.</p>';
    }

    if (isset($_POST['delete_all_articles'])) {
        delete_all_articles();
    }

    // Query all attachments
    $query_images_args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'posts_per_page' => -1,
    );

    $query_images = new WP_Query($query_images_args);

    // Start the dropdown
    echo '<select name="image-dropdown" id="image-dropdown" onchange="showImagePreview(this)">';

    // Loop through the attachments and create the dropdown options
    foreach ($query_images->posts as $image) {
        echo '<option value="' . wp_get_attachment_url($image->ID) . '">' . $image->post_title . '</option>';
    }

    // End the dropdown
    echo '</select>';

    // Add an image element for the preview
    echo '<img id="image-preview" src="" style="max-width: 200px; display: none;" />';

    // Add the JavaScript code
    echo '
    <script type="text/javascript">
        function showImagePreview(selectElement) {
            var previewElement = document.getElementById("image-preview");
            previewElement.src = selectElement.value;
            previewElement.style.display = "block";
        }
    </script>
    ';
}


function remove_non_paragraph_elements($article_content)
{
    $dom = new DOMDocument;

    // Load the HTML into the DOMDocument instance
    @$dom->loadHTML($article_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Create a new DOMXPath instance
    $xpath = new DOMXPath($dom);

    // Query all <p> elements
    $nodes = $xpath->query("//div/p");

    // Initialize an empty string to hold the new HTML
    $new_html = '';

    // Append each <p> element and its inner HTML to the new HTML string
    foreach ($nodes as $node) {
        $new_html .= $dom->saveHTML($node) . "\n";
    }

    return $new_html;
}

function scrape_request($url, $parserId)
{
    // Prepare the URL for the GET request
    echo_log("Preparing to fetch: " . $url . "\n");
    // $request_url = 'http://82.55.177.110:3000/extract?url=' . urlencode($url);
    echo_log("\n");

    // echo_log($request_url);

    // Make the GET request
    // $response = wp_remote_get($request_url, array('timeout' => 15));
    // echo_log($response);

    // $data = array(
    //     "cmd" => "request.get",
    //     "url" => $url,
    //     "maxTimeout" => 60000
    // );
    
    // $response = postRequest('http://188.216.202.161:8191/v1', $data);

    $request_url = 'http://62.68.68.28:3000/extract?target=' . urlencode($url);

    $response = wp_remote_get($request_url, array('timeout' => 60));

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        echo "Something went wrong: $error_message";
        return;
    }


    // Get the body of the response
    echo_log("Preparing to retrieve body \n");

    // What type of variable is $response?
    echo_log("Type of response: " . gettype($response) . "\n");
    
    // echo_log($response);

    $body = wp_remote_retrieve_body($response);

    // echo_log($body);

    $article_content = mb_convert_encoding($body, 'HTML-ENTITIES', 'UTF-8');

    // echo_log($article_content);

    $cleaned_content = remove_non_paragraph_elements($article_content);

    echo_log($cleaned_content);

    return $cleaned_content;
    // echo_log("cleaned_content: \n" . $cleaned_content . "\n");
    // return $cleaned_content;

    // The body should be a JSON string, so decode it into an array
    // echo_log("Preparing to retrieve data \n");

    // $data = json_decode($body, true);

    // echo_log("data retrieved! \n");


    // Check if the request was successful
    // if ($data['success']) {
    //     // The article content should be in the 'data' field
    //     $article_content = $data['data']['articleContent'];
    //     echo_log("Type of article_content: " . gettype($article_content) . "\n");
    //     echo_log("article_content: \n" . $article_content . "\n");
    //     $article_content = mb_convert_encoding($article_content, 'HTML-ENTITIES', 'UTF-8');
    //     $cleaned_content = remove_non_paragraph_elements($article_content);

    //     // echo_log("cleaned_content: \n" . $cleaned_content . "\n");
    //     // Return the article content
    //     return $cleaned_content;
    // } else {
    //     // Handle the error
    //     echo "Error: " . $data['error']['message'];
    //     return;
    // }
}

// Create a function to fetch and process RSS feed items
function fetch_rss_feed_and_post_to_blog()
{
    // Define the RSS feed URLs as an associative array with URL, corresponding image URL, and tags
    $rss_feed_urls = array(
        'https://www.orizzontescuola.it/feed/' => array(
            'image_title' => 'notizie-default',
            // 'tags' => array('test-scuola', 'test-school'), // Add specific tags for this URL
            'category' => "Notizie",
            'parserId' => '.entry-content',
            'post_author' => 2, // Set the author
            'label' => 'Orizzontescuola'

        ),
        // 'https://www.vivoscuola.it/itfeed/rss/tag/162' => array(
        //     'image_title' => 'notizie-default',
        //     'tags' => array('scuola secondaria', 'secondo grado'), // Add specific tags for this URL
        //     'category' => "Scuola Secondaria",
        //     'parserId' => '.col-lg-8',
        //     'post_author' => 2, // Set the author

        // ),
    );

    // Loop through each RSS feed URL, its associated image URL, and tags
    foreach ($rss_feed_urls as $rss_feed_url => $data) {
        $post_image = $data['image_title'];
        $image_title = $data['image_title'];
        $post_tags = $data['tags'];
        $post_category = $data['category'];
        $post_author = $data['post_author'];
        $label = $data['label'];

        // Fetch the RSS feed

        echo_log("FETCHING RSS URL: " .$rss_feed_url);
        $rss = fetch_feed($rss_feed_url);

		// echo_log($rss);

        if (!is_wp_error($rss)) {
            $website_link = $rss->get_base();

            // Get the RSS feed items
            $max_items = $rss->get_item_quantity(20); // Change 10 to the number of items you want to fetch
            $rss_items = $rss->get_items(0, $max_items);



            // Loop through each feed item
            foreach ($rss_items as $item) {
                // Get the necessary data from the feed item
                $post_title = $item->get_title();

                echo_log("Fetching RSS feed with title: " .$post_title);

                // $post_content = $item->get_content(); 
                $post_date = $item->get_date('Y-m-d H:i:s');
                $post_link = $item->get_permalink();


                // Extracting image if available in the feed item
                $post_image = ''; // Initialize the variable for the image URL
                $enclosures = $item->get_enclosures();
                if (!empty($enclosures)) {
                    foreach ($enclosures as $enclosure) {
                        if ($enclosure->get_type() === 'image/jpeg' || $enclosure->get_type() === 'image/png') {
                            $post_image = $enclosure->get_link();
                            break; // Stop after finding the first image enclosure
                        }
                    }
                }

                // Check if a post with the same title already exists
                $existing_post_query = new WP_Query(
                    array(
                        'post_type' => 'post',
                        'post_status' => 'publish',
                        'title' => $post_title
                    )
                );

                if ($existing_post_query->have_posts()) {
                    // A post with the same title already exists, skip this item
                    echo_log("Post with title " . $post_title . " already exists - skipping \n");
                    continue;
                }

                $articleContent = scrape_request($post_link, $data['parserId']);

                // $articleContent .= '<p>Fonte: ' . $website_link . '</p>';
                $articleContent .= '<p>Fonte: <a title="'. $label .'" href="'. $website_link . '" target="_blank">' . $label . '</a></p>';

                // Create a new post
                $new_post = array(
                    'post_title' => $post_title,
                    'post_content' => $articleContent, // Add custom tags to the content
                    'post_date' => $post_date,
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'post_author' => $post_author // Set the author

                );

                // Insert the post into the database
                $post_id = wp_insert_post($new_post);

                // Set the post as not commentable
                wp_update_post(
                    array(
                        'ID' => $post_id,
                        'comment_status' => 'closed',
                    )
                );

                echo_log("Analizing: " . $post_link . "\n");
                echo_log("Post ID: " . $post_id . "\n");


                // Check if the category exists
                $category_exists = term_exists($post_category, 'category');

                if ($category_exists == 0 || $category_exists == null) {
                    // Category doesn't exist, create a new one
                    $new_category_id = wp_insert_category(array('cat_name' => $post_category));
                    // Assign the new category to the post
                    wp_set_post_categories($post_id, array($new_category_id));
                } else {
                    // Category exists, assign it to the post
                    wp_set_post_categories($post_id, array($category_exists['term_id']));
                }

                // Set the tags for the post
                wp_set_post_tags($post_id, $post_tags, false);


                // If an image was found in the feed item, set it as the post's featured image
                if ($post_image !== '') {
                    echo_log("Image found in RSS\n");

                    $filename = basename($post_image);

                    echo_log("filename is " . $filename . "\n");

                    // Check if the image already exists in the media library
                    $query_images_args = array(
                        'post_type' => 'attachment',
                        'post_mime_type' => 'image',
                        'post_status' => 'inherit',
                        'posts_per_page' => -1,
                        'meta_query' => array(
                            array(
                                'key' => '_wp_attached_file',
                                'value' => $filename,
                                'compare' => 'LIKE',
                            ),
                        ),
                    );

                    echo_log("query_images_args is " . $query_images_args . "\n");

                    $query_images = new WP_Query($query_images_args);

                    echo_log("query_images is " . $query_images . "\n");

                    if ($query_images->posts) {
                        // The image already exists in the media library, so use it
                        echo_log("Image found in media library - using it \n");
                        $image_id = $query_images->posts[0]->ID;
                    } else {
                        // The image does not exist in the media library, so upload it
                        echo_log("Image not found in media library - uploading \n");
                        $image = media_sideload_image($post_image, $post_id, $post_title, 'id');
                        if (!is_wp_error($image)) {
                            $image_id = $image;
                        }
                    }

                    // Set the image as the post's featured image
                    if (isset($image_id)) {
                        set_post_thumbnail($post_id, $image_id);
                    }

                } else {
                    // Set a default image as the post's featured image
                    echo_log("Image not found in RSS - using default image \n");
                    echo_log($image_title);
                    echo_log("\n");

                    // Check if the image already exists in the media library
                    // Check if the image already exists in the media library
                    $query_images_args = array(
                        'post_type' => 'attachment',
                        'post_mime_type' => 'image',
                        'post_status' => 'inherit',
                        'posts_per_page' => -1,
                        'title' => $image_title, // replace this with the title of the image
                    );

                    $query_images = new WP_Query($query_images_args);

                    if ($query_images->posts) {
                        // The image already exists in the media library, so use it
                        echo_log("Image found in media library - using it \n");
                        $image_id = $query_images->posts[0]->ID;
                    } else {
                        echo_log("ERROR: Image not found in media library! Check the title");
                    }

                    // Set the image as the post's featured image
                    if (isset($image_id)) {
                        set_post_thumbnail($post_id, $image_id);
                    }
                }


            }
        }
    }
}

// Schedule the function to run at regular intervals
function schedule_fetch_rss_feed()
{
    if (!wp_next_scheduled('Scheduled_Scuolafetch')) {
        wp_schedule_event(time(), 'hourly', 'Scheduled_Scuolafetch');
    }
}
add_action('wp', 'schedule_fetch_rss_feed');
add_action('Scheduled_Scuolafetch', 'fetch_rss_feed_and_post_to_blog');
add_action('admin_menu', 'my_admin_menu');

function custom_plugin_feed_cache_lifetime( $seconds ) {
    // Set the cache lifetime to 6 hours (in seconds) for your plugin
    // return 21600; // 6 hours * 60 minutes * 60 seconds
    return 0.1 * 60 * 60; // 6 hours * 60 minutes * 60 seconds

}


add_filter( 'wp_feed_cache_transient_lifetime', 'custom_plugin_feed_cache_lifetime' );


// function turn_off_feed_caching( $feed ) {
    // $feed->enable_cache( false );
// }

// add_action( 'wp_feed_options', 'turn_off_feed_caching' );
