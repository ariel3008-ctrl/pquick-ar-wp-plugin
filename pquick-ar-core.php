<?php
/**
 * Plugin Name: Pquick AR Core
 * Description: מערכת הליבה לניהול אירועי Pquick AR. כולל יצירת אירועים, מחולל QR, העלאת מסגרות ו-API, וממשקי Frontend.
 * Version: 3.0.0
 * Author: Pquick AR Expert
 * Text Domain: pquick-ar
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Pquick_AR_Core {

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpts' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_custom_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
        
        // חטיפת הניתובים להצגת ממשקי האורח והמפעיל ללא התבנית של האתר
        add_action( 'template_redirect', array( $this, 'render_frontend_apps' ) );
    }

    public function enqueue_admin_scripts( $hook ) {
        global $typenow;
        if ( $typenow == 'pquick_event' ) {
            wp_enqueue_media();
        }
    }

    public function register_cpts() {
        register_post_type( 'pquick_event', array(
            'labels' => array( 'name' => 'אירועי Pquick', 'singular_name' => 'אירוע', 'add_new' => 'הוסף אירוע חדש', 'edit_item' => 'ערוך אירוע' ),
            'public' => true, 'has_archive' => false, 'menu_icon' => 'dashicons-camera', 'supports' => array( 'title' ),
        ));
        register_post_type( 'pquick_media', array(
            'labels' => array( 'name' => 'מדיות אורחים', 'singular_name' => 'מדיה' ),
            'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-format-image',
            'supports' => array( 'title', 'author' ), 'show_in_menu' => 'edit.php?post_type=pquick_event',
        ));
    }

    public function add_custom_meta_boxes() {
        add_meta_box( 'pquick_event_settings', 'הגדרות אירוע ועיצוב', array( $this, 'render_event_settings_box' ), 'pquick_event', 'normal', 'high' );
        add_meta_box( 'pquick_event_qr_operator', 'קישורים וקוד QR (לשיתוף)', array( $this, 'render_event_qr_box' ), 'pquick_event', 'side', 'high' );
    }

    public function render_event_settings_box( $post ) {
        wp_nonce_field( 'pquick_save_meta', 'pquick_meta_nonce' );
        $crop_format = get_post_meta( $post->ID, '_pquick_crop_format', true ) ?: 'rectangle';
        $max_copies = get_post_meta( $post->ID, '_pquick_max_copies', true ) ?: '3';
        $overlay_url = get_post_meta( $post->ID, '_pquick_overlay_url', true );
        ?>
        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 10px;">
            <div style="flex: 1; min-width: 250px;">
                <p>
                    <label for="pquick_crop_format"><strong>פורמט חיתוך תמונה:</strong></label><br>
                    <select name="pquick_crop_format" id="pquick_crop_format" style="width: 100%; margin-top: 5px;">
                        <option value="rectangle" <?php selected( $crop_format, 'rectangle' ); ?>>מלבן (3:4) - קלאסי</option>
                        <option value="square" <?php selected( $crop_format, 'square' ); ?>>ריבוע (1:1) - אינסטגרם</option>
                    </select>
                </p>
                <p style="margin-top: 20px;">
                    <label for="pquick_max_copies"><strong>מקסימום העתקים למשתמש:</strong></label><br>
                    <input type="number" name="pquick_max_copies" id="pquick_max_copies" value="<?php echo esc_attr( $max_copies ); ?>" min="1" max="10" style="width: 100%; margin-top: 5px;">
                </p>
            </div>
            <div style="flex: 1; min-width: 250px; border-right: 1px solid #ccc; padding-right: 20px;">
                <label><strong>מסגרת ממותגת (Overlay - PNG שקוף):</strong></label><br>
                <div style="margin-top: 10px; border: 2px dashed #ccc; padding: 10px; text-align: center; background: #f9f9f9;">
                    <img id="pquick_overlay_preview" src="<?php echo esc_url($overlay_url); ?>" style="max-width: 100%; max-height: 150px; display: <?php echo $overlay_url ? 'block' : 'none'; ?>; margin: 0 auto 10px;">
                    <input type="hidden" name="pquick_overlay_url" id="pquick_overlay_url" value="<?php echo esc_url($overlay_url); ?>">
                    <button type="button" class="button" id="pquick_upload_overlay_btn">בחר / העלה מסגרת</button>
                    <button type="button" class="button" id="pquick_remove_overlay_btn" style="color: red; display: <?php echo $overlay_url ? 'inline-block' : 'none'; ?>;">הסר</button>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($){
            var mediaUploader;
            $('#pquick_upload_overlay_btn').click(function(e) {
                e.preventDefault();
                if (mediaUploader) { mediaUploader.open(); return; }
                mediaUploader = wp.media.frames.file_frame = wp.media({ title: 'בחר מסגרת', button: { text: 'בחר' }, multiple: false });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#pquick_overlay_url').val(attachment.url);
                    $('#pquick_overlay_preview').attr('src', attachment.url).show();
                    $('#pquick_remove_overlay_btn').show();
                });
                mediaUploader.open();
            });
            $('#pquick_remove_overlay_btn').click(function(e){
                e.preventDefault(); $('#pquick_overlay_url').val(''); $('#pquick_overlay_preview').hide(); $(this).hide();
            });
        });
        </script>
        <?php
    }

    public function render_event_qr_box( $post ) {
        if ( $post->post_status == 'auto-draft' || $post->post_status == 'draft' ) {
            echo '<p style="color: #d63638;">יש לשמור/לפרסם את האירוע כדי לחולל קוד QR וקישורים.</p>'; return;
        }

        // שינוי קריטי: הקישורים כעת משתמשים בפרמטר ?pquick_app= כדי למנוע שגיאות 404
        $site_url = get_site_url();
        $guest_url = add_query_arg( array( 'pquick_app' => 'upload', 'event_id' => $post->ID ), $site_url );
        $operator_url = add_query_arg( array( 'pquick_app' => 'operator', 'event_id' => $post->ID ), $site_url );
        $qr_api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($guest_url);
        ?>
        <div style="text-align: center;">
            <p><strong>קוד QR לאורחים (לסריקה בשולחנות):</strong></p>
            <img src="<?php echo esc_url($qr_api_url); ?>" alt="QR Code" style="width: 100%; max-width: 200px; border: 1px solid #ccc; padding: 5px; border-radius: 8px;">
            <p style="margin-top: 10px;">
                <a href="<?php echo esc_url($qr_api_url); ?>" download="Event_<?php echo $post->ID; ?>_QR.png" class="button button-primary">הורד קוד QR להדפסה</a>
            </p>
            <hr style="margin: 20px 0;">
            <p><strong>קישור לעמדת המפעיל (Dashboard):</strong></p>
            <p style="font-size: 11px; background: #f0f0f1; padding: 5px; word-break: break-all; border-radius: 3px;"><?php echo esc_html($operator_url); ?></p>
            <p>
                <a href="<?php echo esc_url($operator_url); ?>" target="_blank" class="button">פתח לוח מפעיל</a>
            </p>
        </div>
        <?php
    }

    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['pquick_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pquick_meta_nonce'], 'pquick_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( isset( $_POST['pquick_crop_format'] ) ) update_post_meta( $post_id, '_pquick_crop_format', sanitize_text_field( $_POST['pquick_crop_format'] ) );
        if ( isset( $_POST['pquick_max_copies'] ) ) update_post_meta( $post_id, '_pquick_max_copies', intval( $_POST['pquick_max_copies'] ) );
        if ( isset( $_POST['pquick_overlay_url'] ) ) update_post_meta( $post_id, '_pquick_overlay_url', esc_url_raw( $_POST['pquick_overlay_url'] ) );
    }

    // --- API Endpoints ---
    public function register_rest_endpoints() {
        register_rest_route( 'pquick/v1', '/upload', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'handle_guest_upload' ), 'permission_callback' => '__return_true' ) );
        register_rest_route( 'pquick/v1', '/event-media/(?P<event_id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_event_media' ), 'permission_callback' => '__return_true' ) );
        // נתיב לעדכון סטטוס הדפסה (מפעיל)
        register_rest_route( 'pquick/v1', '/print/(?P<media_id>\d+)', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'update_print_status' ), 'permission_callback' => '__return_true' ) );
    }

    public function handle_guest_upload( WP_REST_Request $request ) {
        $event_id = $request->get_param( 'event_id' );
        $copies = $request->get_param( 'copies' ) ? intval( $request->get_param( 'copies' ) ) : 1;
        $image_base64 = $request->get_param( 'image_base64' ); 
        
        if ( ! $event_id || ! $image_base64 ) return new WP_Error( 'missing_data', 'חסרים נתונים חיוניים', array( 'status' => 400 ) );

        $image_parts = explode(";base64,", $image_base64);
        $image_base64 = base64_decode($image_parts[1]);
        $image_name = 'pquick_evt_' . $event_id . '_' . time() . '.jpg';
        $upload_dir = wp_upload_dir();
        $image_path = $upload_dir['path'] . '/' . $image_name;
        
        file_put_contents($image_path, $image_base64);
        $image_url = $upload_dir['url'] . '/' . $image_name;

        // שמירת הוידאו אם נשלח (FormData)
        $video_file = $request->get_file_params()['video_file'] ?? null;
        $video_url = '';
        if ( $video_file && $video_file['error'] === UPLOAD_ERR_OK ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            $movefile = wp_handle_upload( $video_file, array( 'test_form' => false ) );
            if ( $movefile && ! isset( $movefile['error'] ) ) { $video_url = $movefile['url']; }
        }

        $post_id = wp_insert_post( array( 'post_type' => 'pquick_media', 'post_title' => 'Upload #' . time(), 'post_status' => 'publish' ) );

        update_post_meta( $post_id, '_pquick_parent_event', $event_id );
        update_post_meta( $post_id, '_pquick_image_url', $image_url );
        update_post_meta( $post_id, '_pquick_video_url', $video_url );
        update_post_meta( $post_id, '_pquick_copies', $copies );
        update_post_meta( $post_id, '_pquick_print_status', 'pending' );

        return rest_ensure_response( array( 'success' => true, 'media_id' => $post_id ) );
    }

    public function get_event_media( WP_REST_Request $request ) {
        $event_id = $request->get_param( 'event_id' );
        $args = array( 'post_type' => 'pquick_media', 'posts_per_page' => 100, 'meta_query' => array( array( 'key' => '_pquick_parent_event', 'value' => $event_id, 'compare' => '=' ) ), 'orderby' => 'date', 'order' => 'DESC' );
        $query = new WP_Query( $args );
        $media_list = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $media_list[] = array(
                    'id' => $post_id,
                    'time' => get_the_date('H:i'),
                    'image' => get_post_meta( $post_id, '_pquick_image_url', true ),
                    'hasVideo' => !empty(get_post_meta( $post_id, '_pquick_video_url', true )),
                    'copies' => get_post_meta( $post_id, '_pquick_copies', true ),
                    'status' => get_post_meta( $post_id, '_pquick_print_status', true ),
                );
            }
            wp_reset_postdata();
        }
        return rest_ensure_response( $media_list );
    }

    public function update_print_status( WP_REST_Request $request ) {
        $media_id = $request->get_param( 'media_id' );
        if(get_post_type($media_id) === 'pquick_media') {
            update_post_meta( $media_id, '_pquick_print_status', 'printed' );
        }
        return rest_ensure_response( array( 'success' => true ) );
    }

    // --- שילוב ה-Frontend ---
    public function render_frontend_apps() {
        if ( ! isset( $_GET['pquick_app'] ) ) return;
        
        $app = sanitize_text_field( $_GET['pquick_app'] );
        $event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : 0;
        
        if ( ! $event_id || get_post_type( $event_id ) !== 'pquick_event' ) {
            wp_die( 'אירוע לא חוקי או לא קיים.', 'שגיאה', array( 'response' => 404 ) );
        }

        if ( $app === 'upload' ) {
            $this->output_guest_app( $event_id ); exit;
        } elseif ( $app === 'operator' ) {
            $this->output_operator_app( $event_id ); exit;
        }
    }

    private function output_guest_app( $event_id ) {
        $event_name = get_the_title($event_id);
        $max_copies = get_post_meta($event_id, '_pquick_max_copies', true) ?: 3;
        $crop_format = get_post_meta($event_id, '_pquick_crop_format', true) ?: 'rectangle';
        $overlay_url = get_post_meta($event_id, '_pquick_overlay_url', true);
        ?>
        <!DOCTYPE html>
        <html lang="he" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Pquick AR - העלאת זכרונות</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Alef:wght@400;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
            <script>
                tailwind.config = { theme: { extend: { colors: { pquick: { dark: '#454857', orange: '#ffb800', lightgreen: '#9ad7cf', pink: '#ff7a7b', light: '#f9f9f9' } }, fontFamily: { sans: ['Alef', 'sans-serif'] } } } }
            </script>
            <style>
                body { background-color: #f5f5f5; -webkit-tap-highlight-color: transparent; }
                .app-container { max-width: 480px; margin: 0 auto; min-height: 100vh; background: white; box-shadow: 0 0 20px rgba(0,0,0,0.05); display: flex; flex-direction: column; }
                .step { display: none; animation: fadeIn 0.4s ease-out; }
                .step.active { display: flex; flex-direction: column; flex: 1; }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .file-upload-wrapper { position: relative; overflow: hidden; display: inline-block; width: 100%; }
                .file-upload-wrapper input[type="file"] { font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; height: 100%; }
                .preview-frame { position: relative; width: 100%; aspect-ratio: 3/4; background-color: #eee; overflow: hidden; box-shadow: 0 4px 15px rgba(69, 72, 87, 0.15); }
                .preview-image { width: 100%; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 1; }
                .crop-container { width: 100%; height: 60vh; background-color: #000; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
                .preview-overlay-dynamic { width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: 2; pointer-events: none; object-fit: cover; }
                @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
                .btn-magic-contact { background: linear-gradient(270deg, #ffb800, #ff7a7b, #9ad7cf, #ffb800); background-size: 300% 300%; animation: gradientShift 4s ease infinite; color: white; text-shadow: 0px 1px 2px rgba(0,0,0,0.2); }
                .loader { border: 4px solid #f3f3f3; border-top: 4px solid #ffb800; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto; }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            </style>
        </head>
        <body class="text-pquick-dark">
        <div class="app-container relative">
            <header class="p-4 flex justify-center items-center border-b border-gray-100 sticky top-0 bg-white z-10">
                <div class="flex-col items-center leading-none flex">
                    <span class="text-3xl font-bold text-pquick-dark">Pquick</span>
                    <span class="text-lg font-bold text-pquick-orange">Events</span>
                </div>
            </header>
            <main class="flex-1 p-5 flex flex-col">
                <div id="step-upload" class="step active">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-bold mb-2">ברוכים הבאים ל<span id="welcome-event-name" class="text-pquick-orange">אירוע</span>!</h1>
                        <p class="text-gray-500 text-sm">בחרו תמונה להדפסה וסרטון שיקים אותה לחיים בעזרת קסם ה-AR שלנו.</p>
                    </div>
                    <div class="space-y-6">
                        <div class="file-upload-wrapper">
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center transition-colors hover:border-pquick-pink bg-pquick-light" id="image-dropzone">
                                <div class="text-pquick-pink text-4xl mb-3"><i class="fa-regular fa-image"></i></div>
                                <h3 class="font-bold">1. תמונה להדפסה</h3>
                                <p class="text-xs text-gray-500 mt-1">לחצו לבחירה או צלמו עכשיו</p>
                                <p id="image-filename" class="text-sm font-bold text-pquick-lightgreen mt-2 hidden"><i class="fa-solid fa-check"></i> התמונה נחתכה ונשמרה</p>
                            </div>
                            <input type="file" id="input-image" accept="image/*">
                        </div>
                        <div class="file-upload-wrapper">
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center transition-colors hover:border-pquick-pink bg-pquick-light opacity-50 transition-opacity" id="video-dropzone">
                                <div class="text-pquick-pink text-4xl mb-3"><i class="fa-solid fa-video"></i></div>
                                <h3 class="font-bold">2. סרטון ההפתעה (AR)</h3>
                                <p class="text-xs text-gray-500 mt-1">עד 15 שניות של קסם</p>
                                <p id="video-filename" class="text-sm font-bold text-pquick-lightgreen mt-2 hidden"><i class="fa-solid fa-check"></i> נבחר סרטון</p>
                            </div>
                            <input type="file" id="input-video" accept="video/*" disabled>
                        </div>
                    </div>
                    <div class="mt-auto pt-8">
                        <button id="btn-to-preview" disabled class="w-full bg-gray-200 text-gray-400 font-bold py-4 rounded-full text-lg transition-colors shadow-md disabled:cursor-not-allowed">
                            המשך לתצוגה מקדימה <i class="fa-solid fa-arrow-left ml-2"></i>
                        </button>
                    </div>
                </div>

                <div id="step-crop" class="step">
                    <div class="text-center mb-4"><h2 class="text-xl font-bold">התאמת תמונה</h2><p class="text-gray-500 text-sm">הזיזו או עשו זום להתאמה מושלמת למסגרת ההדפסה</p></div>
                    <div class="crop-container"><img id="image-to-crop" src="" style="max-width: 100%; display: block;"></div>
                    <div class="flex gap-3 mt-auto">
                        <button id="btn-cancel-crop" class="flex-1 bg-white border-2 border-gray-200 text-pquick-dark font-bold py-3 rounded-full transition-colors hover:bg-gray-50">ביטול</button>
                        <button id="btn-save-crop" class="flex-[2] bg-pquick-orange text-pquick-dark font-bold py-3 rounded-full shadow-lg hover:opacity-90 transition-opacity">חתוך ושמור <i class="fa-solid fa-crop-simple ml-2"></i></button>
                    </div>
                </div>

                <div id="step-preview" class="step">
                    <div class="text-center mb-4"><h2 class="text-xl font-bold">ככה זה ייראה באירוע!</h2></div>
                    <div class="preview-frame mx-auto mb-4">
                        <img id="preview-img-element" class="preview-image" src="">
                        <img id="preview-overlay-element" class="preview-overlay-dynamic" src="">
                    </div>
                    <div class="bg-pquick-light rounded-xl p-3 mb-2 border border-gray-200 flex justify-between items-center shadow-sm">
                        <span class="font-bold text-gray-700 text-sm">כמות העתקים להדפסה:</span>
                        <div class="flex items-center gap-3">
                            <button id="btn-qty-minus" class="w-8 h-8 rounded-full bg-white border border-gray-300 flex items-center justify-center hover:bg-gray-50"><i class="fa-solid fa-minus"></i></button>
                            <span id="print-qty-display" class="font-bold text-xl w-6 text-center">1</span>
                            <button id="btn-qty-plus" class="w-8 h-8 rounded-full bg-white border border-gray-300 flex items-center justify-center hover:bg-gray-50"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                    <p id="qty-limit-msg" class="text-xs text-pquick-pink text-center mb-4 hidden font-bold">ניתן להדפיס עד <span id="max-qty-num"></span> העתקים.</p>
                    <div class="flex gap-3 mt-auto">
                        <button id="btn-back" class="flex-1 bg-white border-2 border-gray-200 text-pquick-dark font-bold py-3 rounded-full hover:bg-gray-50">חזור</button>
                        <button id="btn-submit" class="flex-[2] bg-pquick-orange text-pquick-dark font-bold py-3 rounded-full shadow-lg hover:opacity-90">הדפס אותי! <i class="fa-solid fa-print ml-2"></i></button>
                    </div>
                </div>

                <div id="step-loading" class="step items-center justify-center">
                    <div class="text-center"><div class="loader mb-4"></div><h2 class="text-xl font-bold">מעבד קסמים...</h2><p class="text-gray-500">מעלה תמונה ווידאו לשרת Pquick</p></div>
                </div>

                <div id="step-success" class="step items-center justify-center text-center">
                    <div class="w-24 h-24 bg-pquick-lightgreen rounded-full flex items-center justify-center mx-auto mb-6 text-pquick-dark text-4xl shadow-md"><i class="fa-solid fa-check"></i></div>
                    <h1 class="text-3xl font-bold mb-2">מושלם!</h1>
                    <p class="text-gray-600 mb-8 leading-relaxed">התמונה נשלחה בהצלחה לעמדת ההדפסה.</p>
                    <button onclick="location.reload()" class="text-pquick-dark font-bold underline hover:text-pquick-pink transition-colors mb-8">העלה תמונה נוספת</button>
                    <div class="mt-2 w-full flex flex-col items-center">
                        <p class="text-gray-500 text-sm mb-2 font-medium">רוצים את הקסם הזה גם באירוע שלכם?</p>
                        <a href="https://pquick.co.il/events/" target="_blank" class="btn-magic-contact inline-flex items-center justify-center font-bold py-2.5 px-8 rounded-full text-sm transition-transform hover:scale-105 shadow-lg"><i class="fa-solid fa-wand-magic-sparkles ml-2"></i> לפרטים והזמנות</a>
                    </div>
                </div>
            </main>
        </div>

        <script>
            // הזרקת נתונים דינמיים מוורדפרס ל-JS
            const EVENT_DATA = {
                id: <?php echo intval($event_id); ?>,
                name: "<?php echo esc_js($event_name); ?>",
                maxCopies: <?php echo intval($max_copies); ?>,
                cropFormat: "<?php echo esc_js($crop_format); ?>",
                overlayUrl: "<?php echo esc_js($overlay_url); ?>"
            };

            const isSquare = EVENT_DATA.cropFormat === "square";
            
            // מנגנון גיבוי אם אין מסגרת מוגדרת בוורדפרס
            if (!EVENT_DATA.overlayUrl) {
                const svgWidth = 600; const svgHeight = 800;
                let holePath = isSquare ? 'M30 30v540h540V30z' : 'M30 30v640h540V30z';
                EVENT_DATA.overlayUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${svgWidth} ${svgHeight}"><path d="M0 0h${svgWidth}v${svgHeight}H0z ${holePath}" fill="#ffffff" fill-rule="evenodd"/><text x="${svgWidth/2}" y="${isSquare ? 650 : 720}" font-family="Alef, sans-serif" font-size="${isSquare ? '40' : '34'}" fill="#ffb800" font-weight="bold" text-anchor="middle">${EVENT_DATA.name}</text><text x="${svgWidth/2}" y="${isSquare ? 720 : 760}" font-family="Alef, sans-serif" font-size="${isSquare ? '24' : '20'}" fill="#454857" font-weight="bold" text-anchor="middle">סרקו אותי עם המצלמה!</text></svg>`);
            }

            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('welcome-event-name').textContent = EVENT_DATA.name;
                document.getElementById('preview-overlay-element').src = EVENT_DATA.overlayUrl;
                document.getElementById('max-qty-num').textContent = EVENT_DATA.maxCopies;
                
                const previewImgElement = document.getElementById('preview-img-element');
                if(previewImgElement) previewImgElement.style.height = isSquare ? '75%' : '100%';

                const inputImage = document.getElementById('input-image');
                const inputVideo = document.getElementById('input-video');
                const imageToCropElement = document.getElementById('image-to-crop');
                
                let cropper = null;
                let croppedImageDataUrl = null;
                let currentQty = 1;

                const updateQtyUI = () => {
                    document.getElementById('print-qty-display').textContent = currentQty;
                    document.getElementById('btn-qty-minus').disabled = currentQty <= 1;
                    if (currentQty >= EVENT_DATA.maxCopies) {
                        document.getElementById('btn-qty-plus').disabled = true;
                        document.getElementById('qty-limit-msg').classList.remove('hidden');
                    } else {
                        document.getElementById('btn-qty-plus').disabled = false;
                        document.getElementById('qty-limit-msg').classList.add('hidden');
                    }
                };

                document.getElementById('btn-qty-minus').addEventListener('click', () => { if (currentQty > 1) { currentQty--; updateQtyUI(); } });
                document.getElementById('btn-qty-plus').addEventListener('click', () => { if (currentQty < EVENT_DATA.maxCopies) { currentQty++; updateQtyUI(); } });
                updateQtyUI();

                let hasImage = false, hasVideo = false;

                const showStep = (stepId) => {
                    document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
                    document.getElementById(stepId).classList.add('active');
                };

                const checkFormValidity = () => {
                    const btn = document.getElementById('btn-to-preview');
                    if (hasImage && hasVideo) {
                        btn.disabled = false; btn.className = 'w-full bg-pquick-orange text-pquick-dark font-bold py-4 rounded-full text-lg shadow-md transition-colors';
                    } else {
                        btn.disabled = true; btn.className = 'w-full bg-gray-200 text-gray-400 font-bold py-4 rounded-full text-lg transition-colors cursor-not-allowed';
                    }
                };

                inputImage.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            imageToCropElement.src = event.target.result;
                            showStep('step-crop');
                            if (cropper) cropper.destroy();
                            cropper = new Cropper(imageToCropElement, {
                                aspectRatio: isSquare ? 1 : 3 / 4, viewMode: 1, dragMode: 'move', autoCropArea: 1, guides: true, center: true, highlight: false, cropBoxMovable: false, cropBoxResizable: false, toggleDragModeOnDblclick: false
                            });
                        }
                        reader.readAsDataURL(this.files[0]);
                    }
                });

                document.getElementById('btn-cancel-crop').addEventListener('click', () => {
                    if (cropper) cropper.destroy(); inputImage.value = ''; showStep('step-upload');
                });

                document.getElementById('btn-save-crop').addEventListener('click', () => {
                    if (!cropper) return;
                    const canvas = cropper.getCroppedCanvas({ width: 1200, height: isSquare ? 1200 : 1600 });
                    croppedImageDataUrl = canvas.toDataURL('image/jpeg', 0.85);
                    previewImgElement.src = croppedImageDataUrl;
                    
                    hasImage = true;
                    document.getElementById('image-filename').classList.remove('hidden');
                    document.getElementById('image-dropzone').classList.replace('border-gray-300', 'border-pquick-lightgreen');
                    
                    inputVideo.disabled = false;
                    document.getElementById('video-dropzone').classList.remove('opacity-50');
                    if (cropper) cropper.destroy();
                    checkFormValidity(); showStep('step-upload');
                });

                inputVideo.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        hasVideo = true;
                        document.getElementById('video-filename').classList.remove('hidden');
                        document.getElementById('video-dropzone').classList.replace('border-gray-300', 'border-pquick-lightgreen');
                    }
                    checkFormValidity();
                });

                document.getElementById('btn-to-preview').addEventListener('click', () => { if (hasImage && hasVideo) showStep('step-preview'); });
                document.getElementById('btn-back').addEventListener('click', () => showStep('step-upload'));

                // השליחה בפועל לוורדפרס!
                document.getElementById('btn-submit').addEventListener('click', () => {
                    showStep('step-loading');
                    
                    const formData = new FormData();
                    formData.append('event_id', EVENT_DATA.id);
                    formData.append('copies', currentQty);
                    formData.append('image_base64', croppedImageDataUrl);
                    if (inputVideo.files && inputVideo.files[0]) {
                        formData.append('video_file', inputVideo.files[0]);
                    }

                    fetch('/wp-json/pquick/v1/upload', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            showStep('step-success');
                            if(typeof confetti !== 'undefined') confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 }, colors: ['#ffb800', '#9ad7cf', '#ff7a7b', '#454857'], zIndex: 100 });
                        } else {
                            alert('שגיאה: ' + (data.message || 'לא הצלחנו לשמור, נסה שוב.'));
                            showStep('step-preview');
                        }
                    }).catch(err => { console.error(err); alert('שגיאת תקשורת, בדוק חיבור לאינטרנט.'); showStep('step-preview'); });
                });
            });
        </script>
        </body>
        </html>
        <?php
    }

    private function output_operator_app( $event_id ) {
        $event_name = get_the_title($event_id);
        ?>
        <!DOCTYPE html>
        <html lang="he" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Pquick AR - לוח מפעיל אירוע</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Alef:wght@400;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <script>tailwind.config = { theme: { extend: { colors: { pquick: { dark: '#454857', orange: '#ffb800', lightgreen: '#9ad7cf', pink: '#ff7a7b' } }, fontFamily: { sans: ['Alef', 'sans-serif'] } } } }</script>
            <style>
                body { background-color: #f0f2f5; }
                ::-webkit-scrollbar { width: 8px; } ::-webkit-scrollbar-track { background: #f1f1f1; } ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
                @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
                .item-animate { animation: slideIn 0.3s ease-out forwards; }
            </style>
        </head>
        <body class="text-pquick-dark h-screen flex flex-col overflow-hidden">
            <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4 flex justify-between items-center shrink-0 z-10">
                <div class="flex items-center gap-4">
                    <span class="text-2xl font-bold text-pquick-dark" style="font-family: 'Alef', sans-serif;">Pquick<span class="text-pquick-orange">Events</span></span>
                    <div class="h-8 w-px bg-gray-300"></div>
                    <div>
                        <h1 class="text-xl font-bold leading-none"><?php echo esc_html($event_name); ?></h1>
                        <span class="text-sm text-green-500 font-bold flex items-center gap-1"><span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span> מחובר לשרת</span>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="text-center"><div class="text-sm text-gray-500">סה"כ העלאות</div><div class="text-xl font-bold" id="stat-total">0</div></div>
                    <div class="text-center"><div class="text-sm text-pquick-pink font-bold">ממתין להדפסה</div><div class="text-xl font-bold text-pquick-pink" id="stat-pending">0</div></div>
                    <div class="text-center"><div class="text-sm text-pquick-lightgreen font-bold">הודפס</div><div class="text-xl font-bold text-pquick-lightgreen" id="stat-printed">0</div></div>
                </div>
            </header>
            <div class="bg-white px-6 py-3 border-b border-gray-200 flex justify-between items-center shrink-0">
                <div class="flex gap-2">
                    <button id="btn-view-grid" class="view-btn active w-10 h-10 rounded-md bg-pquick-dark text-white shadow-inner"><i class="fa-solid fa-border-all text-lg"></i></button>
                    <button id="btn-view-list" class="view-btn w-10 h-10 rounded-md bg-white border border-gray-300 text-gray-600 hover:bg-gray-50"><i class="fa-solid fa-list text-lg"></i></button>
                </div>
                <div><input type="text" id="search-input" placeholder="חיפוש לפי ID..." class="pl-4 pr-4 py-2 border border-gray-300 rounded-full text-sm w-64"></div>
            </div>
            <main class="flex-1 overflow-y-auto p-6" id="main-content-area"><div id="content-container">טוען נתונים מהשרת...</div></main>

        <script>
            const EVENT_ID = <?php echo intval($event_id); ?>;
            let uploadsData = [];
            let currentView = 'grid';
            
            async function fetchMedia() {
                try {
                    const res = await fetch('/wp-json/pquick/v1/event-media/' + EVENT_ID);
                    uploadsData = await res.json();
                    render();
                } catch(e) { console.error("API error", e); }
            }

            // משיכת נתונים ראשונית וכל 5 שניות
            fetchMedia();
            setInterval(fetchMedia, 5000);

            function updateStats() {
                document.getElementById('stat-total').textContent = uploadsData.length;
                document.getElementById('stat-pending').textContent = uploadsData.filter(i => i.status === 'pending').length;
                document.getElementById('stat-printed').textContent = uploadsData.filter(i => i.status === 'printed').length;
            }

            function getStatusBadge(status) {
                if (status === 'pending') return '<span class="bg-pquick-pink text-white text-xs font-bold px-2 py-1 rounded-full"><i class="fa-solid fa-clock mr-1"></i> ממתין</span>';
                if (status === 'printed') return '<span class="bg-pquick-lightgreen text-pquick-dark text-xs font-bold px-2 py-1 rounded-full"><i class="fa-solid fa-check mr-1"></i> הודפס</span>';
                return '';
            }

            function render() {
                const container = document.getElementById('content-container');
                if(uploadsData.length === 0) { container.innerHTML = '<p class="text-center text-gray-500 mt-10">אין תמונות עדיין. ממתין לאורחים...</p>'; return; }
                
                let html = '';
                if (currentView === 'grid') {
                    container.className = 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6';
                    uploadsData.forEach(item => {
                        const isPending = item.status === 'pending';
                        html += `
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col item-animate relative group">
                                <div class="absolute top-2 right-2 bg-pquick-dark text-white font-bold w-8 h-8 rounded-full flex items-center justify-center shadow-md z-10 border-2 border-white">${item.copies}x</div>
                                ${item.hasVideo ? `<div class="absolute top-2 left-2 bg-pquick-orange text-pquick-dark w-8 h-8 rounded-full flex items-center justify-center shadow-md z-10"><i class="fa-solid fa-video"></i></div>` : ''}
                                <div class="aspect-[3/4] relative bg-gray-100 overflow-hidden">
                                    <img src="${item.image}" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <button class="bg-white text-pquick-dark font-bold py-2 px-4 rounded-full shadow-lg hover:bg-pquick-orange transition-colors" onclick="printItem('${item.id}', '${item.image}')">
                                            <i class="fa-solid fa-print ml-2"></i> ${isPending ? 'הדפס עכשיו' : 'הדפס שוב'}
                                        </button>
                                    </div>
                                </div>
                                <div class="p-4 flex flex-col gap-3">
                                    <div class="flex justify-between items-center"><span class="text-sm text-gray-500 font-bold">#${item.id}</span><span class="text-xs text-gray-400"><i class="fa-regular fa-clock"></i> ${item.time}</span></div>
                                    <div class="flex justify-between items-center">${getStatusBadge(item.status)}</div>
                                </div>
                            </div>`;
                    });
                } else {
                    container.className = 'bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden';
                    html = `<table class="w-full text-right border-collapse"><thead><tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-sm"><th class="p-4">תצוגה</th><th class="p-4">ID</th><th class="p-4">שעה</th><th class="p-4 text-center">וידאו</th><th class="p-4 text-center">העתקים</th><th class="p-4">סטטוס</th><th class="p-4 text-left">פעולה</th></tr></thead><tbody>`;
                    uploadsData.forEach(item => {
                        const isPending = item.status === 'pending';
                        html += `
                            <tr class="border-b border-gray-100 hover:bg-gray-50 item-animate">
                                <td class="p-4"><img src="${item.image}" class="w-12 h-16 object-cover rounded-md border border-gray-200"></td>
                                <td class="p-4 font-bold">#${item.id}</td><td class="p-4 text-gray-500">${item.time}</td>
                                <td class="p-4 text-center">${item.hasVideo ? '<i class="fa-solid fa-video text-pquick-orange"></i>' : '-'}</td>
                                <td class="p-4 text-center"><span class="bg-gray-100 font-bold px-3 py-1 rounded-full">${item.copies}</span></td>
                                <td class="p-4">${getStatusBadge(item.status)}</td>
                                <td class="p-4 text-left"><button onclick="printItem('${item.id}', '${item.image}')" class="${isPending ? 'bg-pquick-orange text-pquick-dark' : 'bg-white border text-gray-600'} font-bold py-2 px-4 rounded-md shadow-sm">הדפס</button></td>
                            </tr>`;
                    });
                    html += `</tbody></table>`;
                }
                container.innerHTML = html;
                updateStats();
            }

            window.printItem = async function(id, imageUrl) {
                // במערכת אמיתית פה תיפתח תיבת ההדפסה של הדפדפן לתמונה הזו
                window.open(imageUrl, '_blank');
                
                const itemIndex = uploadsData.findIndex(item => item.id == id);
                if (itemIndex > -1) {
                    uploadsData[itemIndex].status = 'printed';
                    render();
                    try { await fetch('/wp-json/pquick/v1/print/' + id, { method: 'POST' }); } catch(e) {}
                }
            };

            document.getElementById('btn-view-grid').addEventListener('click', (e) => { currentView = 'grid'; e.currentTarget.classList.add('active','bg-pquick-dark','text-white'); document.getElementById('btn-view-list').classList.remove('active','bg-pquick-dark','text-white'); render(); });
            document.getElementById('btn-view-list').addEventListener('click', (e) => { currentView = 'list'; e.currentTarget.classList.add('active','bg-pquick-dark','text-white'); document.getElementById('btn-view-grid').classList.remove('active','bg-pquick-dark','text-white'); render(); });
        </script>
        </body>
        </html>
        <?php
    }
}

new Pquick_AR_Core();