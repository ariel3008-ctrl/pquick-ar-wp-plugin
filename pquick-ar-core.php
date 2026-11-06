<?php
/**
 * Plugin Name: Pquick AR Core
 * Description: גרסה 21.0.0 - חוויית סריקה חדשה: התאמה מדויקת וידאו-הדפסה, מסך פתיחה, מצב Sticky, QR מקומי ואבטחת עמדת מפעיל.
 * Version: 21.0.0
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
        add_action( 'template_redirect', array( $this, 'render_frontend_apps' ) );
        
        // הגדרות מערכת ל-AWS
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_aws_settings' ) );
        
        // אישור העלאת קבצי SVG
        add_filter( 'upload_mimes', array( $this, 'allow_svg_uploads' ) );
    }

    public function add_admin_menu() {
        add_submenu_page( 'edit.php?post_type=pquick_event', 'הגדרות ענן (AWS)', 'הגדרות ענן (AWS)', 'manage_options', 'pquick-aws-settings', array( $this, 'render_aws_settings_page' ) );
    }

    public function register_aws_settings() {
        register_setting( 'pquick_aws_settings_group', 'pquick_aws_bucket' );
        register_setting( 'pquick_aws_settings_group', 'pquick_aws_region' );
        register_setting( 'pquick_aws_settings_group', 'pquick_aws_access_key' );
        register_setting( 'pquick_aws_settings_group', 'pquick_aws_secret_key' );
    }

    public function render_aws_settings_page() {
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-cloud" style="font-size: 28px; margin-top: 5px; color: #ffb800;"></span> הגדרות ענן Pquick AR (AWS S3)</h1>
            <p>הגרסה הסופית: תמונות וסרטונים עולים ל-S3. פונקציית Lambda באמזון דואגת לקמפל אותם ל-AR באופן אוטומטי.</p>
            <form method="post" action="options.php" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 600px;">
                <?php settings_fields( 'pquick_aws_settings_group' ); ?>
                <?php do_settings_sections( 'pquick_aws_settings_group' ); ?>
                <table class="form-table">
                    <tr valign="top">
                    <th scope="row">AWS Bucket Name</th>
                    <td><input type="text" name="pquick_aws_bucket" value="<?php echo esc_attr( get_option('pquick_aws_bucket') ); ?>" class="regular-text" placeholder="לדוגמה: pquick-ar-media-2026" /></td>
                    </tr>
                    <tr valign="top">
                    <th scope="row">AWS Region</th>
                    <td><input type="text" name="pquick_aws_region" value="<?php echo esc_attr( get_option('pquick_aws_region') ); ?>" class="regular-text" placeholder="לדוגמה: eu-central-1" /></td>
                    </tr>
                    <tr valign="top">
                    <th scope="row">Access Key ID</th>
                    <td><input type="text" name="pquick_aws_access_key" value="<?php echo esc_attr( get_option('pquick_aws_access_key') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                    <th scope="row">Secret Access Key</th>
                    <td><input type="password" name="pquick_aws_secret_key" value="<?php echo esc_attr( get_option('pquick_aws_secret_key') ); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button('שמור הגדרות ענן'); ?>
            </form>
        </div>
        <?php
    }

    public function allow_svg_uploads( $mimes ) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    public function enqueue_admin_scripts( $hook ) {
        global $post, $typenow;
        $type = $typenow;
        if (empty($type) && !empty($post)) { $type = $post->post_type; } elseif (empty($type) && isset($_GET['post_type'])) { $type = $_GET['post_type']; }
        if ( $type == 'pquick_event' ) { wp_enqueue_media(); }
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
        add_meta_box( 'pquick_event_settings', 'הגדרות אירוע, לוגו ומסגרת', array( $this, 'render_event_settings_box' ), 'pquick_event', 'normal', 'high' );
        add_meta_box( 'pquick_event_qr_operator', 'ניהול ו-QR לאירוע', array( $this, 'render_event_qr_box' ), 'pquick_event', 'side', 'high' );
    }

    public function render_event_settings_box( $post ) {
        wp_nonce_field( 'pquick_save_meta', 'pquick_meta_nonce' );
        
        $print_format = get_post_meta( $post->ID, '_pquick_print_format', true ) ?: '0.75'; 
        $max_copies = get_post_meta( $post->ID, '_pquick_max_copies', true ) ?: '3';
        $global_max_uploads = get_post_meta( $post->ID, '_pquick_global_max_uploads', true ) ?: '';
        
        $overlay_url = get_post_meta( $post->ID, '_pquick_overlay_url', true );
        $logo_url = get_post_meta( $post->ID, '_pquick_event_logo', true ); 
        
        $photo_w = get_post_meta( $post->ID, '_pquick_photo_w', true ) !== '' ? get_post_meta( $post->ID, '_pquick_photo_w', true ) : '100';
        $photo_h = get_post_meta( $post->ID, '_pquick_photo_h', true ) !== '' ? get_post_meta( $post->ID, '_pquick_photo_h', true ) : '100';
        $photo_t = get_post_meta( $post->ID, '_pquick_photo_t', true ) !== '' ? get_post_meta( $post->ID, '_pquick_photo_t', true ) : '0';
        $photo_l = get_post_meta( $post->ID, '_pquick_photo_l', true ) !== '' ? get_post_meta( $post->ID, '_pquick_photo_l', true ) : '0';
        ?>
        
        <div style="background: #fff; border: 1px solid #ccc; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <label><strong>לוגו לאירוע (אם לא תעלה לוגו, יוצג שם המותג "Pquick Events" בטקסט):</strong></label><br>
            <div style="margin-top: 10px;">
                <img id="pquick_logo_preview" src="<?php echo esc_url($logo_url); ?>" style="max-width: 200px; max-height: 80px; display: <?php echo $logo_url ? 'inline-block' : 'none'; ?>; margin-bottom: 10px; background:#f0f0f0; padding:10px; border-radius:4px;">
                <br>
                <input type="hidden" name="pquick_event_logo" id="pquick_event_logo" value="<?php echo esc_url($logo_url); ?>">
                <button type="button" class="button" id="pquick_upload_logo_btn">בחר לוגו (PNG/SVG)</button>
                <button type="button" class="button" id="pquick_remove_logo_btn" style="color: red; display: <?php echo $logo_url ? 'inline-block' : 'none'; ?>;">הסר לוגו</button>
            </div>
        </div>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 350px;">
                <label><strong>יחס חיתוך לתמונת האורח (Cropper):</strong></label><br>
                <span style="font-size: 12px; color: #666;">המסגרת תמיד תוצג כמלבן עומד. ההגדרה כאן קובעת רק את צורת החיתוך שתתלבש באזור השקוף.</span>
                <select name="pquick_print_format" id="pquick_print_format" style="width: 100%; margin-top: 5px; margin-bottom: 15px; padding: 8px;">
                    <option value="1" <?php selected( $print_format, '1' ); ?>>מצב 1: חיתוך ריבוע (1:1)</option>
                    <option value="0.75" <?php selected( $print_format, '0.75' ); ?>>מצב 2: חיתוך מלבן לאורך (3:4)</option>
                </select>

                <label><strong>מסגרת ממותגת (Overlay - קובץ PNG שקוף):</strong></label><br>
                <span style="font-size: 12px; color: #666;">המסגרת תוצג תמיד ב-100% ללא חיתוך ותשב מעל תמונת האורח.</span>
                <div style="margin-top: 10px; border: 2px dashed #ccc; padding: 15px; text-align: center; background: #f9f9f9; border-radius: 8px;">
                    <img id="pquick_overlay_preview" crossorigin="anonymous" src="<?php echo esc_url($overlay_url); ?>" style="max-width: 100%; max-height: 250px; display: <?php echo $overlay_url ? 'inline-block' : 'none'; ?>; margin: 0 auto 15px; background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAAXNSR0IArs4c6QAAADFJREFUOE9jZGBgEGHAA8wMTAwMQHkGBgZcBpKAEWoAxhUDBmEAAwMPEDcT0kQDmFwEAIDxDwLz2t8+AAAAAElFTkSuQmCC) repeat; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <br>
                    <input type="hidden" name="pquick_overlay_url" id="pquick_overlay_url" value="<?php echo esc_url($overlay_url); ?>">
                    <button type="button" class="button button-primary" id="pquick_upload_overlay_btn">בחר / העלה מסגרת</button>
                    <button type="button" class="button" id="pquick_remove_overlay_btn" style="color: red; display: <?php echo $overlay_url ? 'inline-block' : 'none'; ?>;">הסר</button>
                </div>
                
                <div style="background: #e6f7f4; padding: 10px; border-radius: 8px; margin-top: 10px; border: 1px solid #bce4dc;">
                    <p style="margin: 0; font-size: 12px; color: #007c6d;"><strong><span class="dashicons dashicons-visibility" style="font-size:16px;"></span> מיקום התמונה שזוהה אוטומטית במסגרת:</strong></p>
                    <div style="display: flex; gap: 10px; font-size: 11px; margin-top:5px; color:#555;">
                        <div>רוחב: <input type="text" readonly name="pquick_photo_w" id="pquick_photo_w" value="<?php echo esc_attr($photo_w); ?>" style="width: 50px; padding:0 4px; background:#f0f0f0; border:1px solid #ccc;">%</div>
                        <div>גובה: <input type="text" readonly name="pquick_photo_h" id="pquick_photo_h" value="<?php echo esc_attr($photo_h); ?>" style="width: 50px; padding:0 4px; background:#f0f0f0; border:1px solid #ccc;">%</div>
                        <div>עליון: <input type="text" readonly name="pquick_photo_t" id="pquick_photo_t" value="<?php echo esc_attr($photo_t); ?>" style="width: 50px; padding:0 4px; background:#f0f0f0; border:1px solid #ccc;">%</div>
                        <div>שמאל: <input type="text" readonly name="pquick_photo_l" id="pquick_photo_l" value="<?php echo esc_attr($photo_l); ?>" style="width: 50px; padding:0 4px; background:#f0f0f0; border:1px solid #ccc;">%</div>
                    </div>
                </div>
            </div>
            
            <div style="flex: 1; min-width: 250px; background: #fff; border: 1px solid #ccc; padding: 15px; border-radius: 8px;">
                <h4 style="margin-top:0;">הגבלות והעתקים</h4>
                <p>
                    <label for="pquick_max_copies"><strong>מקסימום העתקים למשתמש בודד:</strong></label><br>
                    <input type="number" name="pquick_max_copies" id="pquick_max_copies" value="<?php echo esc_attr( $max_copies ); ?>" min="1" max="10" style="width: 100%; margin-top: 5px;">
                </p>
                <hr style="margin: 20px 0;">
                <p>
                    <label for="pquick_global_max_uploads"><strong style="color:#d63638;">מכסת העלאות כוללת לאירוע:</strong></label><br>
                    <span style="font-size: 12px; color: #666;">לאחר כמות זו, המערכת תינעל. השאר ריק ללא הגבלה.</span><br>
                    <input type="number" name="pquick_global_max_uploads" id="pquick_global_max_uploads" value="<?php echo esc_attr( $global_max_uploads ); ?>" min="1" placeholder="לדוגמה: 300" style="width: 100%; margin-top: 5px;">
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            var logoUploader;
            $('#pquick_upload_logo_btn').click(function(e) {
                e.preventDefault();
                if (logoUploader) { logoUploader.open(); return; }
                logoUploader = wp.media.frames.file_frame = wp.media({ title: 'בחר לוגו לאירוע', button: { text: 'בחר' }, multiple: false });
                logoUploader.on('select', function() {
                    var attachment = logoUploader.state().get('selection').first().toJSON();
                    $('#pquick_event_logo').val(attachment.url);
                    $('#pquick_logo_preview').attr('src', attachment.url).show();
                    $('#pquick_remove_logo_btn').show();
                });
                logoUploader.open();
            });
            $('#pquick_remove_logo_btn').click(function(e){
                e.preventDefault(); $('#pquick_event_logo').val(''); $('#pquick_logo_preview').hide(); $(this).hide();
            });

            function calculateTransparentHole(imageUrl) {
                const img = new Image();
                img.crossOrigin = "Anonymous";
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    ctx.drawImage(img, 0, 0);
                    
                    try {
                        const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                        let top = canvas.height, bottom = 0, left = canvas.width, right = 0;
                        let foundTransparent = false;

                        for (let y = 0; y < canvas.height; y++) {
                            for (let x = 0; x < canvas.width; x++) {
                                if (imgData[(y * canvas.width + x) * 4 + 3] < 50) {
                                    if (y < top) top = y; if (y > bottom) bottom = y;
                                    if (x < left) left = x; if (x > right) right = x;
                                    foundTransparent = true;
                                }
                            }
                        }

                        if(foundTransparent) {
                            $('#pquick_photo_w').val( (((right - left) / canvas.width) * 100).toFixed(2) );
                            $('#pquick_photo_h').val( (((bottom - top) / canvas.height) * 100).toFixed(2) );
                            $('#pquick_photo_t').val( ((top / canvas.height) * 100).toFixed(2) );
                            $('#pquick_photo_l').val( ((left / canvas.width) * 100).toFixed(2) );
                        }
                    } catch (e) {
                        console.warn("לא ניתן לקרוא נתוני פיקסלים אוטומטית.");
                    }
                };
                img.src = imageUrl;
            }

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
                    calculateTransparentHole(attachment.url);
                });
                mediaUploader.open();
            });
            $('#pquick_remove_overlay_btn').click(function(e){
                e.preventDefault(); $('#pquick_overlay_url').val(''); $('#pquick_overlay_preview').hide(); $(this).hide();
                $('#pquick_photo_w').val('100'); $('#pquick_photo_h').val('100'); $('#pquick_photo_t').val('0'); $('#pquick_photo_l').val('0');
            });
        });
        </script>
        <?php
    }

    public function render_event_qr_box( $post ) {
        if ( $post->post_status == 'auto-draft' || $post->post_status == 'draft' ) {
            echo '<p style="color: #d63638;">יש לשמור את האירוע כדי ליצור קודי QR וקישורים.</p>'; return;
        }

        $site_url = get_site_url();
        $guest_url = add_query_arg( array( 'pquick_app' => 'upload', 'event_id' => $post->ID ), $site_url );
        $operator_url = add_query_arg( array( 'pquick_app' => 'operator', 'event_id' => $post->ID, 'key' => $this->get_operator_key( $post->ID ) ), $site_url );
        
        $qr_upload_api = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($guest_url);
        ?>
        <div style="text-align: center;">
            <p><strong>QR: להעלאת תמונות (שלטים בשולחנות)</strong></p>
            <img src="<?php echo esc_url($qr_upload_api); ?>" style="width: 100%; max-width: 150px; border: 1px solid #ccc; padding: 5px; border-radius: 8px;">
            <p style="margin-top: 5px;">
                <a href="<?php echo esc_url($qr_upload_api); ?>" download="Upload_QR.png" class="button button-secondary">הורד QR לשולחנות</a>
            </p>
            <hr style="margin: 15px 0;">
            <p><strong>עמדת המפעיל (ללא התחברות):</strong></p>
            <p>
                <button type="button" class="button button-primary" onclick="navigator.clipboard.writeText('<?php echo esc_js($operator_url); ?>'); alert('הקישור הועתק! שלח אותו למפעיל');">העתק קישור למפעיל</button>
            </p>
            <p style="font-size:11px; color:#666;">הקישור כולל מפתח סודי. מי שאין לו את הקישור המלא לא יוכל לצפות בתמונות האירוע.</p>
            <p style="font-size:11px; color:#666;">הערה: ה-QR לסורק יווצר אוטומטית על ידי המפעיל על כל תמונה.</p>
        </div>
        <?php
    }

    /**
     * מפתח סודי לכל אירוע - מגן על עמדת המפעיל ועל ה-API שחושף את תמונות האורחים.
     */
    private function get_operator_key( $event_id ) {
        $key = get_post_meta( $event_id, '_pquick_operator_key', true );
        if ( empty( $key ) ) {
            $key = wp_generate_password( 24, false, false );
            update_post_meta( $event_id, '_pquick_operator_key', $key );
        }
        return $key;
    }

    private function is_valid_operator_key( $event_id, $key ) {
        $stored = get_post_meta( $event_id, '_pquick_operator_key', true );
        return ! empty( $stored ) && is_string( $key ) && hash_equals( $stored, $key );
    }

    private function request_operator_key( WP_REST_Request $request ) {
        $key = $request->get_header( 'x-pquick-key' );
        if ( empty( $key ) ) $key = $request->get_param( 'key' );
        return (string) $key;
    }

    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['pquick_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pquick_meta_nonce'], 'pquick_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        
        if ( isset( $_POST['pquick_print_format'] ) ) update_post_meta( $post_id, '_pquick_print_format', sanitize_text_field( $_POST['pquick_print_format'] ) );
        if ( isset( $_POST['pquick_max_copies'] ) ) update_post_meta( $post_id, '_pquick_max_copies', intval( $_POST['pquick_max_copies'] ) );
        if ( isset( $_POST['pquick_global_max_uploads'] ) ) update_post_meta( $post_id, '_pquick_global_max_uploads', intval( $_POST['pquick_global_max_uploads'] ) );
        if ( isset( $_POST['pquick_overlay_url'] ) ) update_post_meta( $post_id, '_pquick_overlay_url', esc_url_raw( $_POST['pquick_overlay_url'] ) );
        if ( isset( $_POST['pquick_event_logo'] ) ) update_post_meta( $post_id, '_pquick_event_logo', esc_url_raw( $_POST['pquick_event_logo'] ) );
        
        if ( isset( $_POST['pquick_photo_w'] ) ) update_post_meta( $post_id, '_pquick_photo_w', floatval( $_POST['pquick_photo_w'] ) );
        if ( isset( $_POST['pquick_photo_h'] ) ) update_post_meta( $post_id, '_pquick_photo_h', floatval( $_POST['pquick_photo_h'] ) );
        if ( isset( $_POST['pquick_photo_t'] ) ) update_post_meta( $post_id, '_pquick_photo_t', floatval( $_POST['pquick_photo_t'] ) );
        if ( isset( $_POST['pquick_photo_l'] ) ) update_post_meta( $post_id, '_pquick_photo_l', floatval( $_POST['pquick_photo_l'] ) );
    }

    private function upload_to_s3( $file_path, $s3_file_name, $content_type, $bucket, $region, $access_key, $secret_key ) {
        $host = $bucket . '.s3.' . $region . '.amazonaws.com';
        $uri = '/' . ltrim($s3_file_name, '/');
        $date = gmdate('Ymd');
        $amz_date = gmdate('Ymd\THis\Z');
        $payload = file_get_contents($file_path);
        $payload_hash = hash('sha256', $payload);

        $canonical_uri = $uri;
        $canonical_querystring = '';
        $canonical_headers = "content-type:" . $content_type . "\nhost:" . $host . "\nx-amz-content-sha256:" . $payload_hash . "\nx-amz-date:" . $amz_date . "\n";
        $signed_headers = "content-type;host;x-amz-content-sha256;x-amz-date";

        $canonical_request = "PUT\n" . $canonical_uri . "\n" . $canonical_querystring . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date . '/' . $region . '/s3/aws4_request';
        $string_to_sign = $algorithm . "\n" . $amz_date . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);

        $kSecret = 'AWS4' . $secret_key;
        $kDate = hash_hmac('sha256', $date, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $string_to_sign, $kSigning);

        $authorization_header = $algorithm . ' Credential=' . $access_key . '/' . $credential_scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;
        
        $curl_headers = array(
            "Host: " . $host,
            "Content-Type: " . $content_type,
            "x-amz-content-sha256: " . $payload_hash,
            "x-amz-date: " . $amz_date,
            "Authorization: " . $authorization_header
        );

        $ch = curl_init('https://' . $host . $uri);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            return 'https://' . $host . $uri;
        }
        return false;
    }

    public function register_rest_endpoints() {
        register_rest_route( 'pquick/v1', '/upload', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'handle_guest_upload' ), 'permission_callback' => '__return_true' ) );
        register_rest_route( 'pquick/v1', '/event-media/(?P<event_id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_event_media' ), 'permission_callback' => array( $this, 'check_event_media_permission' ) ) );
        register_rest_route( 'pquick/v1', '/print/(?P<media_id>\d+)', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'update_print_status' ), 'permission_callback' => array( $this, 'check_print_permission' ) ) );
        register_rest_route( 'pquick/v1', '/ar-status/(?P<media_id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_ar_status' ), 'permission_callback' => '__return_true' ) );
    }

    public function check_event_media_permission( WP_REST_Request $request ) {
        $event_id = intval( $request->get_param( 'event_id' ) );
        return current_user_can( 'edit_post', $event_id ) || $this->is_valid_operator_key( $event_id, $this->request_operator_key( $request ) );
    }

    public function check_print_permission( WP_REST_Request $request ) {
        $media_id = intval( $request->get_param( 'media_id' ) );
        $event_id = intval( get_post_meta( $media_id, '_pquick_parent_event', true ) );
        if ( ! $event_id ) return false;
        return current_user_can( 'edit_post', $event_id ) || $this->is_valid_operator_key( $event_id, $this->request_operator_key( $request ) );
    }

    /**
     * בדיקת מוכנות ה-AR: האם ה-Lambda כבר יצר את קובץ ה-.mind (ובאופן אופציונלי וידאו מומר ל-web).
     * הבדיקה נעשית מהשרת (HEAD ל-S3) כדי לא להיות תלויים בהגדרות CORS של HEAD.
     */
    public function get_ar_status( WP_REST_Request $request ) {
        $media_id = intval( $request->get_param( 'media_id' ) );
        if ( get_post_type( $media_id ) !== 'pquick_media' ) {
            return new WP_Error( 'not_found', 'מדיה לא נמצאה', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $this->refresh_ar_status( $media_id ) );
    }

    private function refresh_ar_status( $media_id ) {
        $mind_url  = get_post_meta( $media_id, '_pquick_mind_url', true );
        $video_url = get_post_meta( $media_id, '_pquick_video_url', true );
        $ready     = get_post_meta( $media_id, '_pquick_ar_ready', true ) === '1';

        if ( ! $ready && $mind_url ) {
            $res = wp_remote_head( $mind_url, array( 'timeout' => 5 ) );
            if ( ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) === 200 ) {
                $ready = true;
                update_post_meta( $media_id, '_pquick_ar_ready', '1' );
            }
        }

        // וידאו מומר (H.264 720p faststart) - אם ה-Lambda מייצר אותו בנתיב {שם}_web.mp4
        $web_video = get_post_meta( $media_id, '_pquick_video_web_url', true );
        if ( ! $web_video && $video_url && strpos( $video_url, 'amazonaws.com' ) !== false ) {
            $candidate = preg_replace( '/\.[a-z0-9]+$/i', '', $video_url ) . '_web.mp4';
            $checked = get_transient( 'pquick_webvid_' . $media_id );
            if ( ! $checked ) {
                $res = wp_remote_head( $candidate, array( 'timeout' => 5 ) );
                if ( ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) === 200 ) {
                    $web_video = $candidate;
                    update_post_meta( $media_id, '_pquick_video_web_url', $web_video );
                } else {
                    set_transient( 'pquick_webvid_' . $media_id, 1, 10 );
                }
            }
        }

        return array(
            'ready'     => $ready,
            'hasTarget' => ! empty( $mind_url ),
            'videoUrl'  => $web_video ? $web_video : $video_url,
        );
    }

    public function handle_guest_upload( WP_REST_Request $request ) {
        $event_id = $request->get_param( 'event_id' );
        $copies = $request->get_param( 'copies' ) ? intval( $request->get_param( 'copies' ) ) : 1;
        $image_base64 = $request->get_param( 'image_base64' ); 
        
        if ( ! $event_id || ! $image_base64 ) return new WP_Error( 'missing_data', 'חסרים נתונים חיוניים', array( 'status' => 400 ) );
        if ( get_post_type( $event_id ) !== 'pquick_event' ) return new WP_Error( 'bad_event', 'אירוע לא קיים', array( 'status' => 404 ) );
        if ( ! preg_match( '#^data:image/(jpeg|png);base64,#', $image_base64 ) ) return new WP_Error( 'bad_image', 'פורמט תמונה לא נתמך', array( 'status' => 400 ) );

        $global_limit = get_post_meta($event_id, '_pquick_global_max_uploads', true);
        if ( !empty($global_limit) && intval($global_limit) > 0 ) {
            $current_count = count(get_posts(array('post_type' => 'pquick_media', 'meta_key' => '_pquick_parent_event', 'meta_value' => $event_id, 'posts_per_page' => -1, 'fields' => 'ids')));
            if ( $current_count >= intval($global_limit) ) {
                return new WP_Error( 'limit_reached', 'האירוע הגיע למכסת ההדפסות המקסימלית שלו.', array( 'status' => 403 ) );
            }
        }

        $aws_bucket = get_option('pquick_aws_bucket');
        $aws_region = get_option('pquick_aws_region');
        $aws_access = get_option('pquick_aws_access_key');
        $aws_secret = get_option('pquick_aws_secret_key');
        $use_aws = ($aws_bucket && $aws_region && $aws_access && $aws_secret);

        // מזהה ייחודי (ולא רק time()) כדי ששתי העלאות באותה שנייה לא ידרסו זו את זו
        $time_stamp = time() . '_' . strtolower( wp_generate_password( 6, false, false ) );
        $upload_dir = wp_upload_dir();
        
        // 1. שמירת תמונה (JPG)
        $image_parts = explode(";base64,", $image_base64);
        $image_base64_decoded = base64_decode($image_parts[1]);
        $image_name = 'pquick_evt_' . $event_id . '_' . $time_stamp . '.jpg';
        $temp_image_path = $upload_dir['path'] . '/' . $image_name;
        file_put_contents($temp_image_path, $image_base64_decoded);
        
        $final_image_url = '';
        $final_mind_url = ''; 
        
        if ($use_aws) {
            $s3_path = 'events/' . $event_id . '/' . $image_name;
            $s3_url = $this->upload_to_s3($temp_image_path, $s3_path, 'image/jpeg', $aws_bucket, $aws_region, $aws_access, $aws_secret);
            if ($s3_url) { 
                $final_image_url = $s3_url; 
                $s3_mind_path = str_replace('.jpg', '.mind', $s3_path);
                $final_mind_url = 'https://' . $aws_bucket . '.s3.' . $aws_region . '.amazonaws.com/' . $s3_mind_path;
                unlink($temp_image_path); 
            } else { 
                $final_image_url = $upload_dir['url'] . '/' . $image_name; 
            }
        } else {
            $final_image_url = $upload_dir['url'] . '/' . $image_name;
        }

        // 2. טיפול בוידאו
        $video_file = $request->get_file_params()['video_file'] ?? null;
        $final_video_url = '';
        if ( $video_file && $video_file['error'] === UPLOAD_ERR_OK ) {
            $video_type = wp_check_filetype( $video_file['name'] );
            $allowed_ext = array( 'mp4', 'mov', 'm4v', 'webm', '3gp' );
            if ( ! in_array( strtolower( (string) $video_type['ext'] ), $allowed_ext, true ) || $video_file['size'] > 200 * MB_IN_BYTES ) {
                return new WP_Error( 'bad_video', 'קובץ הווידאו לא נתמך או גדול מדי', array( 'status' => 400 ) );
            }
            $video_file['type'] = $video_type['type'] ? $video_type['type'] : 'video/mp4';
        }
        if ( $video_file && $video_file['error'] === UPLOAD_ERR_OK ) {
            if ($use_aws) {
                $video_ext = pathinfo($video_file['name'], PATHINFO_EXTENSION);
                $video_name = 'pquick_evt_' . $event_id . '_' . $time_stamp . '.' . $video_ext;
                $s3_video_path = 'events/' . $event_id . '/' . $video_name;
                $s3_vid_url = $this->upload_to_s3($video_file['tmp_name'], $s3_video_path, $video_file['type'], $aws_bucket, $aws_region, $aws_access, $aws_secret);
                if ($s3_vid_url) { $final_video_url = $s3_vid_url; }
            } else {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                $movefile = wp_handle_upload( $video_file, array( 'test_form' => false ) );
                if ( $movefile && ! isset( $movefile['error'] ) ) { $final_video_url = $movefile['url']; }
            }
        }

        $post_id = wp_insert_post( array( 'post_type' => 'pquick_media', 'post_title' => 'Upload #' . $time_stamp, 'post_status' => 'publish' ) );
        update_post_meta( $post_id, '_pquick_parent_event', $event_id );
        update_post_meta( $post_id, '_pquick_image_url', $final_image_url );
        update_post_meta( $post_id, '_pquick_video_url', $final_video_url );
        update_post_meta( $post_id, '_pquick_mind_url', $final_mind_url );
        update_post_meta( $post_id, '_pquick_copies', $copies );
        update_post_meta( $post_id, '_pquick_print_status', 'pending' ); 

        // נתוני התאמה וידאו<->הדפסה: הרגע שנבחר, החיתוך המדויק מתוך פריים הווידאו, ויחס המטרה המודפסת
        $frame_time = floatval( $request->get_param( 'frame_time' ) );
        update_post_meta( $post_id, '_pquick_frame_time', max( 0, $frame_time ) );
        $crop = array();
        foreach ( array( 'x', 'y', 'w', 'h' ) as $k ) {
            $v = $request->get_param( 'crop_' . $k );
            if ( $v === null || $v === '' ) { $crop = array(); break; }
            $crop[ $k ] = min( 1, max( 0, floatval( $v ) ) );
        }
        if ( $crop && $crop['w'] > 0 && $crop['h'] > 0 ) update_post_meta( $post_id, '_pquick_video_crop', wp_json_encode( $crop ) );
        $target_aspect = floatval( $request->get_param( 'target_aspect' ) );
        if ( $target_aspect > 0.2 && $target_aspect < 5 ) update_post_meta( $post_id, '_pquick_target_aspect', $target_aspect );

        return rest_ensure_response( array( 'success' => true, 'media_id' => $post_id ) );
    }

    public function get_event_media( WP_REST_Request $request ) {
        $event_id = $request->get_param( 'event_id' );
        $args = array( 'post_type' => 'pquick_media', 'posts_per_page' => 500, 'meta_query' => array( array( 'key' => '_pquick_parent_event', 'value' => $event_id, 'compare' => '=' ) ), 'orderby' => 'date', 'order' => 'DESC' );
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

    public function render_frontend_apps() {
        // כתובת קצרה לסורק (?pq=MEDIA_ID): QR קצר = מודולים גדולים יותר = סריקה מהירה יותר מההדפסה
        if ( isset( $_GET['pq'] ) && ! isset( $_GET['pquick_app'] ) ) {
            $media_id = intval( $_GET['pq'] );
            $event_id = intval( get_post_meta( $media_id, '_pquick_parent_event', true ) );
            if ( ! $event_id || get_post_type( $media_id ) !== 'pquick_media' ) {
                wp_die( 'קוד ה-QR לא תקין.', 'שגיאה', array( 'response' => 404 ) );
            }
            $_GET['media_id'] = $media_id;
            $this->output_scanner_app( $event_id ); exit;
        }
        if ( ! isset( $_GET['pquick_app'] ) ) return;
        
        $app = sanitize_text_field( $_GET['pquick_app'] );
        $event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : 0;
        
        if ( ! $event_id || get_post_type( $event_id ) !== 'pquick_event' ) {
            wp_die( 'אירוע לא חוקי או לא קיים.', 'שגיאה', array( 'response' => 404 ) );
        }

        if ( $app === 'upload' ) { $this->output_guest_app( $event_id ); exit; } 
        elseif ( $app === 'operator' ) {
            $key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
            if ( ! current_user_can( 'edit_post', $event_id ) && ! $this->is_valid_operator_key( $event_id, $key ) ) {
                wp_die( 'אין הרשאה לעמדת המפעיל. בקשו מהמנהל את הקישור המלא.', 'אין הרשאה', array( 'response' => 403 ) );
            }
            $this->output_operator_app( $event_id ); exit;
        } 
        elseif ( $app === 'scanner' ) { $this->output_scanner_app( $event_id ); exit; }
    }

    // --- אפליקציה 1: האורח ---
    private function output_guest_app( $event_id ) {
        $event_name = get_the_title($event_id);
        $max_copies = get_post_meta($event_id, '_pquick_max_copies', true) ?: 3;
        $print_format = get_post_meta($event_id, '_pquick_print_format', true) ?: '0.75';
        $overlay_url = get_post_meta($event_id, '_pquick_overlay_url', true);
        
        $photo_w = get_post_meta( $event_id, '_pquick_photo_w', true ) !== '' ? get_post_meta( $event_id, '_pquick_photo_w', true ) : '100';
        $photo_h = get_post_meta( $event_id, '_pquick_photo_h', true ) !== '' ? get_post_meta( $event_id, '_pquick_photo_h', true ) : '100';
        $photo_t = get_post_meta( $event_id, '_pquick_photo_t', true ) !== '' ? get_post_meta( $event_id, '_pquick_photo_t', true ) : '0';
        $photo_l = get_post_meta( $event_id, '_pquick_photo_l', true ) !== '' ? get_post_meta( $event_id, '_pquick_photo_l', true ) : '0';
        
        $logo_url = get_post_meta($event_id, '_pquick_event_logo', true);
        $has_logo = ! empty( trim( $logo_url ) );
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
                
                .preview-frame { position: relative; width: 100%; background-color: #eee; overflow: hidden; box-shadow: 0 4px 15px rgba(69, 72, 87, 0.15); }
                .preview-overlay-dynamic { width: 100%; height: auto; position: relative; z-index: 2; pointer-events: none; display: block; object-fit: contain; }
                .preview-image { position: absolute; z-index: 1; object-fit: cover; width: <?php echo esc_attr($photo_w); ?>%; height: <?php echo esc_attr($photo_h); ?>%; top: <?php echo esc_attr($photo_t); ?>%; left: <?php echo esc_attr($photo_l); ?>%; }
                
                .crop-container { width: 100%; height: 50vh; background-color: #000; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
                
                /* עיצוב סליידר חילוץ הפריים */
                input[type=range] { -webkit-appearance: none; width: 100%; background: transparent; }
                input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; height: 24px; width: 24px; border-radius: 50%; background: #ffb800; cursor: pointer; margin-top: -10px; box-shadow: 0 2px 6px rgba(0,0,0,0.3); border: 2px solid white; }
                input[type=range]::-webkit-slider-runnable-track { width: 100%; height: 6px; cursor: pointer; background: #e5e7eb; border-radius: 4px; }
                
                @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
                .btn-magic-contact { background: linear-gradient(270deg, #ffb800, #ff7a7b, #9ad7cf, #ffb800); background-size: 300% 300%; animation: gradientShift 4s ease infinite; color: white; text-shadow: 0px 1px 2px rgba(0,0,0,0.2); }
                .progress-container { width: 100%; background-color: #e5e7eb; border-radius: 9999px; height: 12px; overflow: hidden; margin-top: 15px; }
                .progress-bar { height: 100%; background-color: #ffb800; width: 0%; transition: width 0.3s ease; border-radius: 9999px; }
            </style>
        </head>
        <body class="text-pquick-dark">
        <div class="app-container relative">
            <header class="p-4 flex justify-center items-center border-b border-gray-100 sticky top-0 bg-white z-10 shadow-sm min-h-[70px]">
                <?php if($has_logo): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Event Logo" class="h-10 w-auto object-contain shrink-0 drop-shadow-sm" style="min-width: 60px; max-width: 150px;">
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center leading-none">
                        <span class="text-3xl font-bold text-pquick-dark" style="font-family: 'Alef', sans-serif;">Pquick</span>
                        <span class="text-lg font-bold text-pquick-orange" style="font-family: 'Alef', sans-serif;">Events</span>
                    </div>
                <?php endif; ?>
            </header>
            <main class="flex-1 p-5 flex flex-col">
                <div id="step-upload" class="step active">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-bold mb-2">ברוכים הבאים ל<span id="welcome-event-name" class="text-pquick-orange">אירוע</span>!</h1>
                        <p class="text-gray-500 text-sm">העלו סרטון שיתעורר לחיים ב-AR, ואנחנו כבר נגזור ממנו את התמונה המושלמת להדפסה.</p>
                    </div>
                    <div class="space-y-6 mt-4">
                        <div class="file-upload-wrapper w-full">
                            <div class="border-2 border-dashed border-pquick-orange rounded-xl p-10 text-center transition-colors hover:border-pquick-pink bg-pquick-light shadow-sm" id="video-dropzone">
                                <div class="text-pquick-orange text-6xl mb-4 animate-bounce"><i class="fa-solid fa-video"></i></div>
                                <h3 class="font-bold text-2xl mb-2">בחרו או צלמו סרטון</h3>
                                <p class="text-sm text-gray-500">לחצו כאן (עד 15 שניות)</p>
                            </div>
                            <input type="file" id="input-video" accept="video/*">
                        </div>
                    </div>
                </div>

                <div id="step-frame-select" class="step">
                    <div class="text-center mb-4">
                        <h2 class="text-xl font-bold">בחירת תמונה להדפסה</h2>
                        <p class="text-gray-500 text-sm">הזיזו את הסליידר כדי לבחור את הרגע המושלם מהסרטון. מכאן התמונה תתעורר לחיים!</p>
                    </div>
                    <div class="relative bg-black rounded-xl overflow-hidden mb-6 flex items-center justify-center" style="height: 45vh;">
                        <video id="video-extractor" class="max-w-full max-h-full object-contain" playsinline webkit-playsinline muted preload="auto"></video>
                    </div>
                    <button id="btn-auto-sharp" class="mx-auto mb-4 text-sm font-bold text-pquick-dark bg-pquick-light border border-gray-200 rounded-full px-4 py-2 shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-wand-magic-sparkles text-pquick-orange"></i> <span>בחרו לי את הרגע הכי חד</span>
                    </button>
                    <p id="sharp-hint" class="text-xs text-center text-gray-500 -mt-2 mb-3">רגע חד = תמונה שתתעורר לחיים מהר ויציב יותר</p>
                    <div class="px-2 mb-8 mt-auto">
                        <input type="range" id="video-slider" min="0" max="100" step="0.01" value="0">
                        <div class="flex justify-between text-xs text-gray-500 mt-2 font-bold">
                            <span>התחלה <i class="fa-solid fa-play"></i></span>
                            <span>סוף <i class="fa-solid fa-flag-checkered"></i></span>
                        </div>
                    </div>
                    <div class="flex gap-3 pb-6">
                        <button id="btn-cancel-video" class="flex-1 bg-white border-2 border-gray-200 text-pquick-dark font-bold py-3 rounded-full hover:bg-gray-50">ביטול</button>
                        <button id="btn-capture-frame" class="flex-[2] bg-pquick-orange text-pquick-dark font-bold py-3 rounded-full shadow-lg hover:opacity-90">הדפס רגע זה <i class="fa-solid fa-camera ml-2"></i></button>
                    </div>
                </div>

                <div id="step-crop" class="step">
                    <div class="text-center mb-4"><h2 class="text-xl font-bold">התאמה למסגרת</h2><p class="text-gray-500 text-sm">הזיזו או עשו זום להתאמה מושלמת למסגרת ההדפסה</p></div>
                    <div class="crop-container"><img id="image-to-crop" src="" style="max-width: 100%; display: block;"></div>
                    <div class="flex gap-3 mt-auto pb-10">
                        <button id="btn-back-to-frame" class="flex-1 bg-white border-2 border-gray-200 text-pquick-dark font-bold py-3 rounded-full transition-colors hover:bg-gray-50">אחורה</button>
                        <button id="btn-save-crop" class="flex-[2] bg-pquick-orange text-pquick-dark font-bold py-3 rounded-full shadow-lg hover:opacity-90 transition-opacity">חתוך והמשך <i class="fa-solid fa-crop-simple ml-2"></i></button>
                    </div>
                </div>

                <div id="step-preview" class="step">
                    <div class="text-center mb-4"><h2 class="text-xl font-bold">ככה זה ייראה באירוע!</h2></div>
                    <div class="preview-frame mx-auto mb-4 rounded-xl">
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
                    <div class="flex gap-3 mt-auto pb-10">
                        <button id="btn-back" class="flex-1 bg-white border-2 border-gray-200 text-pquick-dark font-bold py-3 rounded-full hover:bg-gray-50">חזור</button>
                        <button id="btn-submit" class="flex-[2] bg-pquick-orange text-pquick-dark font-bold py-3 rounded-full shadow-lg hover:opacity-90">הדפס אותי! <i class="fa-solid fa-print ml-2"></i></button>
                    </div>
                </div>

                <div id="step-loading" class="step items-center justify-center">
                    <div class="text-center w-full max-w-xs mx-auto">
                        <i class="fa-solid fa-cloud-arrow-up text-5xl text-pquick-orange mb-4 animate-bounce" id="loading-icon"></i>
                        <h2 class="text-xl font-bold" id="loading-title">מעלה קבצים...</h2>
                        <p class="text-gray-500 text-sm mt-2" id="loading-subtitle">מכין את קסם ה-AR, אנא המתן</p>
                        <div class="progress-container"><div id="upload-progress-bar" class="progress-bar"></div></div>
                        <p id="upload-progress-text" class="text-pquick-dark font-bold mt-2">0%</p>
                    </div>
                </div>

                <div id="step-success" class="step text-center">
                    <div class="w-20 h-20 bg-pquick-lightgreen rounded-full flex items-center justify-center mx-auto mb-4 text-pquick-dark text-4xl shadow-md mt-4"><i class="fa-solid fa-check"></i></div>
                    <h1 class="text-2xl font-bold mb-2">הזכרון נשלח להדפסה!</h1>
                    
                    <div class="bg-pquick-light rounded-xl p-5 text-right my-2 border border-gray-200 shadow-sm">
                        <h3 class="font-bold text-lg mb-4 text-center border-b border-gray-200 pb-2">מה עושים עכשיו?</h3>
                        <ul class="space-y-4">
                            <li class="flex items-start gap-3">
                                <div class="bg-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-pquick-orange shrink-0 shadow-sm">1</div>
                                <div><span class="font-bold">גשו לעמדת ההדפסה</span> וקחו את התמונה המוכנה שלכם.</div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="bg-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-pquick-orange shrink-0 shadow-sm">2</div>
                                <div><span class="font-bold">סרקו את ה-QR</span> שעל גבי התמונה כדי לפתוח את סורק הקסם.</div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="bg-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-pquick-orange shrink-0 shadow-sm">3</div>
                                <div><span class="font-bold">כוונו את המצלמה</span> אל התמונה המודפסת וראו איך היא מתעוררת לחיים!</div>
                            </li>
                        </ul>
                    </div>

                    <a id="btn-open-scanner" href="#" class="w-full bg-pquick-orange text-pquick-dark font-bold py-4 rounded-full shadow-lg hover:scale-105 transition-transform flex items-center justify-center gap-2 mb-4 mt-4 text-lg hidden">
                        <i class="fa-solid fa-expand"></i> פתח סורק AR עכשיו!
                    </a>

                    <button onclick="location.reload()" class="w-full bg-white border-2 border-gray-300 text-gray-600 font-bold py-3 rounded-full hover:bg-gray-50 mb-4 transition-colors">
                        העלה סרטון נוסף
                    </button>

                    <button id="btn-guest-share" class="w-full bg-[#ffb800] text-[#454857] font-bold py-3 px-8 rounded-full shadow-md hover:scale-105 transition-transform flex items-center justify-center gap-2 mb-6">
                        <i class="fa-solid fa-share-nodes"></i> שתפו את הקסם לחברים!
                    </button>
                    
                    <div class="mt-auto border-t border-gray-200 pt-4 w-full flex flex-col items-center pb-8">
                        <p class="text-gray-500 text-sm mb-2 font-medium">רוצים את הקסם הזה גם באירוע שלכם?</p>
                        <a href="https://pquick.co.il/events/" target="_blank" class="btn-magic-contact inline-flex items-center justify-center font-bold py-3 px-8 rounded-full text-sm transition-transform hover:scale-105 shadow-lg"><i class="fa-solid fa-wand-magic-sparkles ml-2"></i> לפרטים והזמנות</a>
                    </div>
                </div>
            </main>
        </div>

        <script>
            const EVENT_DATA = {
                id: <?php echo intval($event_id); ?>,
                name: "<?php echo esc_js($event_name); ?>",
                maxCopies: <?php echo intval($max_copies); ?>,
                printFormat: <?php echo floatval($print_format); ?>, 
                overlayUrl: "<?php echo esc_js($overlay_url); ?>"
            };

            const HAS_CUSTOM_OVERLAY = !!EVENT_DATA.overlayUrl;
            const LAYOUT = { w: <?php echo floatval($photo_w); ?>, h: <?php echo floatval($photo_h); ?> };
            // יחס החיתוך בפועל: אם יש מסגרת - יחס ה"חור" השקוף במסגרת (כך שמה שמודפס = בדיוק מה שנחתך = בדיוק מטרת ה-AR)
            let cropAspect = EVENT_DATA.printFormat;
            if (HAS_CUSTOM_OVERLAY) {
                const ov = new Image();
                ov.crossOrigin = 'anonymous';
                ov.onload = () => {
                    if (ov.naturalWidth && ov.naturalHeight && LAYOUT.w > 0 && LAYOUT.h > 0) {
                        const a = (LAYOUT.w * ov.naturalWidth) / (LAYOUT.h * ov.naturalHeight);
                        if (a > 0.2 && a < 5) cropAspect = a;
                    }
                };
                ov.src = EVENT_DATA.overlayUrl;
            }

            if (!EVENT_DATA.overlayUrl) {
                EVENT_DATA.overlayUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800" viewBox="0 0 600 800"><path d="M0 0h600v800H0z M30 30v650h540V30z" fill="#ffffff" fill-rule="evenodd"/><text x="300" y="730" font-family="Alef, sans-serif" font-size="40" fill="#ffb800" font-weight="bold" text-anchor="middle">${EVENT_DATA.name}</text></svg>`);
            }

            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('welcome-event-name').textContent = EVENT_DATA.name;
                document.getElementById('preview-overlay-element').src = EVENT_DATA.overlayUrl;
                document.getElementById('max-qty-num').textContent = EVENT_DATA.maxCopies;
                
                const inputVideo = document.getElementById('input-video');
                const videoExtractor = document.getElementById('video-extractor');
                const videoSlider = document.getElementById('video-slider');
                
                const imageToCropElement = document.getElementById('image-to-crop');
                const previewImgElement = document.getElementById('preview-img-element');
                const btnGuestShare = document.getElementById('btn-guest-share');
                
                let cropper = null;
                let croppedImageDataUrl = null;
                let currentQty = 1;
                let selectedVideoFile = null;
                let selectedFrameTime = 0;
                let videoCropData = null; // חיתוך מנורמל (0..1) מתוך פריים הווידאו

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

                const showStep = (stepId) => {
                    document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
                    document.getElementById(stepId).classList.add('active');
                };

                // בחירת וידאו וטעינת הנתונים (תוקן למובייל)
                inputVideo.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        selectedVideoFile = this.files[0];
                        const videoURL = URL.createObjectURL(selectedVideoFile);
                        videoExtractor.src = videoURL;
                        videoExtractor.load(); // מכריח דפדפני מובייל להתחיל טעינה
                        
                        videoExtractor.onloadeddata = () => {
                            videoSlider.max = videoExtractor.duration;
                            videoSlider.value = 0;
                            
                            // טריק קריטי לאייפון: הזזה קלה מכריחה רינדור של הפריים
                            setTimeout(() => {
                                videoExtractor.currentTime = 0.001; 
                            }, 100);

                            showStep('step-frame-select');
                        };
                    }
                });

                // גלילת הוידאו באמצעות הסליידר
                videoSlider.addEventListener('input', function() {
                    videoExtractor.currentTime = this.value;
                });

                document.getElementById('btn-cancel-video').addEventListener('click', () => {
                    inputVideo.value = ''; 
                    selectedVideoFile = null;
                    showStep('step-upload');
                });

                // --- בחירת הרגע הכי חד (שונות לפלסיאן): פריים חד = יותר נקודות מעקב = זיהוי מהיר ויציב ---
                const seekTo = (t) => new Promise(resolve => {
                    const done = () => { videoExtractor.removeEventListener('seeked', done); resolve(); };
                    videoExtractor.addEventListener('seeked', done);
                    videoExtractor.currentTime = t;
                    setTimeout(done, 1500);
                });
                const sharpnessScore = () => {
                    const w = 160, h = Math.max(1, Math.round(160 * videoExtractor.videoHeight / videoExtractor.videoWidth));
                    const c = document.createElement('canvas'); c.width = w; c.height = h;
                    const cx = c.getContext('2d', { willReadFrequently: true });
                    cx.drawImage(videoExtractor, 0, 0, w, h);
                    const d = cx.getImageData(0, 0, w, h).data;
                    const g = new Float32Array(w * h);
                    for (let i = 0; i < w * h; i++) g[i] = 0.299 * d[i*4] + 0.587 * d[i*4+1] + 0.114 * d[i*4+2];
                    let sum = 0, sumSq = 0, n = 0;
                    for (let y = 1; y < h - 1; y++) for (let x = 1; x < w - 1; x++) {
                        const i = y * w + x;
                        const lap = g[i-1] + g[i+1] + g[i-w] + g[i+w] - 4 * g[i];
                        sum += lap; sumSq += lap * lap; n++;
                    }
                    const mean = sum / n;
                    return sumSq / n - mean * mean;
                };
                document.getElementById('btn-auto-sharp').addEventListener('click', async (e) => {
                    const btn = e.currentTarget;
                    if (!videoExtractor.duration || btn.disabled) return;
                    btn.disabled = true;
                    const label = btn.querySelector('span');
                    const orig = label.textContent;
                    const samples = 16;
                    let best = { t: 0, s: -1 };
                    for (let i = 0; i < samples; i++) {
                        const t = Math.min(videoExtractor.duration - 0.05, (i + 0.5) * videoExtractor.duration / samples);
                        label.textContent = `מחפש... ${Math.round((i + 1) / samples * 100)}%`;
                        await seekTo(t);
                        try { const s = sharpnessScore(); if (s > best.s) best = { t, s }; } catch (err) { break; }
                    }
                    await seekTo(best.t);
                    videoSlider.value = best.t;
                    label.textContent = orig;
                    btn.disabled = false;
                    if (navigator.vibrate) navigator.vibrate(20);
                });

                // צילום המסך מתוך הוידאו והעברה לקרופר
                document.getElementById('btn-capture-frame').addEventListener('click', () => {
                    selectedFrameTime = videoExtractor.currentTime || 0;
                    const canvas = document.createElement('canvas');
                    canvas.width = videoExtractor.videoWidth;
                    canvas.height = videoExtractor.videoHeight;
                    const ctx = canvas.getContext('2d');
                    
                    ctx.drawImage(videoExtractor, 0, 0, canvas.width, canvas.height);
                    const frameDataURL = canvas.toDataURL('image/jpeg', 1.0);
                    
                    imageToCropElement.src = frameDataURL;
                    showStep('step-crop');
                    
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(imageToCropElement, { 
                        aspectRatio: cropAspect, 
                        viewMode: 1, dragMode: 'move', autoCropArea: 1, guides: true, center: true, highlight: false, cropBoxMovable: false, cropBoxResizable: false, toggleDragModeOnDblclick: false 
                    });
                });

                document.getElementById('btn-back-to-frame').addEventListener('click', () => {
                    if (cropper) cropper.destroy();
                    showStep('step-frame-select');
                });

                // אישור החיתוך
                document.getElementById('btn-save-crop').addEventListener('click', () => {
                    if (!cropper) return;
                    
                    // שומרים את החיתוך המדויק כדי שהסורק יחתוך את הוידאו בדיוק כמו ההדפסה
                    const cd = cropper.getData(true);
                    const imgData = cropper.getImageData();
                    const nW = imgData.naturalWidth || 1, nH = imgData.naturalHeight || 1;
                    videoCropData = { x: cd.x / nW, y: cd.y / nH, w: cd.width / nW, h: cd.height / nH };

                    const exportWidth = 1200;
                    const exportHeight = Math.round(exportWidth / cropAspect);
                    
                    const canvas = cropper.getCroppedCanvas({ width: exportWidth, height: exportHeight, imageSmoothingQuality: 'high' });
                    croppedImageDataUrl = canvas.toDataURL('image/jpeg', 0.92);
                    previewImgElement.src = croppedImageDataUrl;
                    
                    if (cropper) cropper.destroy();
                    showStep('step-preview');
                });

                document.getElementById('btn-back').addEventListener('click', () => showStep('step-crop'));

                // שליחה לשרת
                document.getElementById('btn-submit').addEventListener('click', async () => {
                    showStep('step-loading');
                    
                    const formData = new FormData();
                    formData.append('event_id', EVENT_DATA.id);
                    formData.append('copies', currentQty);
                    formData.append('image_base64', croppedImageDataUrl);
                    formData.append('frame_time', selectedFrameTime.toFixed(3));
                    formData.append('target_aspect', cropAspect.toFixed(5));
                    if (videoCropData) {
                        formData.append('crop_x', videoCropData.x.toFixed(5));
                        formData.append('crop_y', videoCropData.y.toFixed(5));
                        formData.append('crop_w', videoCropData.w.toFixed(5));
                        formData.append('crop_h', videoCropData.h.toFixed(5));
                    }
                    if (selectedVideoFile) formData.append('video_file', selectedVideoFile);

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '/wp-json/pquick/v1/upload', true);
                    xhr.upload.onprogress = function(e) {
                        if (e.lengthComputable) {
                            const percentComplete = Math.round((e.loaded / e.total) * 100);
                            document.getElementById('upload-progress-bar').style.width = percentComplete + '%';
                            document.getElementById('upload-progress-text').textContent = percentComplete + '%';
                        }
                    };
                    xhr.onload = function() {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            const data = JSON.parse(xhr.responseText);
                            if(data.success) {
                                showStep('step-success');
                                
                                // אסטרטגיית סורק רציף: בניית הלינק והצגת הכפתור
                                const scannerBtn = document.getElementById('btn-open-scanner');
                                scannerBtn.href = window.location.origin + window.location.pathname + "?pquick_app=scanner&event_id=" + EVENT_DATA.id + "&media_id=" + data.media_id;
                                scannerBtn.classList.remove('hidden');
                                
                                if(typeof confetti !== 'undefined') confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 }, colors: ['#ffb800', '#9ad7cf', '#ff7a7b', '#454857'], zIndex: 100 });
                            } else { alert('שגיאה: ' + (data.message || 'לא הצלחנו לשמור.')); showStep('step-preview'); }
                        } else {
                            if(xhr.status === 403) { alert('האירוע הגיע למכסת ההדפסות המקסימלית שלו!'); showStep('step-upload'); } 
                            else { alert('שגיאת שרת. אנא נסה שוב מאוחר יותר.'); showStep('step-preview'); }
                        }
                    };
                    xhr.onerror = function() { alert('שגיאת תקשורת, בדוק חיבור לאינטרנט.'); showStep('step-preview'); };
                    xhr.send(formData);
                });

                btnGuestShare.addEventListener('click', async () => {
                    const shareData = {
                        title: 'Pquick AR Events',
                        text: 'העליתי סרטון לאירוע! בואו לראות ולהעלות גם:',
                        url: window.location.href
                    };
                    if (navigator.share && navigator.canShare(shareData)) {
                        try { await navigator.share(shareData); } catch (err) {}
                    } else {
                        navigator.clipboard.writeText(window.location.href);
                        alert('הקישור הועתק! תוכל לשלוח אותו לחברים.');
                    }
                });
            });
        </script>
        </body>
        </html>
        <?php
    }

    // --- אפליקציה 2: עמדת המפעיל ---
    private function output_operator_app( $event_id ) {
        $event_name = get_the_title($event_id);
        $overlay_url = get_post_meta($event_id, '_pquick_overlay_url', true);
        
        $custom_logo = get_post_meta($event_id, '_pquick_event_logo', true);
        $logo_url = $custom_logo ? $custom_logo : '';
        $has_logo = ! empty( trim( $logo_url ) );
        
        $photo_w = get_post_meta( $event_id, '_pquick_photo_w', true ) !== '' ? get_post_meta( $event_id, '_pquick_photo_w', true ) : '100';
        $photo_h = get_post_meta( $event_id, '_pquick_photo_h', true ) !== '' ? get_post_meta( $event_id, '_pquick_photo_h', true ) : '100';
        $photo_t = get_post_meta( $event_id, '_pquick_photo_t', true ) !== '' ? get_post_meta( $event_id, '_pquick_photo_t', true ) : '0';
        $photo_l = get_post_meta( $event_id, '_pquick_photo_l', true ) !== '' ? get_post_meta( $event_id, '_pquick_photo_l', true ) : '0';
        ?>
        <!DOCTYPE html>
        <html lang="he" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Pquick AR - לוח מפעיל</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Alef:wght@400;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
            <script>
                tailwind.config = { theme: { extend: { colors: { pquick: { dark: '#454857', orange: '#ffb800', lightgreen: '#9ad7cf', pink: '#ff7a7b' } }, fontFamily: { sans: ['Alef', 'sans-serif'] } } } }
            </script>
            <style>
                body { background-color: #f0f2f5; }
                ::-webkit-scrollbar { width: 8px; } ::-webkit-scrollbar-track { background: #f1f1f1; } ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
                @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
                .item-animate { animation: slideIn 0.3s ease-out forwards; }
            </style>
        </head>
        <body class="text-pquick-dark h-screen flex flex-col overflow-hidden">
            <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4 flex justify-between items-center shrink-0 z-10 min-h-[70px]">
                <div class="flex items-center gap-4">
                    <?php if($has_logo): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Event Logo" class="h-10 w-auto object-contain shrink-0 drop-shadow-sm skip-lazy" data-no-lazy="1" data-skip-lazy="1" style="min-width: 60px; max-width: 150px;">
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center leading-none">
                            <span class="text-2xl font-bold text-pquick-dark" style="font-family: 'Alef', sans-serif;">Pquick</span>
                            <span class="text-sm font-bold text-pquick-orange" style="font-family: 'Alef', sans-serif;">Events</span>
                        </div>
                    <?php endif; ?>
                    <div class="h-8 w-px bg-gray-300"></div>
                    <div>
                        <h1 class="text-xl font-bold leading-none"><?php echo esc_html($event_name); ?></h1>
                        <span class="text-sm text-green-500 font-bold flex items-center gap-1"><span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span> מחובר לשרת ענן באמזון</span>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="text-center"><div class="text-sm text-gray-500">סה"כ העלאות</div><div class="text-xl font-bold" id="stat-total">0</div></div>
                    <div class="text-center"><div class="text-sm text-pquick-pink font-bold">ממתין להדפסה</div><div class="text-xl font-bold text-pquick-pink" id="stat-pending">0</div></div>
                    <div class="text-center"><div class="text-sm text-pquick-lightgreen font-bold">הודפס</div><div class="text-xl font-bold text-pquick-lightgreen" id="stat-printed">0</div></div>
                </div>
            </header>
            
            <div class="bg-white px-6 py-3 border-b border-gray-200 flex justify-between items-center shrink-0">
                <div class="flex items-center gap-3">
                    <div class="flex gap-2">
                        <button id="btn-view-grid" class="view-btn active w-10 h-10 rounded-md bg-pquick-dark text-white shadow-inner"><i class="fa-solid fa-border-all text-lg"></i></button>
                        <button id="btn-view-list" class="view-btn w-10 h-10 rounded-md bg-white border border-gray-300 text-gray-600 hover:bg-gray-50"><i class="fa-solid fa-list text-lg"></i></button>
                    </div>
                    <div class="w-px h-6 bg-gray-300 mx-1"></div>
                    <button onclick="toggleAll(true)" class="text-sm text-gray-600 hover:text-pquick-dark font-medium"><i class="fa-solid fa-check-double"></i> בחר הכל</button>
                    <button onclick="toggleAll(false)" class="text-sm text-gray-600 hover:text-pquick-dark font-medium ml-2"><i class="fa-regular fa-square"></i> בטל בחירה</button>
                    <button id="btn-download-selected" onclick="downloadSelected()" class="bg-pquick-lightgreen text-pquick-dark font-bold text-sm px-4 py-2 rounded-md hover:opacity-90 transition-opacity hidden shadow-sm"><i class="fa-solid fa-download ml-1"></i> הורד נבחרים (<span id="selected-count">0</span>)</button>
                    <button id="btn-download-all" onclick="downloadAll()" class="bg-white border-2 border-pquick-dark text-pquick-dark font-bold text-sm px-4 py-2 rounded-md hover:bg-gray-50 transition-colors shadow-sm"><i class="fa-solid fa-file-zipper ml-1"></i> הורד כל האירוע</button>
                </div>
                <div><input type="text" id="search-input" placeholder="חיפוש לפי ID..." class="pl-4 pr-4 py-2 border border-gray-300 rounded-full text-sm w-64"></div>
            </div>
            
            <main class="flex-1 overflow-y-auto p-6" id="main-content-area"><div id="content-container">טוען נתונים מהשרת...</div></main>

            <div id="download-progress-container" class="fixed inset-0 bg-white/90 backdrop-blur-sm z-50 hidden flex-col items-center justify-center">
                <i class="fa-solid fa-file-zipper text-6xl text-pquick-dark mb-4 animate-bounce"></i>
                <h2 class="text-2xl font-bold text-pquick-dark mb-2">מכין קבצים להורדה...</h2>
                <p id="download-progress-text" class="text-gray-500 font-medium mb-4">אנא המתן, ממזג תמונות עם המסגרת</p>
                <div class="w-64 bg-gray-200 rounded-full h-4 overflow-hidden shadow-inner">
                    <div id="download-progress-bar" class="bg-pquick-orange h-full w-0 transition-all duration-300"></div>
                </div>
            </div>

        <script>
            const EVENT_ID = <?php echo intval($event_id); ?>;
            const OVERLAY_URL = "<?php echo esc_js($overlay_url); ?>";
            const LAYOUT = { w: <?php echo floatval($photo_w); ?>, h: <?php echo floatval($photo_h); ?>, t: <?php echo floatval($photo_t); ?>, l: <?php echo floatval($photo_l); ?> };
            const LOGO_URL = "<?php echo esc_js($logo_url); ?>";
            const OPERATOR_KEY = new URLSearchParams(window.location.search).get('key') || '';
            const API_HEADERS = { 'X-Pquick-Key': OPERATOR_KEY };

            let finalOverlay = OVERLAY_URL;
            if (!finalOverlay) {
                finalOverlay = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800" viewBox="0 0 600 800"><path d="M0 0h600v800H0z M30 30v650h540V30z" fill="#ffffff" fill-rule="evenodd"/><text x="300" y="730" font-family="Alef, sans-serif" font-size="40" fill="#ffb800" font-weight="bold" text-anchor="middle"><?php echo esc_js($event_name); ?></text></svg>`);
            }

            let uploadsData = [];
            let currentView = 'grid';
            let selectedItems = new Set();
            let cachedOverlayImg = null;
            
            async function fetchMedia() {
                try {
                    const res = await fetch('/wp-json/pquick/v1/event-media/' + EVENT_ID, { headers: API_HEADERS });
                    if (!res.ok) { console.error('API status', res.status); return; }
                    uploadsData = await res.json();
                    render();
                } catch(e) { console.error("API error", e); }
            }

            fetchMedia();
            setInterval(fetchMedia, 5000);

            window.toggleSelection = function(id, isChecked) {
                if(isChecked) selectedItems.add(String(id));
                else selectedItems.delete(String(id));
                updateSelectionUI();
            };

            window.toggleAll = function(isChecked) {
                if(isChecked) { uploadsData.forEach(item => selectedItems.add(String(item.id))); } 
                else { selectedItems.clear(); }
                render();
                updateSelectionUI();
            };

            function updateSelectionUI() {
                const count = selectedItems.size;
                document.getElementById('selected-count').innerText = count;
                const btnSel = document.getElementById('btn-download-selected');
                if(count > 0) { btnSel.classList.remove('hidden'); btnSel.classList.add('inline-flex'); } 
                else { btnSel.classList.add('hidden'); btnSel.classList.remove('inline-flex'); }
            }

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

            function generateQRCodeLink(mediaId) {
                // מחזיר את כתובת הסורק. ה-QR עצמו נוצר מקומית (ללא תלות בשירות חיצוני) בתוך generateMergedDataURL
                return window.location.origin + "/?pq=" + mediaId;
            }

            function loadImage(src) {
                return new Promise((resolve, reject) => {
                    const img = new Image();
                    img.crossOrigin = "Anonymous";
                    img.onload = () => resolve(img);
                    img.onerror = reject;
                    img.src = src;
                });
            }

            // ציור QR מקומי: כהה על לבן (ניגודיות מקסימלית לתאורת אירועים), אזור שקט, ולוגו קטן (<=20%) במרכז
            async function drawQRCode(ctx, text, x, y, size) {
                const qr = qrcode(0, 'H');
                qr.addData(text);
                qr.make();
                const count = qr.getModuleCount();
                const quiet = 3; // מודולים של שוליים לבנים
                const cell = size / (count + quiet * 2);
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(x, y, size, size);
                ctx.fillStyle = '#1a1a1a';
                for (let r = 0; r < count; r++) {
                    for (let c = 0; c < count; c++) {
                        if (qr.isDark(r, c)) {
                            ctx.fillRect(Math.floor(x + (c + quiet) * cell), Math.floor(y + (r + quiet) * cell), Math.ceil(cell), Math.ceil(cell));
                        }
                    }
                }
                if (LOGO_URL) {
                    try {
                        const logoImg = await loadImage(LOGO_URL);
                        const inner = size - quiet * 2 * cell;
                        const maxSide = inner * 0.2;
                        const ratio = logoImg.width / logoImg.height || 1;
                        const lw = ratio >= 1 ? maxSide : maxSide * ratio;
                        const lh = ratio >= 1 ? maxSide / ratio : maxSide;
                        const lx = x + (size - lw) / 2, ly = y + (size - lh) / 2;
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(lx - cell, ly - cell, lw + cell * 2, lh + cell * 2);
                        ctx.drawImage(logoImg, lx, ly, lw, lh);
                    } catch (e) { /* בלי לוגו - ה-QR עדיין תקין */ }
                }
            }

            function render() {
                const container = document.getElementById('content-container');
                if(uploadsData.length === 0) { container.innerHTML = '<p class="text-center text-gray-500 mt-10">אין תמונות עדיין. ממתין לאורחים...</p>'; return; }
                const dynamicImgStyle = `width: ${LAYOUT.w}%; height: ${LAYOUT.h}%; top: ${LAYOUT.t}%; left: ${LAYOUT.l}%;`;

                let html = '';
                if (currentView === 'grid') {
                    container.className = 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6';
                    uploadsData.forEach(item => {
                        const isPending = item.status === 'pending';
                        const isChecked = selectedItems.has(String(item.id)) ? 'checked' : '';
                        
                        html += `
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col item-animate relative group">
                                <div class="absolute top-2 right-2 bg-pquick-dark text-white font-bold w-8 h-8 rounded-full flex items-center justify-center shadow-md z-30 border-2 border-white">${item.copies}x</div>
                                <div class="absolute top-2 left-2 z-50 bg-white rounded-md shadow flex items-center justify-center w-8 h-8">
                                    <input type="checkbox" value="${item.id}" class="w-5 h-5 cursor-pointer text-pquick-orange focus:ring-pquick-orange border-gray-300 rounded" onchange="toggleSelection('${item.id}', this.checked)" ${isChecked}>
                                </div>
                                ${item.hasVideo ? `<div class="absolute top-2 left-12 bg-pquick-orange text-pquick-dark w-8 h-8 rounded-md flex items-center justify-center shadow-md z-30"><i class="fa-solid fa-video"></i></div>` : ''}
                                
                                <div class="relative bg-gray-100 overflow-hidden rounded-t-xl">
                                    <img src="${finalOverlay}" class="relative w-full h-auto z-20 pointer-events-none block object-contain">
                                    <img src="${item.image}" class="absolute z-10 object-cover" style="${dynamicImgStyle}">
                                    
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 z-40">
                                        <button class="bg-white text-pquick-dark font-bold py-2 px-3 rounded-full shadow-lg hover:bg-pquick-orange transition-colors" onclick="printItem('${item.id}', '${item.image}')" title="הדפס">
                                            <i class="fa-solid fa-print"></i>
                                        </button>
                                        <button class="bg-white text-pquick-dark font-bold py-2 px-3 rounded-full shadow-lg hover:bg-pquick-lightgreen transition-colors" onclick="downloadSingle('${item.id}', '${item.image}', this)" title="הורד תמונה עם מסגרת">
                                            <i class="fa-solid fa-download"></i>
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
                    html = `<table class="w-full text-right border-collapse"><thead><tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-sm">
                        <th class="p-4 w-12 text-center"></th>
                        <th class="p-4">תמונה מטופלת</th><th class="p-4">ID</th><th class="p-4">שעה</th><th class="p-4 text-center">וידאו</th><th class="p-4 text-center">העתקים</th><th class="p-4">סטטוס</th><th class="p-4 text-left">פעולה</th></tr></thead><tbody>`;
                    uploadsData.forEach(item => {
                        const isPending = item.status === 'pending';
                        const isChecked = selectedItems.has(String(item.id)) ? 'checked' : '';
                        html += `
                            <tr class="border-b border-gray-100 hover:bg-gray-50 item-animate">
                                <td class="p-4 text-center">
                                    <input type="checkbox" value="${item.id}" class="w-5 h-5 cursor-pointer rounded border-gray-300 text-pquick-orange" onchange="toggleSelection('${item.id}', this.checked)" ${isChecked}>
                                </td>
                                <td class="p-4">
                                    <div class="w-16 relative overflow-hidden rounded-md border border-gray-200 inline-block">
                                        <img src="${finalOverlay}" class="relative w-full h-auto z-20 pointer-events-none block object-contain">
                                        <img src="${item.image}" class="absolute z-10 object-cover" style="${dynamicImgStyle}">
                                    </div>
                                </td>
                                <td class="p-4 font-bold">#${item.id}</td><td class="p-4 text-gray-500">${item.time}</td>
                                <td class="p-4 text-center">${item.hasVideo ? '<i class="fa-solid fa-video text-pquick-orange"></i>' : '-'}</td>
                                <td class="p-4 text-center"><span class="bg-gray-100 font-bold px-3 py-1 rounded-full">${item.copies}</span></td>
                                <td class="p-4">${getStatusBadge(item.status)}</td>
                                <td class="p-4 text-left">
                                    <button onclick="downloadSingle('${item.id}', '${item.image}', this)" class="bg-white border text-gray-600 font-bold py-2 px-3 rounded-md shadow-sm mr-2" title="הורד תמונה"><i class="fa-solid fa-download"></i></button>
                                    <button onclick="printItem('${item.id}', '${item.image}')" class="${isPending ? 'bg-pquick-orange text-pquick-dark' : 'bg-white border text-gray-600'} font-bold py-2 px-4 rounded-md shadow-sm">הדפס עם QR</button>
                                </td>
                            </tr>`;
                    });
                    html += `</tbody></table>`;
                }
                container.innerHTML = html;
                updateStats();
            }

            async function getOverlayImage() {
                if (cachedOverlayImg) return cachedOverlayImg;
                return new Promise((resolve, reject) => {
                    const img = new Image();
                    img.crossOrigin = "Anonymous";
                    img.onload = () => { cachedOverlayImg = img; resolve(img); };
                    img.onerror = reject;
                    img.src = finalOverlay;
                });
            }

            // החזרת הפונקציה המקורית והמוכחת לחיבור קנבס עם QR חיצוני + תיקון קאש
            async function generateMergedDataURL(photoUrl, qrUrl = null) {
                const overlay = await getOverlayImage();
                return new Promise((resolve, reject) => {
                    const canvas = document.createElement('canvas');
                    // רזולוציית הדפסה מינימלית: 1800px רוחב (~300DPI בהדפסת 10x15). מסגרת SVG ללא מידות נטענת כ-150px בלבד!
                    const baseW = overlay.naturalWidth || overlay.width || 600;
                    const baseH = overlay.naturalHeight || overlay.height || 800;
                    const printScale = Math.max(1, 1800 / baseW);
                    canvas.width = Math.round(baseW * printScale);
                    canvas.height = Math.round(baseH * printScale);
                    const ctx = canvas.getContext('2d');

                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);

                    const photo = new Image();
                    photo.crossOrigin = "Anonymous";
                    
                    photo.onload = () => {
                        const targetX = (LAYOUT.l / 100) * canvas.width;
                        const targetY = (LAYOUT.t / 100) * canvas.height;
                        const targetW = (LAYOUT.w / 100) * canvas.width;
                        const targetH = (LAYOUT.h / 100) * canvas.height;
                        const bleed = 3; 
                        const finalX = targetX - bleed;
                        const finalY = targetY - bleed;
                        const finalW = targetW + (bleed * 2);
                        const finalH = targetH + (bleed * 2);

                        const imgRatio = photo.width / photo.height;
                        const destRatio = finalW / finalH;
                        let sX = 0, sY = 0, sW = photo.width, sH = photo.height;

                        if (imgRatio > destRatio) {
                            sW = photo.height * destRatio;
                            sX = (photo.width - sW) / 2;
                        } else {
                            sH = photo.width / destRatio;
                            sY = (photo.height - sH) / 2;
                        }

                        ctx.drawImage(photo, sX, sY, sW, sH, finalX, finalY, finalW, finalH);
                        ctx.drawImage(overlay, 0, 0, canvas.width, canvas.height);
                        
                        if (qrUrl) {
                            // QR בגודל 18% מרוחב ההדפסה (בהדפסת 10x15 ~ 1.8 ס"מ) בפינה התחתונה
                            const qrSize = Math.round(canvas.width * 0.18);
                            const margin = Math.round(canvas.width * 0.025);
                            drawQRCode(ctx, qrUrl, canvas.width - qrSize - margin, canvas.height - qrSize - margin, qrSize)
                                .then(() => resolve(canvas.toDataURL('image/jpeg', 0.95)))
                                .catch(() => resolve(canvas.toDataURL('image/jpeg', 0.95)));
                        } else {
                            resolve(canvas.toDataURL('image/jpeg', 0.95));
                        }
                    };
                    
                    photo.onerror = () => {
                        reject(new Error("CORS Error: S3 Bucket blocked the image download."));
                    };
                    
                    const sep = photoUrl.includes('?') ? '&' : '?';
                    photo.src = photoUrl + sep + 't=' + new Date().getTime();
                });
            }

            window.downloadSingle = async function(id, imageUrl, btnElement) {
                const originalHtml = btnElement.innerHTML;
                btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                try {
                    const dataUrl = await generateMergedDataURL(imageUrl);
                    const link = document.createElement('a');
                    link.href = dataUrl;
                    link.download = `Pquick_Photo_${id}.jpg`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } catch(e) { 
                    console.error(e);
                    alert("שגיאה בהורדת התמונה: חובה לאשר CORS בהגדרות ה-S3 של אמזון."); 
                }
                btnElement.innerHTML = originalHtml;
            };

            async function downloadMultiple(itemsToDownload) {
                if(itemsToDownload.length === 0) return;
                
                const zip = new JSZip();
                const folder = zip.folder("Pquick_Event_" + EVENT_ID);
                
                const progressEl = document.getElementById('download-progress-container');
                const progressText = document.getElementById('download-progress-text');
                const progressBar = document.getElementById('download-progress-bar');
                progressEl.classList.remove('hidden'); progressEl.classList.add('flex');
                
                let count = 0;
                for(const item of itemsToDownload) {
                    count++;
                    progressText.innerText = `מעבד תמונה ${count} מתוך ${itemsToDownload.length}...`;
                    progressBar.style.width = `${(count/itemsToDownload.length)*100}%`;
                    try {
                        const qrUrlText = generateQRCodeLink(item.id);
                        const dataUrl = await generateMergedDataURL(item.image, qrUrlText);
                        const base64Data = dataUrl.split(',')[1];
                        folder.file(`Pquick_Photo_${item.id}.jpg`, base64Data, {base64: true});
                    } catch(e) { console.error("Failed to merge image", item.id); }
                }
                
                progressText.innerText = "אורז את הקבצים לקובץ ZIP...";
                zip.generateAsync({type:"blob"}).then(function(content) {
                    saveAs(content, `Pquick_Event_${EVENT_ID}_Photos.zip`);
                    progressEl.classList.add('hidden'); progressEl.classList.remove('flex');
                    toggleAll(false); 
                });
            }

            // פונקציית ההדפסה הישנה והטובה 
            window.printItem = async function(id, imageUrl) {
                try {
                    const qrUrl = generateQRCodeLink(id);
                    const mergedDataUrl = await generateMergedDataURL(imageUrl, qrUrl);
                    
                    const printWindow = window.open('', '_blank');
                    if(!printWindow) {
                        alert("אנא אפשר חלונות קופצים (Pop-ups) כדי להדפיס.");
                        return;
                    }

                    const printHTML = `
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <title>Print #${id}</title>
                        <style>
                            @page { margin: 0; size: auto; }
                            html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: white; overflow: hidden; }
                            img { 
                                position: absolute;
                                top: 0;
                                left: 0;
                                width: 100%;
                                height: 100%;
                                object-fit: contain;
                                margin: 0;
                                padding: 0;
                            }
                        </style>
                    </head>
                    <body>
                        <img src="${mergedDataUrl}" onload="setTimeout(function() { window.print(); }, 200);">
                    </body>
                    </html>`;
                    printWindow.document.write(printHTML);
                    printWindow.document.close();
                    
                    const itemIndex = uploadsData.findIndex(item => item.id == id);
                    if (itemIndex > -1) {
                        uploadsData[itemIndex].status = 'printed';
                        render();
                        try { await fetch('/wp-json/pquick/v1/print/' + id, { method: 'POST', headers: API_HEADERS }); } catch(e) {}
                    }
                } catch(e) {
                    console.error(e);
                    alert("שגיאה בהכנת התמונה להדפסה.");
                }
            };

            document.getElementById('btn-view-grid').addEventListener('click', (e) => { currentView = 'grid'; e.currentTarget.classList.add('active','bg-pquick-dark','text-white'); document.getElementById('btn-view-list').classList.remove('active','bg-pquick-dark','text-white'); render(); });
            document.getElementById('btn-view-list').addEventListener('click', (e) => { currentView = 'list'; e.currentTarget.classList.add('active','bg-pquick-dark','text-white'); document.getElementById('btn-view-grid').classList.remove('active','bg-pquick-dark','text-white'); render(); });
        </script>
        </body>
        </html>
        <?php
    }

    // --- אפליקציה 3: סורק ה-AR ---
    // עקרונות החוויה:
    // 1. הפריים הראשון של הווידאו = בדיוק התמונה המודפסת (אותו רגע, אותו חיתוך) -> התמונה "מתעוררת לחיים" ולא מתחלפת.
    // 2. מסך פתיחה עם כפתור אחד: מסביר את בקשת המצלמה ומשחרר סאונד באייפון.
    // 3. מצב Sticky: כשהמעקב אובד הווידאו לא נעלם - הוא עובר לכרטיס צף וממשיך לנגן.
    // 4. אף פעם לא מבוי סתום: אם אין זיהוי אחרי כמה שניות - אפשר לצפות בסרטון במסך מלא.
    private function output_scanner_app( $event_id ) {
        if (!isset($_GET['media_id'])) {
            wp_die('קוד ה-QR לא תקין. חסר מזהה תמונה.', 'שגיאה', array('response' => 400));
        }

        $media_id = intval($_GET['media_id']);

        if ( get_post_type( $media_id ) !== 'pquick_media' || intval( get_post_meta( $media_id, '_pquick_parent_event', true ) ) !== intval( $event_id ) ) {
            wp_die('התמונה לא נמצאה באירוע זה.', 'שגיאה', array('response' => 404));
        }

        $status    = $this->refresh_ar_status( $media_id );
        $video_url = $status['videoUrl'];
        $mind_url  = get_post_meta($media_id, '_pquick_mind_url', true);
        $image_url = get_post_meta($media_id, '_pquick_image_url', true);

        if (empty($video_url)) {
            wp_die('לא נמצא וידאו עבור תמונה זו.', 'שגיאה', array('response' => 404));
        }

        $custom_logo = get_post_meta($event_id, '_pquick_event_logo', true);
        $logo_url = $custom_logo ? $custom_logo : '';
        $has_logo = ! empty( trim( $logo_url ) );

        // יחס המטרה המודפסת (רוחב/גובה). גרסאות ישנות - נופלים חזרה לפורמט ההדפסה של האירוע
        $target_aspect = floatval( get_post_meta( $media_id, '_pquick_target_aspect', true ) );
        if ( $target_aspect <= 0 ) {
            $print_format = floatval( get_post_meta($event_id, '_pquick_print_format', true) ?: '0.75' );
            $target_aspect = $print_format > 0 ? $print_format : 0.75;
        }
        $plane_width  = 1;
        $plane_height = 1 / $target_aspect;

        $frame_time = floatval( get_post_meta( $media_id, '_pquick_frame_time', true ) );
        $crop_json  = get_post_meta( $media_id, '_pquick_video_crop', true );
        $crop       = $crop_json ? json_decode( $crop_json, true ) : null;

        // פרמטרי מעקב (One Euro filter). ניתנים לכיול מהשטח: ?cf=0.0001&beta=0.01&miss=10
        $tracking = apply_filters( 'pquick_ar_tracking', array(
            'filterMinCF'     => 0.0001,
            'filterBeta'      => 0.01,
            'warmupTolerance' => 3,
            'missTolerance'   => 10,
        ) );
        if ( isset( $_GET['cf'] ) )   $tracking['filterMinCF']   = floatval( $_GET['cf'] );
        if ( isset( $_GET['beta'] ) ) $tracking['filterBeta']    = floatval( $_GET['beta'] );
        if ( isset( $_GET['miss'] ) ) $tracking['missTolerance'] = max( 1, intval( $_GET['miss'] ) );

        $has_target = ! empty( $mind_url );
        ?>
        <!DOCTYPE html>
        <html lang="he" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
            <title>Pquick AR - התמונה שלכם מתעוררת לחיים</title>
            <meta property="og:title" content="התמונה שלי התעוררה לחיים ✨">
            <?php if ( $image_url ): ?><meta property="og:image" content="<?php echo esc_url( $image_url ); ?>"><?php endif; ?>
            <link href="https://fonts.googleapis.com/css2?family=Alef:wght@400;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <?php if ( $has_target ): ?>
            <script src="https://aframe.io/releases/1.3.0/aframe.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/mind-ar@1.2.5/dist/mindar-image-aframe.prod.js"></script>
            <?php endif; ?>
            <style>
                :root { --pq-orange: #ffb800; --pq-dark: #454857; --pq-aspect: <?php echo esc_attr( $target_aspect ); ?>; }
                * { box-sizing: border-box; }
                body, html { margin: 0; padding: 0; width: 100vw; height: 100dvh; overflow: hidden; background-color: #000; font-family: 'Alef', sans-serif; position: fixed; inset: 0; color: #fff; -webkit-tap-highlight-color: transparent; }
                .a-enter-vr, .a-enter-ar { display: none !important; }
                .hidden { display: none !important; }

                #ar-container { width: 100vw; height: 100dvh; position: absolute; top: 0; left: 0; z-index: 1; }
                /* רק וידאו המצלמה (שמוזרק ע"י MindAR) נמתח למסך מלא - לא וידאו התוכן */
                #ar-container > video { object-fit: cover !important; width: 100vw !important; height: 100dvh !important; position: absolute !important; top: 0 !important; left: 0 !important; }

                .glass { background: rgba(0,0,0,0.55); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 6px 20px rgba(0,0,0,0.35); }
                .btn { pointer-events: auto; cursor: pointer; border: none; font-family: inherit; font-weight: 700; color: #fff; transition: transform .12s ease; }
                .btn:active { transform: scale(0.95); }

                /* --- מסך פתיחה --- */
                #intro { position: fixed; inset: 0; z-index: 200; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; text-align: center;
                         background: radial-gradient(120% 80% at 50% 0%, #5a5e70 0%, #2b2d36 55%, #15161b 100%); transition: opacity .4s ease; }
                #intro.fade-out { opacity: 0; pointer-events: none; }
                #intro-photo-wrap { position: relative; width: min(58vw, 260px); aspect-ratio: var(--pq-aspect); margin-bottom: 26px; transform: rotate(-3deg); }
                #intro-photo { width: 100%; height: 100%; object-fit: cover; border: 8px solid #fff; border-bottom-width: 22px; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.55); background: #222; }
                #intro-photo-wrap::after { content: ''; position: absolute; inset: 0; border-radius: 4px; pointer-events: none;
                         background: linear-gradient(115deg, transparent 30%, rgba(255,255,255,0.35) 45%, transparent 60%); background-size: 250% 100%; animation: shine 2.8s ease-in-out infinite; }
                @keyframes shine { 0% { background-position: 120% 0; } 60%, 100% { background-position: -120% 0; } }
                #intro h1 { font-size: 26px; margin: 0 0 8px; line-height: 1.25; }
                #intro p { margin: 0 0 26px; color: rgba(255,255,255,0.75); font-size: 15px; max-width: 320px; }
                #btn-start { background: var(--pq-orange); color: var(--pq-dark); font-size: 20px; padding: 16px 44px; border-radius: 999px; box-shadow: 0 0 0 0 rgba(255,184,0,0.7); animation: pulse 2s infinite; display: inline-flex; gap: 10px; align-items: center; }
                #btn-start[disabled] { animation: none; opacity: .8; }
                @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255,184,0,0.6); } 70% { box-shadow: 0 0 0 18px rgba(255,184,0,0); } 100% { box-shadow: 0 0 0 0 rgba(255,184,0,0); } }
                #intro small { display: block; margin-top: 16px; color: rgba(255,255,255,0.5); font-size: 12px; }
                .spinner { width: 18px; height: 18px; border: 3px solid rgba(69,72,87,0.3); border-top-color: var(--pq-dark); border-radius: 50%; animation: spin 0.9s linear infinite; }
                @keyframes spin { to { transform: rotate(360deg); } }

                /* --- שכבת UI מעל המצלמה --- */
                #scanner-ui { position: fixed; inset: 0; z-index: 100; pointer-events: none; }
                #logo-wrapper { position: absolute; top: max(16px, env(safe-area-inset-top)); right: 16px; padding: 8px 16px; border-radius: 50px; min-height: 44px; display: flex; align-items: center; }
                #logo-wrapper img { height: 28px; width: auto; display: block; }
                .brand-text { font-size: 18px; font-weight: bold; }
                .brand-text span { color: var(--pq-orange); }

                /* מסגרת כיוון בצורת ההדפסה */
                #scan-frame { position: absolute; left: 50%; top: 46%; transform: translate(-50%, -50%); width: min(70vw, 52dvh * var(--pq-aspect)); aspect-ratio: var(--pq-aspect); transition: opacity .35s ease, transform .35s ease; }
                #scan-frame.locked { opacity: 0; transform: translate(-50%, -50%) scale(0.92); }
                #scan-frame .ghost { position: absolute; inset: 0; background-size: cover; background-position: center; opacity: 0.22; border-radius: 6px; }
                #scan-frame .corner { position: absolute; width: 34px; height: 34px; border: 4px solid var(--pq-orange); filter: drop-shadow(0 0 6px rgba(255,184,0,0.6)); }
                #scan-frame .c-tl { top: -6px; left: -6px; border-right: none; border-bottom: none; border-top-left-radius: 12px; }
                #scan-frame .c-tr { top: -6px; right: -6px; border-left: none; border-bottom: none; border-top-right-radius: 12px; }
                #scan-frame .c-bl { bottom: -6px; left: -6px; border-right: none; border-top: none; border-bottom-left-radius: 12px; }
                #scan-frame .c-br { bottom: -6px; right: -6px; border-left: none; border-top: none; border-bottom-right-radius: 12px; }
                #scan-frame .laser { position: absolute; left: 4%; right: 4%; height: 2px; background: linear-gradient(90deg, transparent, var(--pq-orange), transparent); box-shadow: 0 0 12px var(--pq-orange); animation: laser 2.2s ease-in-out infinite; }
                @keyframes laser { 0%, 100% { top: 6%; } 50% { top: 94%; } }

                #hint-wrapper { position: absolute; bottom: calc(110px + env(safe-area-inset-bottom)); left: 50%; transform: translateX(-50%); width: calc(100% - 32px); max-width: 360px; text-align: center; transition: opacity .4s ease; }
                #hint { display: inline-block; padding: 12px 20px; border-radius: 50px; font-size: 15px; font-weight: bold; transition: opacity .25s ease; }
                #btn-fallback { margin-top: 10px; background: rgba(255,255,255,0.95); color: var(--pq-dark); padding: 10px 18px; border-radius: 50px; font-size: 14px; display: inline-flex; gap: 8px; align-items: center; }

                /* סרגל פעולות (אחרי זיהוי) */
                #action-bar { position: absolute; bottom: calc(24px + env(safe-area-inset-bottom)); left: 50%; transform: translateX(-50%); display: flex; gap: 10px; transition: opacity .3s ease, transform .3s ease; }
                #action-bar.off { opacity: 0; transform: translateX(-50%) translateY(20px); pointer-events: none; }
                #action-bar .btn { white-space: nowrap; height: 50px; min-width: 50px; padding: 0 18px; border-radius: 50px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 15px; }
                #btn-sound.on { background: #fff; color: var(--pq-dark); }
                #btn-save { background: var(--pq-orange); color: var(--pq-dark); border: none; }

                /* כרטיס צף (Sticky) / מסך מלא */
                #card { position: absolute; left: 50%; top: 44%; width: min(62vw, 46dvh * var(--pq-aspect)); aspect-ratio: var(--pq-aspect); transform: translate(-50%, -50%) scale(0.85); opacity: 0; transition: opacity .35s ease, transform .45s cubic-bezier(.2,1.4,.4,1), width .35s ease; pointer-events: none;
                        border: 7px solid #fff; border-bottom-width: 18px; border-radius: 4px; box-shadow: 0 24px 60px rgba(0,0,0,0.6); background: #111; }
                #card.show { opacity: 1; transform: translate(-50%, -50%) scale(1) rotate(-1.5deg); pointer-events: auto; }
                #card.full { width: min(92vw, 80dvh * var(--pq-aspect)); }
                #card canvas { width: 100%; height: 100%; display: block; }
                #card-hint { position: absolute; bottom: -52px; left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 13px; font-weight: bold; color: #fff; background: rgba(0,0,0,0.65); padding: 6px 14px; border-radius: 50px; }

                #toast { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); padding: 18px 26px; border-radius: 16px; text-align: center; font-weight: bold; max-width: 86vw; }

                #promo { position: absolute; top: max(16px, env(safe-area-inset-top)); left: 16px; padding: 0 14px; height: 44px; border-radius: 50px; display: flex; align-items: center; gap: 6px; font-size: 13px; color: #fff; text-decoration: none; pointer-events: auto; }
            </style>
        </head>
        <body>
            <!-- מסך פתיחה -->
            <div id="intro">
                <div id="intro-photo-wrap">
                    <?php if ( $image_url ): ?><img id="intro-photo" src="<?php echo esc_url( $image_url ); ?>" alt=""><?php else: ?><div id="intro-photo"></div><?php endif; ?>
                </div>
                <h1>התמונה שלכם עומדת<br>להתעורר לחיים ✨</h1>
                <?php if ( $has_target ): ?>
                    <p>החזיקו את התמונה המודפסת מול המצלמה, ותראו את הקסם קורה.</p>
                    <button id="btn-start" class="btn" disabled><span class="spinner"></span> <span id="btn-start-label">מכינים את הקסם...</span></button>
                    <small><i class="fa-solid fa-camera"></i> נבקש גישה למצלמה רק כדי לזהות את התמונה. שום דבר לא מצולם או נשמר.</small>
                <?php else: ?>
                    <p>הסרטון מהרגע שלכם מוכן לצפייה.</p>
                    <button id="btn-start" class="btn"><i class="fa-solid fa-play"></i> <span id="btn-start-label">צפו ברגע</span></button>
                <?php endif; ?>
            </div>

            <div id="scanner-ui">
                <div id="logo-wrapper" class="glass">
                    <?php if($has_logo): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" data-no-lazy="1" data-skip-lazy="1" loading="eager" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <span class="brand-text" style="display:none;">Pquick<span>AR</span></span>
                    <?php else: ?>
                        <span class="brand-text">Pquick<span>AR</span></span>
                    <?php endif; ?>
                </div>
                <a id="promo" class="glass" href="https://pquick.co.il/events/" target="_blank" rel="noopener"><i class="fa-solid fa-wand-magic-sparkles" style="color:#ffb800"></i> רוצים כזה באירוע?</a>

                <div id="scan-frame" class="hidden">
                    <div class="ghost" <?php if ( $image_url ): ?>style="background-image:url('<?php echo esc_url( $image_url ); ?>')"<?php endif; ?>></div>
                    <div class="corner c-tl"></div><div class="corner c-tr"></div><div class="corner c-bl"></div><div class="corner c-br"></div>
                    <div class="laser"></div>
                </div>

                <div id="card"><canvas id="card-canvas"></canvas><div id="card-hint" class="hidden">כוונו שוב לתמונה המודפסת ✨</div></div>

                <div id="hint-wrapper" class="hidden">
                    <div id="hint" class="glass">כוונו את המצלמה לתמונה המודפסת</div><br>
                    <button id="btn-fallback" class="btn hidden"><i class="fa-solid fa-circle-play"></i> לא מצליחים? צפו בסרטון</button>
                </div>

                <div id="toast" class="glass hidden"></div>

                <div id="action-bar" class="off">
                    <button id="btn-sound" class="btn glass"><i class="fa-solid fa-volume-xmark"></i></button>
                    <button id="btn-share" class="btn glass"><i class="fa-solid fa-arrow-up-from-bracket"></i> שתפו</button>
                    <button id="btn-save" class="btn"><i class="fa-solid fa-download"></i> שמרו סרטון</button>
                </div>
            </div>

            <!-- וידאו התוכן (משמש גם כטקסטורה ב-AR וגם לכרטיס הצף) -->
            <video id="ar-video" src="<?php echo esc_url($video_url); ?>" loop crossorigin="anonymous" playsinline webkit-playsinline muted preload="auto" style="display:none"></video>

            <?php if ( $has_target ): ?>
            <div id="ar-container">
                <a-scene mindar-image="imageTargetSrc: <?php echo esc_url($mind_url); ?>; autoStart: false; uiScanning: no; uiLoading: no; uiError: no; maxTrack: 1; filterMinCF: <?php echo esc_attr( $tracking['filterMinCF'] ); ?>; filterBeta: <?php echo esc_attr( $tracking['filterBeta'] ); ?>; warmupTolerance: <?php echo esc_attr( $tracking['warmupTolerance'] ); ?>; missTolerance: <?php echo esc_attr( $tracking['missTolerance'] ); ?>;" color-space="sRGB" renderer="colorManagement: true; antialias: true" vr-mode-ui="enabled: false" device-orientation-permission-ui="enabled: false" loading-screen="enabled: false">
                    <?php if ( $image_url ): ?>
                    <a-assets timeout="8000">
                        <img id="poster-img" src="<?php echo esc_url( $image_url ); ?>" crossorigin="anonymous">
                    </a-assets>
                    <?php endif; ?>
                    <a-camera position="0 0 0" look-controls="enabled: false"></a-camera>
                    <a-entity mindar-image-target="targetIndex: 0" id="target-entity">
                        <!-- הווידאו צמוד לנייר (z קטן מאוד = בלי פרלקסה) ובגודל 1:1 עם ההדפסה -->
                        <a-plane id="ar-plane" width="<?php echo esc_attr($plane_width); ?>" height="<?php echo esc_attr($plane_height); ?>" position="0 0 0.001" material="shader: flat; transparent: true; opacity: 0; color: #fff"></a-plane>
                        <!-- התמונה עצמה מעל הווידאו: מופיעה מיד בזיהוי ונמסה לתוך הווידאו כשהוא מתחיל לזוז -->
                        <a-plane id="poster-plane" width="<?php echo esc_attr($plane_width); ?>" height="<?php echo esc_attr($plane_height); ?>" position="0 0 0.002" material="shader: flat; transparent: true; opacity: 0; <?php echo $image_url ? 'src: #poster-img' : 'color: #fff'; ?>"></a-plane>
                    </a-entity>
                </a-scene>
            </div>
            <?php endif; ?>

            <script>
            (() => {
                const CFG = {
                    mediaId: <?php echo intval( $media_id ); ?>,
                    hasTarget: <?php echo $has_target ? 'true' : 'false'; ?>,
                    arReady: <?php echo ! empty( $status['ready'] ) ? 'true' : 'false'; ?>,
                    statusUrl: <?php echo wp_json_encode( rest_url( 'pquick/v1/ar-status/' . $media_id ) ); ?>,
                    frameTime: <?php echo floatval( $frame_time ); ?>,
                    crop: <?php echo $crop ? wp_json_encode( $crop ) : 'null'; ?>,
                    aspect: <?php echo floatval( $target_aspect ); ?>,
                    videoUrl: <?php echo wp_json_encode( $video_url ); ?>,
                    fallbackAfterMs: 8000,
                    stickyAfterMs: 700
                };

                const $ = (id) => document.getElementById(id);
                const videoEl = $('ar-video');
                const intro = $('intro'), btnStart = $('btn-start'), btnStartLabel = $('btn-start-label');
                const scanFrame = $('scan-frame'), hintWrapper = $('hint-wrapper'), hint = $('hint'), btnFallback = $('btn-fallback');
                const card = $('card'), cardCanvas = $('card-canvas'), cardHint = $('card-hint');
                const actionBar = $('action-bar'), btnSound = $('btn-sound'), btnShare = $('btn-share'), btnSave = $('btn-save');
                const toast = $('toast');

                let started = false, targetVisible = false, everFound = false, cardMode = null; // cardMode: null | 'sticky' | 'full'
                let hintTimer = null, fallbackTimer = null, stickyTimer = null, firstPlayDone = false;

                const showToast = (html, ms = 2500) => { toast.innerHTML = html; toast.classList.remove('hidden'); clearTimeout(showToast._t); showToast._t = setTimeout(() => toast.classList.add('hidden'), ms); };
                const haptic = (ms) => { try { if (navigator.vibrate) navigator.vibrate(ms); } catch (e) {} };

                // ---------- חיתוך הווידאו בדיוק כמו ההדפסה ----------
                const getCrop = () => {
                    if (CFG.crop && CFG.crop.w > 0 && CFG.crop.h > 0) return CFG.crop;
                    // גרסאות ישנות: חיתוך מרכזי (object-fit: cover)
                    const va = (videoEl.videoWidth || 16) / (videoEl.videoHeight || 9);
                    if (va > CFG.aspect) { const w = CFG.aspect / va; return { x: (1 - w) / 2, y: 0, w, h: 1 }; }
                    const h = va / CFG.aspect; return { x: 0, y: (1 - h) / 2, w: 1, h };
                };

                const seekToFrame = () => {
                    try { if (videoEl.duration && CFG.frameTime < videoEl.duration) videoEl.currentTime = CFG.frameTime; } catch (e) {}
                };
                videoEl.addEventListener('loadedmetadata', () => { seekToFrame(); applyVideoTexture(); }, { once: true });
                videoEl.load();

                // ---------- מעברים חלקים (opacity tween) על חומרי three.js ----------
                const tweens = new Map();
                const fadeTo = (entity, to, dur = 300) => {
                    if (!entity) return;
                    const mesh = entity.getObject3D('mesh');
                    if (!mesh) { entity.addEventListener('loaded', () => fadeTo(entity, to, dur), { once: true }); return; }
                    const mat = mesh.material, from = mat.opacity, t0 = performance.now();
                    const token = {};
                    tweens.set(entity, token); // טווין חדש מבטל את הקודם
                    const step = (now) => {
                        if (tweens.get(entity) !== token) return;
                        const k = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - k, 3);
                        mat.opacity = from + (to - from) * e;
                        mat.needsUpdate = true;
                        if (k < 1) requestAnimationFrame(step); else tweens.delete(entity);
                    };
                    requestAnimationFrame(step);
                };

                let videoTexture = null;
                function applyVideoTexture() {
                    const plane = $('ar-plane');
                    const THREE = (window.AFRAME && window.AFRAME.THREE) || window.THREE;
                    if (!plane || !THREE || !videoEl.videoWidth) return;
                    const mesh = plane.getObject3D('mesh');
                    if (!mesh) { plane.addEventListener('loaded', applyVideoTexture, { once: true }); return; }
                    if (!videoTexture) {
                        videoTexture = new THREE.VideoTexture(videoEl);
                        videoTexture.minFilter = THREE.LinearFilter;
                        videoTexture.magFilter = THREE.LinearFilter;
                        if (THREE.sRGBEncoding) videoTexture.encoding = THREE.sRGBEncoding;
                    }
                    const c = getCrop();
                    videoTexture.repeat.set(c.w, c.h);
                    videoTexture.offset.set(c.x, 1 - c.y - c.h); // UV מתחיל מלמטה
                    mesh.material.map = videoTexture;
                    mesh.material.color && mesh.material.color.set('#ffffff');
                    mesh.material.needsUpdate = true;
                }

                // ---------- כרטיס צף (Sticky) ומסך מלא ----------
                let cardRaf = null;
                const drawCard = () => {
                    if (!cardMode) { cardRaf = null; return; }
                    const rect = cardCanvas.getBoundingClientRect();
                    const dpr = Math.min(2, window.devicePixelRatio || 1);
                    const W = Math.round(rect.width * dpr), H = Math.round(rect.height * dpr);
                    if (cardCanvas.width !== W || cardCanvas.height !== H) { cardCanvas.width = W; cardCanvas.height = H; }
                    if (videoEl.videoWidth) {
                        const c = getCrop();
                        cardCanvas.getContext('2d').drawImage(videoEl, c.x * videoEl.videoWidth, c.y * videoEl.videoHeight, c.w * videoEl.videoWidth, c.h * videoEl.videoHeight, 0, 0, W, H);
                    }
                    cardRaf = requestAnimationFrame(drawCard);
                };
                const showCard = (mode) => {
                    cardMode = mode;
                    card.classList.toggle('full', mode === 'full');
                    cardHint.classList.toggle('hidden', mode !== 'sticky' || !CFG.hasTarget);
                    card.classList.add('show');
                    if (!cardRaf) cardRaf = requestAnimationFrame(drawCard);
                };
                const hideCard = () => { cardMode = null; card.classList.remove('show'); };

                // ---------- ניגון ----------
                const playVideo = () => videoEl.play().catch(() => {
                    // אם הדפדפן חסם ניגון עם סאונד - ננגן מושתק ונציע להפעיל סאונד
                    if (!videoEl.muted) { videoEl.muted = true; updateSoundBtn(); return videoEl.play().catch(() => {}); }
                });
                videoEl.addEventListener('playing', () => {
                    // מתעלמים מהניגון הקצר של שחרור הסאונד - רק ניגון "אמיתי" אחרי זיהוי/כרטיס
                    if (!firstPlayDone && (everFound || cardMode)) {
                        firstPlayDone = true;
                        // התמונה ה"קפואה" נמסה לתוך הווידאו שמתחיל לזוז - רגע ה"וואו"
                        setTimeout(() => fadeTo($('poster-plane'), 0, 600), 120);
                    }
                });

                const updateSoundBtn = () => {
                    btnSound.classList.toggle('on', !videoEl.muted);
                    btnSound.innerHTML = videoEl.muted ? '<i class="fa-solid fa-volume-xmark"></i>' : '<i class="fa-solid fa-volume-high"></i>';
                };
                btnSound.addEventListener('click', () => { videoEl.muted = !videoEl.muted; updateSoundBtn(); if (!videoEl.muted) playVideo(); });

                // ---------- רמזים מתחלפים ----------
                const HINTS = [
                    'כוונו את המצלמה לתמונה המודפסת',
                    'התרחקו מעט: 20-30 ס"מ מהתמונה',
                    'הטו קצת את התמונה כדי להימנע מהשתקפות',
                    'חשוך? התקרבו למקור אור 💡'
                ];
                const startHints = () => {
                    let i = 0;
                    hint.textContent = HINTS[0];
                    clearInterval(hintTimer);
                    hintTimer = setInterval(() => {
                        i = (i + 1) % HINTS.length;
                        hint.style.opacity = 0;
                        setTimeout(() => { hint.textContent = HINTS[i]; hint.style.opacity = 1; }, 250);
                    }, 3200);
                    clearTimeout(fallbackTimer);
                    fallbackTimer = setTimeout(() => { if (!targetVisible) btnFallback.classList.remove('hidden'); }, CFG.fallbackAfterMs);
                };

                const enterScanning = () => {
                    scanFrame.classList.remove('hidden', 'locked');
                    hintWrapper.classList.remove('hidden');
                    hintWrapper.style.opacity = 1;
                    startHints();
                };

                btnFallback.addEventListener('click', () => {
                    btnFallback.classList.add('hidden');
                    scanFrame.classList.add('locked');
                    hintWrapper.style.opacity = 0;
                    showCard('full');
                    actionBar.classList.remove('off');
                    playVideo();
                });

                // ---------- אירועי מעקב ----------
                const onFound = () => {
                    targetVisible = true;
                    clearTimeout(stickyTimer);
                    clearTimeout(fallbackTimer);
                    btnFallback.classList.add('hidden');
                    scanFrame.classList.add('locked');
                    hintWrapper.style.opacity = 0;
                    if (cardMode) hideCard();
                    if (!everFound) {
                        everFound = true;
                        haptic([20, 40, 30]);
                        // הצגה מיידית של התמונה עצמה צמודה להדפסה, ואז הווידאו "מתעורר"
                        fadeTo($('poster-plane'), firstPlayDone ? 0 : 1, 220);
                    }
                    fadeTo($('ar-plane'), 1, 250);
                    actionBar.classList.remove('off');
                    playVideo();
                };

                const onLost = () => {
                    targetVisible = false;
                    clearTimeout(stickyTimer);
                    // לא מעלימים מיד: איבוד רגעי (רעד/השתקפות) לא אמור להבהב
                    stickyTimer = setTimeout(() => {
                        if (targetVisible) return;
                        fadeTo($('ar-plane'), 0, 200);
                        fadeTo($('poster-plane'), 0, 200);
                        if (firstPlayDone || !videoEl.paused) showCard('sticky'); // הווידאו ממשיך לנגן בכרטיס צף
                        else enterScanning();
                    }, CFG.stickyAfterMs);
                };

                // ---------- התחלה ----------
                const unlockAudio = () => {
                    // הקשה של המשתמש מאפשרת ניגון עם סאונד אח"כ (iOS). מנגנים ועוצרים מיד.
                    videoEl.muted = false;
                    const p = videoEl.play();
                    if (p && p.then) {
                        p.then(() => { if (!targetVisible && !cardMode) { videoEl.pause(); seekToFrame(); } updateSoundBtn(); })
                         .catch(() => { videoEl.muted = true; updateSoundBtn(); });
                    }
                };

                const startAR = async () => {
                    const sceneEl = document.querySelector('a-scene');
                    const go = async () => {
                        const sys = sceneEl.systems['mindar-image-system'];
                        sceneEl.addEventListener('arError', () => {
                            showToast('<i class="fa-solid fa-camera-slash"></i><br>לא קיבלנו גישה למצלמה.<br><span style="font-weight:normal;font-size:13px">אפשרו גישה בהגדרות הדפדפן, או צפו בסרטון עכשיו.</span>', 5000);
                            btnFallback.classList.remove('hidden');
                            hintWrapper.classList.remove('hidden');
                        });
                        const target = $('target-entity');
                        target.addEventListener('targetFound', onFound);
                        target.addEventListener('targetLost', onLost);
                        applyVideoTexture();
                        try { await sys.start(); } catch (e) { console.warn(e); }
                        enterScanning();
                    };
                    if (sceneEl.hasLoaded) go(); else sceneEl.addEventListener('loaded', go, { once: true });
                };

                btnStart.addEventListener('click', () => {
                    if (started || btnStart.disabled) return;
                    started = true;
                    unlockAudio();
                    intro.classList.add('fade-out');
                    setTimeout(() => intro.classList.add('hidden'), 450);
                    if (CFG.hasTarget) startAR();
                    else { showCard('full'); actionBar.classList.remove('off'); videoEl.muted = false; seekToFrame(); playVideo(); }
                });

                // ---------- המתנה עד שקובץ ה-AR מוכן (מניעת "מצלמה שלא מזהה כלום") ----------
                const setReady = () => {
                    btnStart.disabled = false;
                    btnStart.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> <span>התחילו</span>';
                };
                if (CFG.hasTarget) {
                    if (CFG.arReady) setReady();
                    else {
                        const t0 = Date.now();
                        const poll = async () => {
                            try {
                                const r = await fetch(CFG.statusUrl, { cache: 'no-store' });
                                const d = await r.json();
                                if (d.ready) {
                                    if (d.videoUrl && d.videoUrl !== CFG.videoUrl) { CFG.videoUrl = d.videoUrl; videoEl.src = d.videoUrl; videoEl.load(); videoEl.addEventListener('loadedmetadata', () => { seekToFrame(); applyVideoTexture(); }, { once: true }); }
                                    return setReady();
                                }
                            } catch (e) {}
                            if (Date.now() - t0 > 90000) {
                                // לא מוכן אחרי 90 שניות - לא משאירים את האורח תקוע
                                CFG.hasTarget = false;
                                btnStart.disabled = false;
                                btnStart.innerHTML = '<i class="fa-solid fa-play"></i> <span>צפו ברגע עכשיו</span>';
                                return;
                            }
                            setTimeout(poll, 2000);
                        };
                        poll();
                    }
                }

                // ---------- שיתוף ושמירה (לולאה ויראלית) ----------
                btnShare.addEventListener('click', async () => {
                    const shareData = { title: 'Pquick AR', text: 'וואו! התמונה מהאירוע התעוררה לחיים ✨ סרקו גם אתם:', url: window.location.href };
                    if (navigator.share) { try { await navigator.share(shareData); } catch (err) {} }
                    else { try { await navigator.clipboard.writeText(window.location.href); showToast('הקישור הועתק ✓'); } catch (e) {} }
                });

                btnSave.addEventListener('click', async () => {
                    const orig = btnSave.innerHTML;
                    btnSave.innerHTML = '<span class="spinner"></span>';
                    try {
                        const res = await fetch(CFG.videoUrl, { mode: 'cors' });
                        const blob = await res.blob();
                        const ext = (blob.type.split('/')[1] || 'mp4').replace('quicktime', 'mov');
                        const file = new File([blob], 'pquick-moment.' + ext, { type: blob.type || 'video/mp4' });
                        if (navigator.canShare && navigator.canShare({ files: [file] })) {
                            await navigator.share({ files: [file], title: 'הרגע שלי מהאירוע ✨' });
                        } else {
                            const a = document.createElement('a');
                            a.href = URL.createObjectURL(blob); a.download = file.name;
                            document.body.appendChild(a); a.click(); a.remove();
                            setTimeout(() => URL.revokeObjectURL(a.href), 5000);
                        }
                    } catch (e) {
                        window.open(CFG.videoUrl, '_blank');
                    }
                    btnSave.innerHTML = orig;
                });

                // כשהאפליקציה חוזרת מרקע - ממשיכים לנגן אם צריך
                document.addEventListener('visibilitychange', () => { if (!document.hidden && (targetVisible || cardMode)) playVideo(); });
            })();
            </script>
        </body>
        </html>
        <?php
    }
}

new Pquick_AR_Core();
