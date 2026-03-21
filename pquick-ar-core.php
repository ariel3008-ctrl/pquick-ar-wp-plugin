<?php
/**
 * Plugin Name: Pquick AR Core
 * Description: מערכת הליבה. כוללת קימפול מציאות רבודה (AR) ישירות בדפדפן של האורח (Client-Side), העלאה ישירה ל-AWS S3, וסורק דינמי.
 * Version: 17.0.0
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
            <p>כדי לשמור על מהירות האתר ולמנוע קריסה באירועים, כל התמונות וסרטוני הוידאו של האורחים יטוסו ישירות לענן של Amazon.</p>
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
        $operator_url = add_query_arg( array( 'pquick_app' => 'operator', 'event_id' => $post->ID ), $site_url );
        
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
            <p style="font-size:11px; color:#666;">הערה: ה-QR לסורק יווצר אוטומטית על ידי המפעיל על כל תמונה.</p>
        </div>
        <?php
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

    // פונקציית העלאה מאובטחת ל-AWS S3 בשיטת Signature V4
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
        register_rest_route( 'pquick/v1', '/event-media/(?P<event_id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_event_media' ), 'permission_callback' => '__return_true' ) );
        register_rest_route( 'pquick/v1', '/print/(?P<media_id>\d+)', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'update_print_status' ), 'permission_callback' => '__return_true' ) );
    }

    public function handle_guest_upload( WP_REST_Request $request ) {
        $event_id = $request->get_param( 'event_id' );
        $copies = $request->get_param( 'copies' ) ? intval( $request->get_param( 'copies' ) ) : 1;
        $image_base64 = $request->get_param( 'image_base64' ); 
        
        if ( ! $event_id || ! $image_base64 ) return new WP_Error( 'missing_data', 'חסרים נתונים חיוניים', array( 'status' => 400 ) );

        $global_limit = get_post_meta($event_id, '_pquick_global_max_uploads', true);
        if ( !empty($global_limit) && intval($global_limit) > 0 ) {
            $current_count = count(get_posts(array('post_type' => 'pquick_media', 'meta_key' => '_pquick_parent_event', 'meta_value' => $event_id, 'posts_per_page' => -1, 'fields' => 'ids')));
            if ( $current_count >= intval($global_limit) ) {
                return new WP_Error( 'limit_reached', 'האירוע הגיע למכסת ההדפסות המקסימלית שלו.', array( 'status' => 403 ) );
            }
        }

        // הגדרות AWS
        $aws_bucket = get_option('pquick_aws_bucket');
        $aws_region = get_option('pquick_aws_region');
        $aws_access = get_option('pquick_aws_access_key');
        $aws_secret = get_option('pquick_aws_secret_key');
        $use_aws = ($aws_bucket && $aws_region && $aws_access && $aws_secret);

        $time_stamp = time();
        $upload_dir = wp_upload_dir();
        
        // --- 1. שמירת תמונה (JPG) ---
        $image_parts = explode(";base64,", $image_base64);
        $image_base64_decoded = base64_decode($image_parts[1]);
        $image_name = 'pquick_evt_' . $event_id . '_' . $time_stamp . '.jpg';
        $temp_image_path = $upload_dir['path'] . '/' . $image_name;
        file_put_contents($temp_image_path, $image_base64_decoded);
        
        $final_image_url = '';
        if ($use_aws) {
            $s3_path = 'events/' . $event_id . '/' . $image_name;
            $s3_url = $this->upload_to_s3($temp_image_path, $s3_path, 'image/jpeg', $aws_bucket, $aws_region, $aws_access, $aws_secret);
            if ($s3_url) { $final_image_url = $s3_url; unlink($temp_image_path); } 
            else { $final_image_url = $upload_dir['url'] . '/' . $image_name; }
        } else {
            $final_image_url = $upload_dir['url'] . '/' . $image_name;
        }

        // --- 2. שמירת קובץ ה-Mind שקומפל בדפדפן ---
        $mind_file = $request->get_file_params()['mind_file'] ?? null;
        $final_mind_url = '';
        if ( $mind_file && $mind_file['error'] === UPLOAD_ERR_OK ) {
            $mind_name = 'pquick_evt_' . $event_id . '_' . $time_stamp . '.mind';
            if ($use_aws) {
                $s3_mind_path = 'events/' . $event_id . '/' . $mind_name;
                $s3_m_url = $this->upload_to_s3($mind_file['tmp_name'], $s3_mind_path, 'application/octet-stream', $aws_bucket, $aws_region, $aws_access, $aws_secret);
                if ($s3_m_url) { $final_mind_url = $s3_m_url; }
            } else {
                $movefile = wp_handle_upload( $mind_file, array( 'test_form' => false ) );
                if ( $movefile && ! isset( $movefile['error'] ) ) { $final_mind_url = $movefile['url']; }
            }
        }

        // --- 3. טיפול בוידאו ---
        $video_file = $request->get_file_params()['video_file'] ?? null;
        $final_video_url = '';
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

        // יצירת הרשומה ב-DB
        $post_id = wp_insert_post( array( 'post_type' => 'pquick_media', 'post_title' => 'Upload #' . $time_stamp, 'post_status' => 'publish' ) );
        update_post_meta( $post_id, '_pquick_parent_event', $event_id );
        update_post_meta( $post_id, '_pquick_image_url', $final_image_url );
        update_post_meta( $post_id, '_pquick_video_url', $final_video_url );
        update_post_meta( $post_id, '_pquick_mind_url', $final_mind_url );
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

    public function render_frontend_apps() {
        if ( ! isset( $_GET['pquick_app'] ) ) return;
        
        $app = sanitize_text_field( $_GET['pquick_app'] );
        $event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : 0;
        
        if ( ! $event_id || get_post_type( $event_id ) !== 'pquick_event' ) {
            wp_die( 'אירוע לא חוקי או לא קיים.', 'שגיאה', array( 'response' => 404 ) );
        }

        if ( $app === 'upload' ) { $this->output_guest_app( $event_id ); exit; } 
        elseif ( $app === 'operator' ) { $this->output_operator_app( $event_id ); exit; } 
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
            <script src="https://cdn.jsdelivr.net/npm/mind-ar@1.2.5/dist/mindar-image.prod.js"></script>
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
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Event Logo" class="h-10 w-auto object-contain shrink-0 drop-shadow-sm skip-lazy" data-no-lazy="1" data-skip-lazy="1" style="min-width: 60px; max-width: 150px;">
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
                    <div class="flex gap-3 mt-auto pb-10">
                        <button id="btn-cancel-crop" class="flex-1 bg-white border-2 border-gray-200 text-pquick-dark font-bold py-3 rounded-full transition-colors hover:bg-gray-50">ביטול</button>
                        <button id="btn-save-crop" class="flex-[2] bg-pquick-orange text-pquick-dark font-bold py-3 rounded-full shadow-lg hover:opacity-90 transition-opacity">חתוך ושמור <i class="fa-solid fa-crop-simple ml-2"></i></button>
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
                        <i class="fa-solid fa-microchip text-5xl text-pquick-pink mb-4 animate-pulse" id="loading-icon"></i>
                        <h2 class="text-xl font-bold" id="loading-title">מכין את קסם ה-AR...</h2>
                        <p class="text-gray-500 text-sm mt-2" id="loading-subtitle">משתמש בכוח העיבוד של המכשיר שלך, אנא המתן ולא לסגור את המסך!</p>
                        <div class="progress-container"><div id="upload-progress-bar" class="progress-bar"></div></div>
                        <p id="upload-progress-text" class="text-pquick-dark font-bold mt-2">0%</p>
                    </div>
                </div>

                <div id="step-success" class="step text-center">
                    <div class="w-20 h-20 bg-pquick-lightgreen rounded-full flex items-center justify-center mx-auto mb-4 text-pquick-dark text-4xl shadow-md mt-4"><i class="fa-solid fa-check"></i></div>
                    <h1 class="text-2xl font-bold mb-2">התמונה נשלחה להדפסה!</h1>
                    
                    <div class="bg-pquick-light rounded-xl p-5 text-right my-6 border border-gray-200 shadow-sm">
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

                    <button onclick="location.reload()" class="w-full bg-white border-2 border-pquick-dark text-pquick-dark font-bold py-3 rounded-full hover:bg-gray-50 mb-6 transition-colors">
                        העלה תמונה נוספת
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

            if (!EVENT_DATA.overlayUrl) {
                EVENT_DATA.overlayUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 800"><path d="M0 0h600v800H0z M30 30v650h540V30z" fill="#ffffff" fill-rule="evenodd"/><text x="300" y="730" font-family="Alef, sans-serif" font-size="40" fill="#ffb800" font-weight="bold" text-anchor="middle">${EVENT_DATA.name}</text></svg>`);
            }

            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('welcome-event-name').textContent = EVENT_DATA.name;
                document.getElementById('preview-overlay-element').src = EVENT_DATA.overlayUrl;
                document.getElementById('max-qty-num').textContent = EVENT_DATA.maxCopies;
                
                const inputImage = document.getElementById('input-image');
                const inputVideo = document.getElementById('input-video');
                const imageToCropElement = document.getElementById('image-to-crop');
                const previewImgElement = document.getElementById('preview-img-element');
                
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
                            cropper = new Cropper(imageToCropElement, { aspectRatio: EVENT_DATA.printFormat, viewMode: 1, dragMode: 'move', autoCropArea: 1, guides: true, center: true, highlight: false, cropBoxMovable: false, cropBoxResizable: false, toggleDragModeOnDblclick: false });
                        }
                        reader.readAsDataURL(this.files[0]);
                    }
                });

                document.getElementById('btn-cancel-crop').addEventListener('click', () => {
                    if (cropper) cropper.destroy(); inputImage.value = ''; showStep('step-upload');
                });

                document.getElementById('btn-save-crop').addEventListener('click', () => {
                    if (!cropper) return;
                    const exportWidth = 1000;
                    const exportHeight = exportWidth / EVENT_DATA.printFormat;
                    const canvas = cropper.getCroppedCanvas({ width: exportWidth, height: exportHeight });
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

                document.getElementById('btn-submit').addEventListener('click', async () => {
                    showStep('step-loading');
                    
                    let mindBlob = null;
                    try {
                        // --- קסם הקימפול בדפדפן של הלקוח ---
                        const compiler = new window.MINDAR.IMAGE.Compiler();
                        const img = new Image();
                        img.src = croppedImageDataUrl;
                        await new Promise((resolve) => { img.onload = resolve; });
                        
                        await compiler.compileImageTargets([img], (progress) => {
                            document.getElementById('upload-progress-bar').style.width = progress.toFixed(2) + '%';
                            document.getElementById('upload-progress-text').textContent = progress.toFixed(2) + '%';
                        });
                        
                        const exportedBuffer = await compiler.exportData();
                        mindBlob = new Blob([exportedBuffer], { type: 'application/octet-stream' });
                    } catch (err) {
                        alert('שגיאה ביצירת קובץ ה-AR. נסה מדפדפן כרום או ספארי רגיל (לא דרך אינסטגרם או פייסבוק).');
                        showStep('step-preview');
                        return;
                    }

                    // --- סיום קימפול, מתחיל העלאה ---
                    document.getElementById('loading-icon').className = 'fa-solid fa-cloud-arrow-up text-5xl text-pquick-orange mb-4 animate-bounce';
                    document.getElementById('loading-title').textContent = 'מעלה קבצים לענן...';
                    document.getElementById('loading-subtitle').textContent = 'מגבה הכל באמזון, אנא המתן';
                    document.getElementById('upload-progress-bar').style.width = '0%';
                    document.getElementById('upload-progress-text').textContent = '0%';

                    const formData = new FormData();
                    formData.append('event_id', EVENT_DATA.id);
                    formData.append('copies', currentQty);
                    formData.append('image_base64', croppedImageDataUrl);
                    if (mindBlob) formData.append('mind_file', mindBlob, 'target.mind');
                    if (inputVideo.files && inputVideo.files[0]) formData.append('video_file', inputVideo.files[0]);

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
            
            let finalOverlay = OVERLAY_URL;
            if (!finalOverlay) {
                finalOverlay = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 800"><path d="M0 0h600v800H0z M30 30v650h540V30z" fill="#ffffff" fill-rule="evenodd"/><text x="300" y="730" font-family="Alef, sans-serif" font-size="40" fill="#ffb800" font-weight="bold" text-anchor="middle"><?php echo esc_js($event_name); ?></text></svg>`);
            }

            let uploadsData = [];
            let currentView = 'grid';
            let selectedItems = new Set();
            let cachedOverlayImg = null;
            
            async function fetchMedia() {
                try {
                    const res = await fetch('/wp-json/pquick/v1/event-media/' + EVENT_ID);
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
                const url = window.location.origin + window.location.pathname + "?pquick_app=scanner&media_id=" + mediaId;
                return 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&color=ffb800&data=' + encodeURIComponent(url);
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

            async function generateMergedDataURL(photoUrl, qrUrl = null) {
                const overlay = await getOverlayImage();
                return new Promise((resolve, reject) => {
                    const canvas = document.createElement('canvas');
                    canvas.width = overlay.width;
                    canvas.height = overlay.height;
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
                        
                        // הוספת ה-QR לתמונה אם התבקש
                        if (qrUrl) {
                            const qrImg = new Image();
                            qrImg.crossOrigin = "Anonymous";
                            qrImg.onload = () => {
                                // ממקם את ה-QR בפינה ימנית תחתונה (ניתן לשנות בעתיד)
                                const qrSize = canvas.width * 0.15;
                                ctx.drawImage(qrImg, canvas.width - qrSize - 20, canvas.height - qrSize - 20, qrSize, qrSize);
                                resolve(canvas.toDataURL('image/jpeg', 0.95));
                            };
                            qrImg.src = qrUrl;
                        } else {
                            resolve(canvas.toDataURL('image/jpeg', 0.95));
                        }
                    };
                    photo.onerror = reject;
                    photo.src = photoUrl;
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
                } catch(e) { alert("שגיאה בהורדת התמונה. בדוק חיבור לאינטרנט."); }
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
                        const qrUrl = generateQRCodeLink(item.id);
                        const dataUrl = await generateMergedDataURL(item.image, qrUrl);
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

            window.downloadSelected = function() { downloadMultiple(uploadsData.filter(item => selectedItems.has(String(item.id)))); };
            window.downloadAll = function() { downloadMultiple(uploadsData); };

            window.printItem = async function(id, imageUrl) {
                const qrUrl = generateQRCodeLink(id);
                const mergedDataUrl = await generateMergedDataURL(imageUrl, qrUrl);
                
                const printWindow = window.open('', '_blank');
                const printHTML = `
                <!DOCTYPE html>
                <html lang="he" dir="rtl">
                <head>
                    <meta charset="UTF-8">
                    <title>הדפסת תמונה #${id}</title>
                    <style>
                        @page { margin: 0; }
                        html, body { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background: white; }
                        body { display: flex; justify-content: center; align-items: center; }
                        img { max-width: 100%; max-height: 100vh; object-fit: contain; }
                    </style>
                </head>
                <body>
                    <img src="${mergedDataUrl}" onload="window.print();">
                </body>
                </html>`;
                printWindow.document.write(printHTML);
                printWindow.document.close();
                
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

    // --- אפליקציה 3: סורק ה-AR (עכשיו דינמי לחלוטין!) ---
    private function output_scanner_app( $event_id ) {
        if (!isset($_GET['media_id'])) {
            wp_die('קוד ה-QR לא תקין. חסר מזהה תמונה.', 'שגיאה', array('response' => 400));
        }
        
        $media_id = intval($_GET['media_id']);
        
        // שליפת הוידאו וקובץ ה-mind הספציפיים לתמונה הזו
        $video_url = get_post_meta($media_id, '_pquick_video_url', true);
        $mind_url = get_post_meta($media_id, '_pquick_mind_url', true);
        
        if (empty($video_url) || empty($mind_url)) {
            wp_die('לא נמצא וידאו או קובץ AR עבור תמונה זו.', 'שגיאה', array('response' => 404));
        }

        $custom_logo = get_post_meta($event_id, '_pquick_event_logo', true);
        $logo_url = $custom_logo ? $custom_logo : '';
        $has_logo = ! empty( trim( $logo_url ) );
        ?>
        <!DOCTYPE html>
        <html lang="he" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Pquick AR - סורק מציאות רבודה</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Alef:wght@400;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <script src="https://aframe.io/releases/1.3.0/aframe.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/mind-ar@1.2.5/dist/mindar-image-aframe.prod.js"></script>
            <style>
                body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background-color: #000; font-family: 'Alef', sans-serif;}
                .a-enter-vr, .a-enter-ar { display: none !important; }
                #ar-container { width: 100vw; height: 100vh; position: absolute; top: 0; left: 0; z-index: 1; display: none; }
                #scanner-ui { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; pointer-events: none; display: flex; flex-direction: column; justify-content: space-between; padding: 20px; }
                .scan-line { width: 80%; height: 2px; background: #ffb800; position: absolute; top: 30%; left: 10%; box-shadow: 0 0 10px #ffb800; animation: scan 2s infinite alternate; border-radius: 50%; }
                @keyframes scan { 0% { top: 30%; opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { top: 70%; opacity: 0; } }
            </style>
        </head>
        <body>
            <div id="scanner-ui">
                <div class="flex justify-between items-start">
                    <div class="bg-black/50 backdrop-blur-md rounded-full px-4 py-2 flex items-center gap-2">
                        <?php if($has_logo): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Pquick Logo" class="h-8 w-auto block object-contain shrink-0 drop-shadow-sm skip-lazy" data-no-lazy="1" data-skip-lazy="1" style="min-width: 40px; max-width: 120px;">
                        <?php else: ?>
                            <span class="text-lg font-bold text-white">Pquick<span class="text-[#ffb800]">AR</span></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="relative w-full h-full flex items-center justify-center">
                    <div class="border-2 border-white/50 w-[80%] aspect-[3/4] rounded-xl relative">
                        <div class="absolute -top-2 -left-2 w-6 h-6 border-t-4 border-l-4 border-[#ffb800] rounded-tl-lg"></div>
                        <div class="absolute -top-2 -right-2 w-6 h-6 border-t-4 border-r-4 border-[#ffb800] rounded-tr-lg"></div>
                        <div class="absolute -bottom-2 -left-2 w-6 h-6 border-b-4 border-l-4 border-[#ffb800] rounded-bl-lg"></div>
                        <div class="absolute -bottom-2 -right-2 w-6 h-6 border-b-4 border-r-4 border-[#ffb800] rounded-br-lg"></div>
                        <div class="scan-line" id="scan-line"></div>
                    </div>
                </div>

                <div class="bg-black/60 backdrop-blur-md rounded-2xl p-4 text-center pointer-events-auto">
                    <p class="text-white font-bold" id="status-text"><i class="fa-solid fa-camera animate-pulse text-[#ffb800] mr-2"></i> כוונו לתמונה המודפסת שלכם...</p>
                    <button id="btn-unmute" class="hidden mt-3 bg-[#ffb800] text-[#454857] font-bold py-2 px-6 rounded-full w-full shadow-lg"><i class="fa-solid fa-volume-high mr-2"></i> הפעל סאונד</button>
                </div>
            </div>

            <div id="ar-container" style="display:block;">
                <a-scene mindar-image="imageTargetSrc: <?php echo esc_url($mind_url); ?>; autoStart: true; uiScanning: no;" color-space="sRGB" renderer="colorManagement: true, physicallyCorrectLights" vr-mode-ui="enabled: false" device-orientation-permission-ui="enabled: false">
                    <a-assets timeout="10000">
                        <video id="ar-video" src="<?php echo esc_url($video_url); ?>" loop crossorigin="anonymous" playsinline webkit-playsinline muted></video>
                    </a-assets>
                    <a-camera position="0 0 0" look-controls="enabled: false"></a-camera>
                    <a-entity mindar-image-target="targetIndex: 0" id="target-entity">
                        <a-video src="#ar-video" position="0 0 0" height="0.552" width="1" rotation="0 0 0"></a-video>
                    </a-entity>
                </a-scene>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    const targetEntity = document.querySelector('#target-entity');
                    const videoEl = document.querySelector('#ar-video');
                    const statusText = document.getElementById('status-text');
                    const scanLine = document.getElementById('scan-line');
                    const btnUnmute = document.getElementById('btn-unmute');

                    // פתרון למניעת מסך לבן בוידאו
                    videoEl.addEventListener('loadedmetadata', () => {
                        const ratio = videoEl.videoWidth / videoEl.videoHeight;
                        const aVideo = document.querySelector('a-video');
                        aVideo.setAttribute('width', '1');
                        aVideo.setAttribute('height', (1 / ratio).toString());
                    });

                    if(targetEntity) {
                        targetEntity.addEventListener("targetFound", event => {
                            statusText.innerHTML = '<i class="fa-solid fa-circle-check text-[#9ad7cf] mr-2"></i> התמונה זוהתה!';
                            scanLine.style.display = 'none';
                            videoEl.play();
                            btnUnmute.classList.remove('hidden'); 
                        });

                        targetEntity.addEventListener("targetLost", event => {
                            statusText.innerHTML = '<i class="fa-solid fa-camera animate-pulse text-[#ffb800] mr-2"></i> כוונו לתמונה המודפסת שלכם...';
                            scanLine.style.display = 'block';
                            videoEl.pause();
                            btnUnmute.classList.add('hidden');
                        });
                    }

                    btnUnmute.addEventListener('click', () => {
                        videoEl.muted = !videoEl.muted;
                        if(videoEl.muted) { btnUnmute.innerHTML = '<i class="fa-solid fa-volume-xmark mr-2"></i> הפעל סאונד'; } 
                        else { btnUnmute.innerHTML = '<i class="fa-solid fa-volume-high mr-2"></i> השתק סאונד'; }
                    });
                });
            </script>
        </body>
        </html>
        <?php
    }
}

new Pquick_AR_Core();