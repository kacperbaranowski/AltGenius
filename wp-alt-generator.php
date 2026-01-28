<?php
/**
 * Plugin Name: AltGenius
 * Plugin URI: https://github.com/kacperbaranowski
 * Description: Automatyczne generowanie tekstów ALT dla obrazów w Bibliotece Mediów z użyciem AI (ChatGPT). Obsługa akcji masowych, kontekstu wpisu/strony/produktu oraz pełne ustawienia (API key, model, prompt).
 * Version: 1.0.5
 * Author: Kacper Baranowski
 * Author URI: https://github.com/kacperbaranowski
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: altgenius
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if(!defined('ALTGPT_GITHUB_REPO'))  define('ALTGPT_GITHUB_REPO', 'kacperbaranowski/AltGenius');
// Token nie jest potrzebny dla publicznego repo

class ALT_By_ChatGPT_One {
    const OPT_KEY = 'altgpt_one_options';
    const NONCE_ACTION = 'altgpt_one_nonce';
    const AJAX_ACTION = 'altgpt_one_generate';
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private $last_request_debug = null;

    private $allowed_models = [
        'gpt-5.1' => 'gpt-5.1',
        'gpt-5-mini' => 'gpt-5-mini',
        'gpt-5-nano' => 'gpt-5-nano',
        'gpt-4.1' => 'gpt-4.1',
        'gpt-4.1-mini' => 'gpt-4.1-mini',
        'gpt-4.1-nano' => 'gpt-4.1-nano',
        'gpt-4o' => 'gpt-4o',
        'gpt-4o-mini' => 'gpt-4o-mini',
        'gpt-4o-realtime-preview' => 'gpt-4o-realtime-preview',
        'o3' => 'o3',
        'o4-mini' => 'o4-mini'
    ];

    public function __construct(){
        add_action('admin_menu', [ $this, 'add_settings_page' ]);
        add_action('admin_init', [ $this, 'register_settings' ]);
        add_filter('manage_upload_columns', [ $this, 'add_alt_column' ]);
        add_action('manage_media_custom_column', [ $this, 'render_alt_column' ], 10, 2);
        add_filter('bulk_actions-upload', [ $this, 'register_bulk_actions' ]);
        add_filter('bulk_actions-upload', [ $this, 'remove_bulk_missing_action' ], 999);
        add_filter('handle_bulk_actions-upload', [ $this, 'handle_bulk_actions' ], 10, 3);
        add_action('admin_notices', [ $this, 'bulk_admin_notice' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);
        add_action('wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_generate_alt' ]);
        add_action('add_attachment', [ $this, 'maybe_fill_on_upload' ]);
        add_action('init', [ $this, 'init_github_updater' ]);
        
        // Nowe AJAX akcje dla statystyk
        add_action('wp_ajax_altgpt_refresh_stats', [ $this, 'ajax_refresh_stats' ]);
        add_action('wp_ajax_altgpt_refresh_logs', [ $this, 'ajax_refresh_logs' ]);
        add_action('wp_ajax_altgpt_clear_logs', [ $this, 'ajax_clear_logs' ]);
        add_action('wp_ajax_altgpt_scan_now', [ $this, 'ajax_scan_now' ]);
        add_action('wp_ajax_altgpt_generate_all', [ $this, 'ajax_generate_all' ]);
        
        // Cron job
        add_action('altgpt_cron_scan', [ $this, 'cron_scan_and_generate' ]);
        // Dodaj custom cron interval (5 minut)
        add_filter('cron_schedules', [ $this, 'add_cron_interval' ]);
        
        register_activation_hook(__FILE__, [ $this, 'activate_cron' ]);
        register_deactivation_hook(__FILE__, [ $this, 'deactivate_cron' ]);
    }

    private function default_prompt(){
        return 'Opisz to zdjęcie jednym zdaniem po polsku do ALT. URL: {{image_url}}';
    }

    private function get_options(){
        $defaults = [
            'api_key' => '',
            'model' => 'gpt-4o-mini',
            'prompt' => $this->default_prompt(),
            'auto_on_upload' => 0,
            'scan_limit' => 30
        ];
        return wp_parse_args(get_option(self::OPT_KEY, []), $defaults);
    }

    public function add_settings_page(){
        add_menu_page(
            'ALT Generator',           // page_title
            'ALT Generator',           // menu_title
            'manage_options',          // capability
            'altgpt-one',              // menu_slug
            [ $this, 'render_settings_page' ], // callback
            'dashicons-images-alt2',   // icon
            30                         // position
        );
        
        // Submenu: Ustawienia
        add_submenu_page(
            'altgpt-one',              // parent_slug
            'Ustawienia',              // page_title
            'Ustawienia',              // menu_title
            'manage_options',          // capability
            'altgpt-one',              // menu_slug (same as parent for first submenu)
            [ $this, 'render_settings_page' ] // callback
        );
        
        // Submenu: Statystyki i Logi
        add_submenu_page(
            'altgpt-one',              // parent_slug
            'Statystyki i Logi',       // page_title
            'Statystyki i Logi',       // menu_title
            'manage_options',          // capability
            'altgpt-stats',            // menu_slug
            [ $this, 'render_stats_page' ] // callback
        );
    }

    public function register_settings(){
        register_setting('altgpt-one', self::OPT_KEY);
        add_settings_section('main','Ustawienia','__return_false','altgpt-one');
        add_settings_field('api_key','API Key',function(){
            $o=$this->get_options(); echo '<input type="password" name="'.self::OPT_KEY.'[api_key]" value="'.esc_attr($o['api_key']).'" class="regular-text">';
        },'altgpt-one','main');
        add_settings_field('model','Model',function(){
            $o=$this->get_options(); echo '<select name="'.self::OPT_KEY.'[model]">';
            foreach($this->allowed_models as $m=>$lbl){ printf('<option value="%s"%s>%s</option>',esc_attr($m),selected($o['model'],$m,false),esc_html($lbl)); }
            echo '</select>';
        },'altgpt-one','main');
        add_settings_field('prompt','Prompt',function(){
            $o=$this->get_options(); echo '<textarea name="'.self::OPT_KEY.'[prompt]" rows="4" class="large-text">'.esc_textarea($o['prompt']).'</textarea>';
        },'altgpt-one','main');
        add_settings_field('auto_on_upload','Automatycznie przy uploadzie',function(){
            $o=$this->get_options();
            printf('<label><input type="checkbox" name="%s[auto_on_upload]" value="1" %s> Włącz automatyczne generowanie ALT przy dodaniu pliku</label>',
                esc_attr(self::OPT_KEY), checked(!empty($o['auto_on_upload']), true, false));
        },'altgpt-one','main');
    }


    public function render_settings_page(){
        echo '<div class="wrap"><h1>AltGenius - Ustawienia</h1><form method="post" action="options.php">';
        settings_fields('altgpt-one'); do_settings_sections('altgpt-one'); submit_button();
        echo '</form></div>';
    }

    public function render_stats_page(){
        if(!current_user_can('manage_options')) return;
        
        $stats = $this->get_images_stats();
        
        // Sprawdź status crona
        $next_cron = wp_next_scheduled('altgpt_cron_scan');
        $cron_status = $next_cron ? 'Aktywny' : 'Nieaktywny';
        $cron_next = $next_cron ? date('Y-m-d H:i:s', $next_cron) : 'Brak';
        
        ?>
        <div class="wrap">
            <h1>AltGenius - Statystyki</h1>
            
            <!-- KPI Cards -->
            <div class="altgpt-stats-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="card" style="padding: 20px; background: #fff; border-left: 4px solid #2271b1;">
                    <h3 style="margin: 0 0 10px;">Wszystkie obrazy</h3>
                    <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo number_format($stats['total']); ?></p>
                </div>
                <div class="card" style="padding: 20px; background: #fff; border-left: 4px solid #00a32a;">
                    <h3 style="margin: 0 0 10px;">Z ALT</h3>
                    <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo number_format($stats['with_alt']); ?></p>
                </div>
                <div class="card" style="padding: 20px; background: #fff; border-left: 4px solid #d63638;">
                    <h3 style="margin: 0 0 10px;">Bez ALT</h3>
                    <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo number_format($stats['without_alt']); ?></p>
                </div>
                <div class="card" style="padding: 20px; background: #fff; border-left: 4px solid #007cba;">
                    <h3 style="margin: 0 0 10px;">Pokrycie</h3>
                    <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo $stats['percentage']; ?>%</p>
                </div>
            </div>
            
            <!-- Status Crona -->
            <div class="card" style="background: #fff; padding: 20px; margin: 20px 0;">
                <h2>Automatyczne Generowanie (CRON)</h2>
                <p><strong>Status:</strong> <span style="color: <?php echo $next_cron ? '#00a32a' : '#d63638'; ?>;"><?php echo $cron_status; ?></span></p>
                <?php if($next_cron): ?>
                    <p><strong>Następne uruchomienie:</strong> <?php echo $cron_next; ?></p>
                    <p style="color: #666; font-size: 14px;"><em>Cron uruchamia się co 5 minut (288×/dzień) i przetwarza 30 obrazków = ~8,640 zapytań/dzień (OpenAI Tier 1: 10k/dzień).</em></p>
                <?php else: ?>
                    <p style="color: #d63638;">⚠️ Cron nie jest zaplanowany. Aktywuj ponownie wtyczkę.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }


    public function add_alt_column($c){ $c['altgpt']=__('ALT (ChatGPT)'); return $c; }
    public function render_alt_column($col,$id){
        if($col!='altgpt') return;
        if(strpos(get_post_mime_type($id),'image/')!==0){echo '—'; return;}
        $alt=get_post_meta($id,'_wp_attachment_image_alt',true);
        $nonce=wp_create_nonce(self::NONCE_ACTION);
        printf('<div><div class="altgpt-alt">%s</div><button class="button altgpt-generate" data-id="%d" data-nonce="%s">Generuj ALT</button><span class="altgpt-status"></span></div>',esc_html($alt?:'brak'),$id,$nonce);
    }

    public function enqueue_admin_assets($hook){
        // Dla strony Media (upload.php)
        if($hook=='upload.php'){
            wp_enqueue_script('altgpt',plugin_dir_url(__FILE__).'assets/altgpt.js',['jquery'],'1.0',true);
            wp_localize_script('altgpt','ALTGPT',['ajax'=>admin_url('admin-ajax.php'),'action'=>self::AJAX_ACTION]);
        }
        
        // Dla strony statystyk
        if(strpos($hook, 'altgpt-stats') !== false){
            wp_enqueue_script('altgpt-stats',plugin_dir_url(__FILE__).'assets/stats.js',['jquery'],'1.0',true);
            wp_enqueue_style('altgpt-stats-css',plugin_dir_url(__FILE__).'assets/stats.css',[],'1.0');
            wp_localize_script('altgpt-stats','ALTGPT_STATS',[
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('altgpt-stats-nonce')
            ]);
        }
    }

    public function ajax_generate_alt(){
        check_ajax_referer(self::NONCE_ACTION,'_nonce');
        $id=intval($_POST['attachment_id']); $res=$this->generate_and_update_alt($id);
        if(is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message(),'request'=>$this->last_request_debug]);
        wp_send_json_success(['alt'=>$res,'request'=>$this->last_request_debug]);
    }

    private function generate_and_update_alt($id){
        $url=wp_get_attachment_url($id); if(!$url)return new WP_Error('no_url','Brak URL');
        $alt=$this->ask_openai_for_alt($url,$id); if(is_wp_error($alt))return $alt;
        update_post_meta($id,'_wp_attachment_image_alt',$alt); return $alt;
    }

    private function ask_openai_for_alt($url,$id){
    $o=$this->get_options(); 
    if(!$o['api_key']) return new WP_Error('no_key','Brak API key');
    
    $prompt=str_replace('{{image_url}}',$url,$o['prompt']);
    
    $parent=get_post_field('post_parent',$id);
    if($parent){ 
        $prompt.=' Kontekst: '.get_the_title($parent); 
    }
    
    $image_path = get_attached_file($id);
    if(!$image_path || !file_exists($image_path)){
        return new WP_Error('no_file','Nie można znaleźć pliku obrazka');
    }
    
    $image_data = file_get_contents($image_path);
    if($image_data === false){
        return new WP_Error('read_error','Nie można odczytać pliku obrazka');
    }
    
    $mime_type = get_post_mime_type($id);
    if(!$mime_type){
        $mime_type = 'image/jpeg';
    }
    
    $base64 = base64_encode($image_data);
    $data_url = "data:{$mime_type};base64,{$base64}";
    
    $payload=[
        'model'=>$o['model'],
        'messages'=>[[ 
            'role'=>'user',
            'content'=>[ 
                ['type'=>'text','text'=>$prompt], 
                ['type'=>'image_url','image_url'=>['url'=>$data_url]]
            ] 
        ]]
    ];
    
    $headers = [
        'Authorization' => 'Bearer '.$o['api_key'],
        'Content-Type'  => 'application/json'
    ];
    
    $this->last_request_debug = [
        'endpoint' => self::API_ENDPOINT,
        'headers'  => [
            'Authorization' => 'Bearer '.( $o['api_key'] ? substr($o['api_key'],0,3).'...' : '' ),
            'Content-Type'  => 'application/json'
        ],
        'payload'  => $payload,
        'image_size' => strlen($base64) . ' bytes (base64)'
    ];
    
    $r=wp_remote_post(self::API_ENDPOINT,[
        'headers'=>$headers,
        'body'=>json_encode($payload),
        'timeout'=>60
    ]);
    
    if(is_wp_error($r)) return $r;
    $code=wp_remote_retrieve_response_code($r);
    if($code!==200){
        $body=wp_remote_retrieve_body($r);
        return new WP_Error('openai_error','OpenAI error '.$code.': '.$body);
    }
    
    $resp=json_decode(wp_remote_retrieve_body($r),true);
    if(!isset($resp['choices'][0]['message']['content'])){
        return new WP_Error('no_content','Brak odpowiedzi z OpenAI');
    }
    
    return trim($resp['choices'][0]['message']['content']);
}

    public function register_bulk_actions($a){
        $a['altgpt_sel']='Generuj ALT dla zaznaczonych'; $a['altgpt_miss']='Generuj ALT dla brakujących'; return $a;
    }
    public function handle_bulk_actions($url,$act,$ids){
        $ok=0;$err=0;
        if($act=='altgpt_sel'){
            foreach($ids as $id){
                if(get_post_meta($id,'_wp_attachment_image_alt',true)){
                    continue;
                }
                $r=$this->generate_and_update_alt($id);
                if(is_wp_error($r))$err++; else $ok++;
            }
        }
        if($act=='altgpt_miss'){ $q=new WP_Query(['post_type'=>'attachment','post_mime_type'=>'image','meta_query'=>[['key'=>'_wp_attachment_image_alt','compare'=>'NOT EXISTS']],'fields'=>'ids','posts_per_page'=>20]); foreach($q->posts as $id){$r=$this->generate_and_update_alt($id); if(is_wp_error($r))$err++; else $ok++;} }
        return add_query_arg(['altgpt_bulk'=>1,'ok'=>$ok,'err'=>$err],$url);
    }
    public function remove_bulk_missing_action($a){ unset($a['altgpt_miss']); return $a; }
    public function bulk_admin_notice(){
        if(!isset($_GET['altgpt_bulk']))return;
        echo '<div class="updated"><p>ALT wygenerowane: '.intval($_GET['ok']).', błędy: '.intval($_GET['err']).'</p></div>';
    }

    public function maybe_fill_on_upload($id){
        $o=$this->get_options(); if(empty($o['auto_on_upload']))return;
        if(!get_post_meta($id,'_wp_attachment_image_alt',true)) $this->generate_and_update_alt($id);
    }

    // === STATYSTYKI I LOGI ===
    
    private function get_images_stats(){
        global $wpdb;
        
        // Wszystkie obrazy
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'");
        
        // Obrazy z alt
        $with_alt = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type='attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND pm.meta_key='_wp_attachment_image_alt'
            AND pm.meta_value != ''
        ");
        
        $without_alt = $total - $with_alt;
        $percentage = $total > 0 ? round(($with_alt / $total) * 100, 1) : 0;
        
        return [
            'total' => (int)$total,
            'with_alt' => (int)$with_alt,
            'without_alt' => (int)$without_alt,
            'percentage' => $percentage
        ];
    }
    
    private function get_images_without_alt($limit = -1){
        global $wpdb;
        
        // Zapytanie SQL bezpośrednio do bazy
        $sql = "
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
            ORDER BY p.ID DESC
        ";
        
        if($limit > 0){
            $sql .= " LIMIT " . intval($limit);
        }
        
        $ids = $wpdb->get_col($sql);
        
        // Debug log
        $this->log_to_file("get_images_without_alt: znaleziono " . count($ids) . " ID-ków (limit: $limit)", 'INFO');
        
        if(empty($ids)){
            return [];
        }
        
        // Pobierz pełne obiekty postów
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post__in' => $ids,
            'posts_per_page' => -1,
            'orderby' => 'post__in'
        ];
        
        $query = new WP_Query($args);
        
        // Debug log
        $this->log_to_file("WP_Query zwróciło " . count($query->posts) . " postów", 'INFO');
        
        return $query->posts;
    }
    
    private function get_log_file_path(){
        return plugin_dir_path(__FILE__) . 'logs/alt-scan-log.txt';
    }
    
    private function ensure_logs_directory(){
        $logs_dir = plugin_dir_path(__FILE__) . 'logs';
        if(!file_exists($logs_dir)){
            mkdir($logs_dir, 0755, true);
        }
        
        $htaccess = $logs_dir . '/.htaccess';
        if(!file_exists($htaccess)){
            file_put_contents($htaccess, "deny from all\n");
        }
    }
    
    private function log_to_file($message, $type = 'INFO'){
        $this->ensure_logs_directory();
        $log_file = $this->get_log_file_path();
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$type} - {$message}\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
    
    private function get_recent_logs($lines = 100){
        $log_file = $this->get_log_file_path();
        if(!file_exists($log_file)) return '';
        
        $content = file_get_contents($log_file);
        $all_lines = explode("\n", $content);
        $all_lines = array_filter($all_lines); // usuń puste
        $all_lines = array_reverse($all_lines); // odwróć (najnowsze pierwsze)
        $recent = array_slice($all_lines, 0, $lines);
        
        return implode("\n", $recent);
    }
    
    private function clear_logs(){
        $log_file = $this->get_log_file_path();
        if(file_exists($log_file)){
            file_put_contents($log_file, '');
        }
        $this->log_to_file('Logi zostały wyczyszczone', 'INFO');
    }

    // === AJAX HANDLERS ===
    
    public function ajax_refresh_stats(){
        check_ajax_referer('altgpt-stats-nonce', 'nonce');
        $stats = $this->get_images_stats();
        wp_send_json_success($stats);
    }
    
    public function ajax_refresh_logs(){
        check_ajax_referer('altgpt-stats-nonce', 'nonce');
        $logs = $this->get_recent_logs(100);
        wp_send_json_success(['logs' => $logs]);
    }
    
    public function ajax_clear_logs(){
        check_ajax_referer('altgpt-stats-nonce', 'nonce');
        $this->clear_logs();
        wp_send_json_success(['message' => 'Logi wyczyszczone']);
    }
    
    public function ajax_scan_now(){
        check_ajax_referer('altgpt-stats-nonce', 'nonce');
        $this->log_to_file('Rozpoczęto manualne skanowanie', 'INFO');
        $stats = $this->get_images_stats();
        wp_send_json_success([
            'message' => 'Skanowanie zakończone',
            'stats' => $stats
        ]);
    }
    
    public function ajax_generate_all(){
        check_ajax_referer('altgpt-stats-nonce', 'nonce');
        
        $this->log_to_file("=== AJAX GENERATE_ALL START ===", 'INFO');
        
        $limit = 10; // Generuj max 10 na jedno wywołanie AJAX
        $images = $this->get_images_without_alt($limit);
        
        $this->log_to_file("Rozpoczynam przetwarzanie " . count($images) . " obrazków", 'INFO');
        
        $ok = 0;
        $err = 0;
        
        foreach($images as $img){
            $this->log_to_file("Generowanie ALT dla ID {$img->ID}", 'INFO');
            $result = $this->generate_and_update_alt($img->ID);
            
            if(is_wp_error($result)){
                $err++;
                $this->log_to_file("Błąd dla ID {$img->ID}: " . $result->get_error_message(), 'ERROR');
            } else {
                $ok++;
                $this->log_to_file("Sukces dla ID {$img->ID}: {$result}", 'SUCCESS');
            }
        }
        
        $this->log_to_file("Pętla zakończona. OK: $ok, Błędy: $err", 'INFO');
        
        $stats = $this->get_images_stats();
        
        $this->log_to_file("=== AJAX GENERATE_ALL END === Zwracam: processed=" . count($images) . ", remaining=" . $stats['without_alt'], 'INFO');
        
        wp_send_json_success([
            'processed' => count($images),
            'ok' => $ok,
            'err' => $err,
            'remaining' => $stats['without_alt'],
            'stats' => $stats
        ])
;
    }
    
    // === CRON ===
    
    // Custom cron interval - 5 minut
    public function add_cron_interval($schedules){
        $schedules['every_5_minutes'] = [
            'interval' => 300, // 5 minut w sekundach
            'display' => __('Co 5 minut')
        ];
        return $schedules;
    }
    
    public function activate_cron(){
        if(!wp_next_scheduled('altgpt_cron_scan')){
            wp_schedule_event(time(), 'every_5_minutes', 'altgpt_cron_scan');
        }
        $this->log_to_file('Cron aktywowany (co 5 minut)', 'INFO');
    }
    
    public function deactivate_cron(){
        wp_clear_scheduled_hook('altgpt_cron_scan');
        $this->log_to_file('Cron dezaktywowany', 'INFO');
    }
    
    public function cron_scan_and_generate(){
        $o = $this->get_options();
        $limit = isset($o['scan_limit']) ? (int)$o['scan_limit'] : 50;
        
        $this->log_to_file("=== CRON START === Limit: {$limit}", 'INFO');
        
        $images = $this->get_images_without_alt($limit);
        $count = count($images);
        
        if($count === 0){
            $this->log_to_file("Brak obrazków bez ALT", 'INFO');
            return;
        }
        
        $this->log_to_file("Znaleziono {$count} obrazków bez ALT", 'INFO');
        
        $ok = 0;
        $err = 0;
        
        foreach($images as $img){
            $result = $this->generate_and_update_alt($img->ID);
            
            if(is_wp_error($result)){
                $err++;
                $this->log_to_file("ERROR ID {$img->ID}: " . $result->get_error_message(), 'ERROR');
            } else {
                $ok++;
                $this->log_to_file("SUCCESS ID {$img->ID}", 'SUCCESS');
            }
        }
        
        $this->log_to_file("=== CRON END === OK: {$ok}, Błędy: {$err}", 'INFO');
    }


    public function init_github_updater(){
        $repo = $this->get_github_repo();
        if(!$repo){ return; }
        add_filter('pre_set_site_transient_update_plugins', [ $this, 'github_check_for_update' ]);
        add_filter('plugins_api', [ $this, 'github_plugin_info' ], 10, 3);
        add_filter('http_request_args', [ $this, 'github_http_headers' ], 10, 2);
        add_filter('upgrader_source_selection', [ $this, 'github_rename_source_dir' ], 10, 4);
    }

    private function get_github_repo(){
        if(defined('ALTGPT_GITHUB_REPO') && ALTGPT_GITHUB_REPO){ return ALTGPT_GITHUB_REPO; }
        return apply_filters('altgpt_github_repo', null);
    }

    private function get_github_token(){
        if(defined('ALTGPT_GITHUB_TOKEN') && ALTGPT_GITHUB_TOKEN){ return ALTGPT_GITHUB_TOKEN; }
        return apply_filters('altgpt_github_token', null);
    }

    private function get_plugin_basename(){
        return plugin_basename(__FILE__);
    }

    private function get_plugin_slug(){
        $basename = $this->get_plugin_basename();
        $dir = dirname($basename);
        return ($dir && $dir !== '.') ? $dir : basename($basename, '.php');
    }

    private function get_plugin_version(){
        $headers = get_file_data(__FILE__, [ 'Version' => 'Version' ]);
        return isset($headers['Version']) ? $headers['Version'] : '0.0.0';
    }

    public function github_http_headers($args, $url){
        $is_github = (strpos($url,'github.com') !== false) || (strpos($url,'api.github.com') !== false) || (strpos($url,'codeload.github.com') !== false);
        if(!$is_github){ return $args; }

        $args['headers'] = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : [];
        $args['headers']['User-Agent'] = 'WordPress/'.get_bloginfo('version').'; '.home_url();

        $token = $this->get_github_token();
        if($token){
            $args['headers']['Authorization'] = 'Bearer '.$token;
        }

        if(strpos($url,'api.github.com') !== false){
            if(strpos($url,'/releases/assets/') !== false){
                $args['headers']['Accept'] = 'application/octet-stream';
            } else {
                $args['headers']['Accept'] = 'application/vnd.github+json';
            }
        }

        return $args;
    }

    private function github_fetch_latest_release($repo){
        $api = 'https://api.github.com/repos/'.$repo.'/releases/latest';
        $r = wp_remote_get($api, [ 'timeout' => 20 ]);
        if(is_wp_error($r)) return $r;
        $code = wp_remote_retrieve_response_code($r);
        if($code !== 200){ return new WP_Error('github_http_'.$code, 'GitHub HTTP '.$code); }
        $b = json_decode(wp_remote_retrieve_body($r), true);
        if(!is_array($b)) return new WP_Error('github_bad_json','Nieprawidlowy JSON z GitHub');
        return $b;
    }

    private function github_pick_download_url($release){
        if(!empty($release['assets']) && is_array($release['assets'])){
            $has_token = (bool) $this->get_github_token();
            foreach($release['assets'] as $asset){
                $browser = !empty($asset['browser_download_url']) ? $asset['browser_download_url'] : '';
                $api     = !empty($asset['url']) ? $asset['url'] : '';
                $is_zip  = $browser && preg_match('/\.zip$/i', $browser);
                if($is_zip){
                    if($has_token && $api){
                        return $api; // e.g., https://api.github.com/repos/{owner}/{repo}/releases/assets/{id}
                    }
                    return $browser;
                }
            }
        }
        if(!empty($release['zipball_url'])) return $release['zipball_url'];
        return null;
    }

    public function github_rename_source_dir($source, $remote_source, $upgrader, $hook_extra){
        if(empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->get_plugin_basename()){
            return $source;
        }
        $slug = $this->get_plugin_slug();
        $parent = trailingslashit(dirname(rtrim($source,'/\\')));
        $desired = $parent . $slug . '/';
        if(rtrim($source,'/\\') === rtrim($desired,'/\\')){
            return $source; // already matching
        }
        if(@rename($source, $desired)){
            return $desired;
        }
        return $source;
    }

    public function github_check_for_update($transient){
        if(empty($transient) || empty($transient->checked)){ return $transient; }
        $repo = $this->get_github_repo(); if(!$repo){ return $transient; }

        $plugin = $this->get_plugin_basename();
        $current = $this->get_plugin_version();
        $release = $this->github_fetch_latest_release($repo);
        if(is_wp_error($release)){ return $transient; }

        $tag = !empty($release['tag_name']) ? $release['tag_name'] : (!empty($release['name']) ? $release['name'] : '');
        $remote_version = ltrim((string)$tag, 'vV');
        if(!$remote_version || version_compare($remote_version, $current, '<=')){
            return $transient;
        }

        $download = $this->github_pick_download_url($release);
        if(!$download){ return $transient; }

        $update = new stdClass();
        $update->slug = $this->get_plugin_slug();
        $update->plugin = $plugin;
        $update->new_version = $remote_version;
        $update->url = 'https://github.com/'.$repo;
        $update->package = $download;
        $transient->response[$plugin] = $update;
        return $transient;
    }

    public function github_plugin_info($result, $action, $args){
        if($action !== 'plugin_information'){ return $result; }
        if(empty($args->slug) || $args->slug !== $this->get_plugin_slug()){ return $result; }
        $repo = $this->get_github_repo(); if(!$repo){ return $result; }
        $release = $this->github_fetch_latest_release($repo);
        if(is_wp_error($release)){ return $result; }
        $tag = !empty($release['tag_name']) ? $release['tag_name'] : (!empty($release['name']) ? $release['name'] : '');
        $remote_version = ltrim((string)$tag, 'vV');
        $download = $this->github_pick_download_url($release);

        $info = new stdClass();
        $info->name = 'AI ALT Generator by Hedea';
        $info->slug = $this->get_plugin_slug();
        $info->version = $remote_version ?: $this->get_plugin_version();
        $info->author = 'Kacper Baranowski';
        $info->homepage = 'https://github.com/'.$repo;
        $info->sections = [
            'description' => !empty($release['body']) ? $release['body'] : 'Aktualizacje z GitHub Releases.',
            'changelog'   => !empty($release['body']) ? $release['body'] : ''
        ];
        if($download){ $info->download_link = $download; }
        return $info;
    }
}
new ALT_By_ChatGPT_One();

// ========================================
// WP ALT ⇄ GUTENBERG SYNC
// Dwukierunkowa synchronizacja ALT między biblioteką mediów a blokami Gutenberg
// ========================================

if (!class_exists('WP_Alt_Gutenberg_Sync')) {
    class WP_Alt_Gutenberg_Sync {
        private static $from_media_guard = false;

        public static function init() {
            add_action('updated_post_meta', [__CLASS__, 'on_updated_post_meta'], 10, 4);
            add_action('added_post_meta', [__CLASS__, 'on_added_post_meta'], 10, 4);
            add_action('save_post', [__CLASS__, 'on_save_post'], 10, 3);
        }

        public static function on_added_post_meta($meta_id, $post_id, $meta_key, $meta_value) {
            self::maybe_sync_from_media($post_id, $meta_key, $meta_value);
        }

        public static function on_updated_post_meta($meta_id, $post_id, $meta_key, $meta_value) {
            self::maybe_sync_from_media($post_id, $meta_key, $meta_value);
        }

        private static function maybe_sync_from_media($post_id, $meta_key, $new_value) {
            if ($meta_key !== '_wp_attachment_image_alt') {
                return;
            }
            $attachment = get_post($post_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                return;
            }
            $attachment_id = (int)$post_id;
            $new_alt = is_string($new_value) ? $new_value : '';

            self::$from_media_guard = true;
            self::sync_alt_to_posts($attachment_id, $new_alt);
            self::$from_media_guard = false;
        }

        public static function on_save_post($post_id, $post, $update) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (wp_is_post_revision($post_id)) return;
            if (self::$from_media_guard) return;

            $allowed_types = self::get_allowed_post_types();
            if (!in_array($post->post_type, $allowed_types, true)) return;

            $content = $post->post_content;
            if (!is_string($content) || $content === '') return;

            self::sync_post_alts_to_media($content);
        }

        private static function sync_post_alts_to_media($content) {
            // wp:image block attributes
            if (preg_match_all('/<!--\s*wp:image\s*(\{.*?\})\s*-->/s', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $attrs = json_decode($m[1], true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($attrs)) continue;
                    if (!isset($attrs['id'])) continue;
                    $id = (int)$attrs['id'];
                    $alt = '';
                    if (isset($attrs['alt']) && is_string($attrs['alt'])) {
                        $alt = $attrs['alt'];
                    } else {
                        $alt = self::guess_img_alt_for_attachment($content, $id);
                    }
                    if ($id > 0 && $alt !== '') {
                        $current = get_post_meta($id, '_wp_attachment_image_alt', true);
                        if ($current !== $alt) update_post_meta($id, '_wp_attachment_image_alt', $alt);
                    }
                }
            }

            // <img class="wp-image-ID" alt="...">
            if (preg_match_all('/<img\b[^>]*class="[^"]*\bwp-image-(\d+)\b[^"]*"[^>]*>/i', $content, $imgMatches)) {
                foreach ($imgMatches[0] as $i => $imgTag) {
                    $id = (int)$imgMatches[1][$i];
                    $alt = self::extract_alt_from_img_tag($imgTag);
                    if ($id > 0 && $alt !== '') {
                        $current = get_post_meta($id, '_wp_attachment_image_alt', true);
                        if ($current !== $alt) update_post_meta($id, '_wp_attachment_image_alt', $alt);
                    }
                }
            }
        }

        private static function get_allowed_post_types() {
            $core_excluded = ['attachment','revision','nav_menu_item','custom_css','customize_changeset','oembed_cache','user_request','wp_template','wp_template_part','wp_navigation'];
            $detected = get_post_types(['show_ui' => true], 'objects');
            if (!is_array($detected)) $detected = [];
            foreach ($core_excluded as $ex) { if (isset($detected[$ex])) unset($detected[$ex]); }
            foreach (['post','page','wp_block'] as $ensure) { $obj = get_post_type_object($ensure); if ($obj) { $detected[$ensure] = $obj; } }
            $types = array_keys($detected);
            $types = apply_filters('wpags_allowed_post_types', $types);
            $types = array_values(array_filter($types, function ($t) { return $t !== 'attachment'; }));
            return $types;
        }

        private static function sync_alt_to_posts($attachment_id, $new_alt) {
            global $wpdb;
            $like_class = '%wp-image-' . (int)$attachment_id . '%';
            $like_block = '%<!-- wp:image %"id":' . (int)$attachment_id . '%';
            $post_types = self::get_allowed_post_types();
            if (empty($post_types)) return;

            $placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
            $sql = "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','pending','private','future') AND post_type IN ($placeholders) AND (post_content LIKE %s OR post_content LIKE %s)";
            $params = array_merge($post_types, [$like_class, $like_block]);
            $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
            if (empty($rows)) return;

            foreach ($rows as $row) {
                $updated = self::replace_alt_in_content($row->post_content, $attachment_id, $new_alt);
                if ($updated !== $row->post_content) {
                    wp_update_post(['ID' => (int)$row->ID, 'post_content' => $updated]);
                }
            }
        }

        private static function replace_alt_in_content($content, $attachment_id, $new_alt) {
            $updated = $content;
            $updated = preg_replace_callback(
                '/<!--\s*wp:image\s*(\{.*?\})\s*-->/s',
                function ($m) use ($attachment_id, $new_alt) {
                    $attrs = json_decode($m[1], true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($attrs)) return $m[0];
                    if (!isset($attrs['id']) || (int)$attrs['id'] !== (int)$attachment_id) return $m[0];
                    $attrs['alt'] = (string)$new_alt;
                    $new_json = wp_json_encode($attrs);
                    return str_replace($m[1], $new_json, $m[0]);
                },
                $updated
            );

            $img_pattern = '/<img\b[^>]*class="([^"]*)\bwp-image-' . (int)$attachment_id . '\b([^"]*)"[^>]*>/i';
            $updated = preg_replace_callback(
                $img_pattern,
                function ($m) use ($new_alt) {
                    $img_tag = $m[0];
                    if (preg_match('/\balt="[^"]*"/i', $img_tag)) {
                        $img_tag = preg_replace('/\balt="[^"]*"/i', 'alt="' . esc_attr($new_alt) . '"', $img_tag);
                    } else {
                        $img_tag = preg_replace('/>$/', ' alt="' . esc_attr($new_alt) . '">', $img_tag);
                    }
                    return $img_tag;
                },
                $updated
            );
            return $updated;
        }

        private static function extract_alt_from_img_tag($imgTag) {
            if (preg_match('/\balt="([^"]*)"/i', $imgTag, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            }
            return '';
        }

        private static function guess_img_alt_for_attachment($content, $attachment_id) {
            $pattern = '/<img\b[^>]*class="[^"]*\bwp-image-' . (int)$attachment_id . '\b[^"]*"[^>]*\balt="([^"]*)"[^>]*>/i';
            if (preg_match($pattern, $content, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            }
            return '';
        }
    }
}

WP_Alt_Gutenberg_Sync::init();
