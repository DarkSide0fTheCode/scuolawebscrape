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

    $response = wp_remote_get($request_url);

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
    
    $body = wp_remote_retrieve_body($response);

    echo_log("Body retrieved! \n");

    // The body should be a JSON string, so decode it into an array
    echo_log("Preparing to retrieve data \n");

    $data = json_decode($body, true);

    echo_log("data retrieved! \n");


    // Check if the request was successful
    if ($data['success']) {
        // The article content should be in the 'data' field
        $article_content = $data['data']['articleContent'];
        echo_log("Type of article_content: " . gettype($article_content) . "\n");
        echo_log("article_content: \n" . $article_content . "\n");
        $article_content = mb_convert_encoding($article_content, 'HTML-ENTITIES', 'UTF-8');
        $cleaned_content = remove_non_paragraph_elements($article_content);

        // echo_log("cleaned_content: \n" . $cleaned_content . "\n");
        // Return the article content
        return $cleaned_content;
    } else {
        // Handle the error
        echo "Error: " . $data['error']['message'];
        return;
    }
}

// Create a function to fetch and process RSS feed items
function fetch_rss_feed_and_post_to_blog()
{
    // Define the RSS feed URLs as an associative array with URL, corresponding image URL, and tags
    $rss_feed_urls = array(
        'https://www.orizzontescuola.it/feed/' => array(
            'image_title' => 'notizie-default',
            'tags' => array('test-scuola', 'test-school'), // Add specific tags for this URL
            'category' => "Notizie",
            'parserId' => '.entry-content',
            'post_author' => 2, // Set the author

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

        // Fetch the RSS feed

        $rss = fetch_feed($rss_feed_url);

        if (!is_wp_error($rss)) {
            $website_link = $rss->get_base();

            // Get the RSS feed items
            $max_items = $rss->get_item_quantity(10); // Change 10 to the number of items you want to fetch
            $rss_items = $rss->get_items(0, $max_items);



            // Loop through each feed item
            foreach ($rss_items as $item) {
                // Get the necessary data from the feed item
                $post_title = $item->get_title();
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

                $articleContent .= '<p>Fonte: ' . $website_link . '</p';

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
    if (!wp_next_scheduled('fetch_rss_feed_event')) {
        wp_schedule_event(time(), 'hourly', 'fetch_rss_feed_event');
    }
}
add_action('wp', 'schedule_fetch_rss_feed');
add_action('fetch_rss_feed_event', 'fetch_rss_feed_and_post_to_blog');
add_action('admin_menu', 'my_admin_menu');

// $salve = "<div>\n<div></div>\n<header>\n<div>\n<span>\n<a href=\"https://www.orizzontescuola.it/ata/\">ATA</a> </span>\n<span>\n<a href=\"https://www.orizzontescuola.it/proroga-contratti-ata-5-739-collaboratori-scolastici-3-166-assistenti-tecnici-e-amministrativi-in-arrivo-le-istruzioni-del-ministero/\"><time>28 Dic 2023 - 15:01</time></a> </span>\n</div>\n<h1>Proroga contratti ATA: 5.739 collaboratori scolastici. 3.166 assistenti tecnici e amministrativi. In arrivo le istruzioni del Ministero</h1> <div>\nDi <span><a href=\"https://www.orizzontescuola.it/author/redazione/\">redazione</a></span> </div>\n</header>\n<div>\n\n<a href=\"https://facebook.com/sharer/sharer.php?u=https://www.orizzontescuola.it/proroga-contratti-ata-5-739-collaboratori-scolastici-3-166-assistenti-tecnici-e-amministrativi-in-arrivo-le-istruzioni-del-ministero/\" target=\"_blank\">\n<div><div>\n</div>Facebook</div>\n</a>\n\n<a href=\"https://twitter.com/intent/tweet/?text=Proroga contratti ATA: 5.739 collaboratori scolastici. 3.166 assistenti tecnici e amministrativi. In arrivo le istruzioni del Ministero&amp;url=https://www.orizzontescuola.it/proroga-contratti-ata-5-739-collaboratori-scolastici-3-166-assistenti-tecnici-e-amministrativi-in-arrivo-le-istruzioni-del-ministero/\" target=\"_blank\">\n<div><div>\n</div>Twitter</div>\n</a>\n\n<a target=\"_blank\">\n<div><div>\n</div>WhatsApp</div>\n</a>\n\n<a href=\"https://t.me/share/url?text=Proroga contratti ATA: 5.739 collaboratori scolastici. 3.166 assistenti tecnici e amministrativi. In arrivo le istruzioni del Ministero&amp;url=https://www.orizzontescuola.it/proroga-contratti-ata-5-739-collaboratori-scolastici-3-166-assistenti-tecnici-e-amministrativi-in-arrivo-le-istruzioni-del-ministero/\" target=\"_blank\">\n<div><div>\n</div>Telegram</div>\n</a>\n\n<a href=\"https://www.printfriendly.com\" target=\"_self\">\n<div><div>\n\n\n\n\n</div>Stampa</div>\n</a>\n\n</div>\n<div></div>\n<div></div>\n<div>\n </div>\n<div></div>\n<div></div>\n<p>Il Ministero ci ha informato che i contratti a tempo determinato per i collaboratori scolastici, per un totale di 5.739 (4320 da PNRR e 1419 da Agenda Sud), e i contratti per gli assistenti amministrativi e tecnici, per un totale di 3.166, saranno prorogati.</p>\n<div></div>\n<div></div>\n<p>Lo scrive la <strong>Uil Scuola Rua</strong>, che dunque comunica i piani in merito alla proroga dei contratti ATA.</p>\n<p>Capitolo <strong>collaboratori scolastici</strong>: per tale personale – scrive il sindacato – il canale di finanziamento è la legge di Bilancio che è ancora in corso di approvazione e che prevede la proroga dei contratti, senza soluzione di continuità, a partire dal 1° gennaio 2024 e sino al <strong>15 aprile 2024</strong> nonostante la funzione SIDI per la proroga dei contratti, sarà disponibile solo dall’8 gennaio 2024.</p>\n<p>Per quanto riguarda gli <strong>assistenti amministrativi e assistenti tecnici</strong>, il canale di finanziamento è il Decreto legge n. 145/2023 convertito in Legge n. 191/2023 con risorse non a carico dello Stato ma previste dal PNRR. In questo caso la norma prevede l’attivazione di nuovi incarichi di personale amministrativo e tecnico secondo il finanziamento stabilito, per cui al momento non c’è una data di scadenza precisa dei contratti come invece è prevista dalla Legge di Bilancio per i collaboratori scolastici. In base a quanto si apprende, in questo caso la proroga dovrebbe essere fino al <strong>30 giugno 2026.</strong></p>\n<p>In giornata, spiega la Uil Scuola Rua, il Ministero invierà alle scuole le istruzioni operative per la corretta stipula dei contratti e metterà a disposizione delle stesse un simulatore per la gestione delle risorse PNRR al fine di calibrare con esattezza la data di termine del contratto per gli assistenti amministrativi e tecnici.</p>\n<blockquote><p><a href=\"https://www.orizzontescuola.it/organico-aggiuntivo-ata-chi-riguarda-la-proroga-al-15-aprile-2024-in-legge-di-bilancio/\">Organico aggiuntivo ATA, chi riguarda la proroga al 15 aprile 2024 in legge di Bilancio?</a></p></blockquote>\n<p></p>\n<p></p>\n<div>\n<div></div>\n<div></div>\n<div>\n\n<a href=\"https://facebook.com/sharer/sharer.php?u=https://www.orizzontescuola.it/proroga-contratti-ata-5-739-collaboratori-scolastici-3-166-assistenti-tecnici-e-amministrativi-in-arrivo-le-istruzioni-del-ministero/\" target=\"_blank\">\n<div><div>\n</div>Facebook</div>\n</a>\n\n<a href=\"https://twitter.com/intent/tweet/?text=Proroga contratti ATA: 5.739 collaboratori scolastici. 3.166 assistenti tecnici e amministrativi. In arrivo le istruzioni del Ministero&amp;url=https://www.orizzontescuola.it/proroga-contratti-ata-5-739-collaboratori-scolastici-3-166-assistenti-tecnici-e-amministrativi-in-arrivo-le-istruzioni-del-ministero/\" target=\"_blank\">\n<div><div>\n</div>Twitter</div>\n</a>\n\n<a target=\"_blank\">\n<div><div>\n</div>WhatsApp</div>\n</a>\n\n<a href=\"https://t.me/share/url?text=Proroga contratti ATA: 5.739 collaboratori scolastici. 3.166 assistenti tecnici e amministrativi. In arrivo le istruzioni del Ministero&amp;url=https://www.orizzontescuola.it/proroga-contratti-ata-5-739-collaboratori-scolastici-3-166-assistenti-tecnici-e-amministrativi-in-arrivo-le-istruzioni-del-ministero/\" target=\"_blank\">\n<div><div>\n</div>Telegram</div>\n</a>\n\n<a href=\"https://www.printfriendly.com\" target=\"_self\">\n<div><div>\n\n\n\n\n</div>Stampa</div>\n</a>\n\n</div>\n<div>\n<h2>Corsi</h2>\n<article>\n<h2><a href=\"https://www.orizzontescuola.it/concorso-straordinario-docenti-6-nuove-lezioni-live-per-superare-la-prova-scritta-analizzeremo-ulteriori-400-quesiti-su-tutti-gli-argomenti-del-test-2-edizione/\">Concorso straordinario docenti, 6 nuove lezioni live per superare la prova scritta. Analizzeremo ulteriori 400 quesiti su tutti gli argomenti del test – 2° edizione</a></h2> </article>\n<article>\n<h2><a href=\"https://www.orizzontescuola.it/pnrr-3-1-steam-scuole-possono-presentare-progetti-innovativi-le-indicazioni-operative-sulle-procedure-amministrative-negoziali-e-contabili-in-un-webinar-giorno-8-gratuito-per-gli-abbonati-plus/\">PNRR 3.1 STEAM, scuole possono presentare progetti innovativi. Le indicazioni operative sulle procedure amministrative, negoziali e contabili in un WEBINAR giorno 8, gratuito per gli abbonati PLUS</a></h2> </article>\n<a href=\"https://www.orizzontescuolaformazione.it/\">Tutti i corsi</a>\n</div>\n<div>\n<h2>Orizzonte Scuola PLUS</h2>\n<article>\n<h2><a href=\"https://www.orizzontescuola.it/gestire-il-personale-scolastico-anno-3-n2-supplenze-personale-docente-tutto-quello-che-ce-da-sapere-con-casi-concreti-per-segreterie-e-docenti/\">Gestire il personale scolastico anno 3 n°2 – Supplenze personale docente: tutto quello che c’è da sapere con casi concreti per segreterie e docenti</a></h2> </article>\n<article>\n<h2><a href=\"https://www.orizzontescuola.it/la-dirigenza-scolastica-anno-3-n4-le-novita-dautunno-circolari-adempimenti-e-scadenze-autunnali-per-scuole-e-dirigenti-scolastici-abbonati-o-acquistala/\">La dirigenza scolastica. Anno 3 n°4 – Le novità d’autunno: circolari, adempimenti e scadenze autunnali per scuole e Dirigenti scolastici. Abbonati o acquistala</a></h2> </article>\n<a href=\"https://plus.orizzontescuola.it/\">Scopri tutti i contenuti PLUS</a>\n</div>\n<div>\n<a href=\"https://www.orizzontescuola.it/content/newsletter\">\n<span>Iscriviti alla newsletter di OrizzonteScuola</span>\n<p>Ricevi ogni sera nella tua casella di posta una e-mail con tutti gli aggiornamenti del network di\norizzontescuola.it</p>\n</a>\n</div>\n<footer>\n<div>\n<span>\nPubblicato in <a href=\"https://www.orizzontescuola.it/ata/\">ATA</a> </span>\n</div>\n</footer>\n<div>\n<div></div>\n<div></div>\n<div></div>\n<div></div>\n</div>\n<div></div>\n<div></div>\n<div></div>\n</div>\n</div>";
