<?php
/**
 * Plugin Name: AI ALT Generator by Hedea
 * Description: Generowanie ALT dla obrazów w Mediach z ChatGPT. Przyciski, ustawienia (API key, model, prompt), akcje masowe, kontekst produktu/wpisu/strony.
 * Version: 0.0.3
 * Author: Hedea - Kacper Baranowski
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class ALT_By_ChatGPT_One {
    const OPT_KEY = 'altgpt_one_options';
    const NONCE_ACTION = 'altgpt_one_nonce';
    const AJAX_ACTION = 'altgpt_one_generate';
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private $last_request_debug = null;

    private $allowed_models = [
        'gpt-4o-mini' => 'gpt-4o-mini',
        'gpt-4o' => 'gpt-4o',
        'gpt-4.1-mini' => 'gpt-4.1-mini',
        'gpt-4.1' => 'gpt-4.1',
        'o4-mini' => 'o4-mini'
    ];

    public function __construct(){
        add_action('admin_menu', [ $this, 'add_settings_page' ]);
        add_action('admin_init', [ $this, 'register_settings' ]);
        add_filter('manage_upload_columns', [ $this, 'add_alt_column' ]);
        add_action('manage_media_custom_column', [ $this, 'render_alt_column' ], 10, 2);
        add_filter('bulk_actions-upload', [ $this, 'register_bulk_actions' ]);
        // Usuń akcję masową "dla brakujących" z listy (zostaw tylko zaznaczone)
        add_filter('bulk_actions-upload', [ $this, 'remove_bulk_missing_action' ], 999);
        add_filter('handle_bulk_actions-upload', [ $this, 'handle_bulk_actions' ], 10, 3);
        add_action('admin_notices', [ $this, 'bulk_admin_notice' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);
        add_action('wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_generate_alt' ]);
        add_action('add_attachment', [ $this, 'maybe_fill_on_upload' ]);

        // GitHub updater (optional; configure ALTGPT_GITHUB_REPO constant)
        add_action('init', [ $this, 'init_github_updater' ]);
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
            'scan_limit' => 50,
            'github_repo' => '',
            'github_token' => '',
        ];
        return wp_parse_args(get_option(self::OPT_KEY, []), $defaults);
    }

    public function add_settings_page(){
        add_options_page('ALT by ChatGPT','ALT by ChatGPT','manage_options','altgpt-one',[ $this, 'render_settings_page' ]);
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

        // Sekcja aktualizacji (GitHub)
        add_settings_section('updates','Aktualizacje (GitHub)','__return_false','altgpt-one');
        add_settings_field('github_repo','Repozytorium (owner/repo)',function(){
            $o=$this->get_options();
            echo '<input type="text" name="'.self::OPT_KEY.'[github_repo]" value="'.esc_attr($o['github_repo']).'" class="regular-text" placeholder="owner/repo">';
            echo '<p class="description">Np. hedea/alt-by-chatgpt-one-context. Zostaw puste, aby wyłączyć aktualizacje z GitHub.</p>';
        },'altgpt-one','updates');
        add_settings_field('github_token','Token (opcjonalnie)',function(){
            $o=$this->get_options();
            echo '<input type="password" name="'.self::OPT_KEY.'[github_token]" value="'.esc_attr($o['github_token']).'" class="regular-text" autocomplete="new-password">';
            echo '<p class="description">Wymagany dla prywatnych repo. Przechowywany w opcjach WordPress.</p>';
        },'altgpt-one','updates');
    }

    public function render_settings_page(){
        echo '<div class="wrap"><h1>ALT by ChatGPT</h1><form method="post" action="options.php">';
        settings_fields('altgpt-one'); do_settings_sections('altgpt-one'); submit_button();
        echo '</form></div>';
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
        if($hook!='upload.php')return;
        wp_enqueue_script('altgpt',plugin_dir_url(__FILE__).'assets/altgpt.js',['jquery'],'1.0',true);
        wp_localize_script('altgpt','ALTGPT',['ajax'=>admin_url('admin-ajax.php'),'action'=>self::AJAX_ACTION]);
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
        $o=$this->get_options(); if(!$o['api_key']) return new WP_Error('no_key','Brak API key');
        $prompt=str_replace('{{image_url}}',$url,$o['prompt']);
        // Dodanie kontekstu – np. post_parent
        $parent=get_post_field('post_parent',$id);
        if($parent){ $prompt.=' Kontekst: '.get_the_title($parent); }
        $payload=['model'=>$o['model'],'messages'=>[[ 'role'=>'user','content'=>[ ['type'=>'text','text'=>$prompt], ['type'=>'image_url','image_url'=>['url'=>$url]] ] ]]];
        $headers = [
            'Authorization' => 'Bearer '.$o['api_key'],
            'Content-Type'  => 'application/json'
        ];
        // Debug: zapamiętaj szczegóły zapytania (API key zamaskowany)
        $this->last_request_debug = [
            'endpoint' => self::API_ENDPOINT,
            'headers'  => [
                'Authorization' => 'Bearer '.( $o['api_key'] ? substr($o['api_key'],0,3).'...' : '' ),
                'Content-Type'  => 'application/json'
            ],
            'payload'  => $payload
        ];
        $r=wp_remote_post(self::API_ENDPOINT,[
            'headers'=>$headers,
            'body'=>json_encode($payload),
            'timeout' => 30
        ]);
        if(is_wp_error($r)) return $r;
        $status = wp_remote_retrieve_response_code($r);
        $body   = wp_remote_retrieve_body($r);
        $this->last_request_debug['response'] = [
            'status' => $status,
            'body_preview' => substr((string)$body,0,2000)
        ];
        $b=json_decode($body,true);
        if(!is_array($b)) return new WP_Error('bad_json','OpenAI zwróciło nieprawidłową odpowiedź');
        if(isset($b['error'])){
            $msg = is_array($b['error']) ? ($b['error']['message'] ?? 'Błąd OpenAI') : (string)$b['error'];
            return new WP_Error('openai_error',$msg);
        }
        if(empty($b['choices']) || empty($b['choices'][0]['message']['content'])){
            return new WP_Error('no_content','Brak treści w odpowiedzi OpenAI');
        }
        $content = $b['choices'][0]['message']['content'];
        if(is_array($content)){
            $parts = [];
            foreach($content as $part){ if(is_array($part) && isset($part['text'])) $parts[] = $part['text']; }
            $content = trim(implode(' ', $parts));
        }
        $alt = trim((string)$content);
        return $alt !== '' ? $alt : new WP_Error('empty_alt','Pusta treść ALT');
    }

    public function register_bulk_actions($a){
        $a['altgpt_sel']='Generuj ALT dla zaznaczonych'; $a['altgpt_miss']='Generuj ALT dla brakujących'; return $a;
    }
    public function handle_bulk_actions($url,$act,$ids){
        $ok=0;$err=0;
        if($act=='altgpt_sel'){
            foreach($ids as $id){
                // Pomiń załączniki, które już mają ALT
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

    /* =========================
     *  GitHub Updater (optional)
     * ========================= */
    public function init_github_updater(){
        $repo = $this->get_github_repo();
        if(!$repo){ return; }
        add_filter('pre_set_site_transient_update_plugins', [ $this, 'github_check_for_update' ]);
        add_filter('plugins_api', [ $this, 'github_plugin_info' ], 10, 3);
        add_filter('http_request_args', [ $this, 'github_http_headers' ], 10, 2);
        add_filter('upgrader_source_selection', [ $this, 'github_rename_source_dir' ], 10, 4);
    }

    private function get_github_repo(){
        // Priorytet: stała w wp-config → ustawienie wtyczki → filtr
        if(defined('ALTGPT_GITHUB_REPO') && ALTGPT_GITHUB_REPO){ return ALTGPT_GITHUB_REPO; }
        $o=$this->get_options(); if(!empty($o['github_repo'])) return $o['github_repo'];
        return apply_filters('altgpt_github_repo', null);
    }

    private function get_github_token(){
        // Priorytet: stała w wp-config → ustawienie wtyczki → filtr
        if(defined('ALTGPT_GITHUB_TOKEN') && ALTGPT_GITHUB_TOKEN){ return ALTGPT_GITHUB_TOKEN; }
        $o=$this->get_options(); if(!empty($o['github_token'])) return $o['github_token'];
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
        if(strpos($url,'github.com')===false && strpos($url,'api.github.com')===false){ return $args; }
        $args['headers'] = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : [];
        $args['headers']['User-Agent'] = 'WordPress/'.get_bloginfo('version').'; '.home_url();
        $args['headers']['Accept'] = 'application/vnd.github+json';
        $token = $this->get_github_token();
        if($token){
            $args['headers']['Authorization'] = 'Bearer '.$token;
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
        // Prefer release asset .zip if available
        if(!empty($release['assets']) && is_array($release['assets'])){
            foreach($release['assets'] as $asset){
                if(!empty($asset['browser_download_url']) && preg_match('/\.zip$/i',$asset['browser_download_url'])){
                    return $asset['browser_download_url'];
                }
            }
        }
        // Fallback to auto-generated zipball (may change folder name)
        if(!empty($release['zipball_url'])) return $release['zipball_url'];
        return null;
    }

    public function github_rename_source_dir($source, $remote_source, $upgrader, $hook_extra){
        if(empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->get_plugin_basename()){
            return $source;
        }
        // Ensure extracted folder name matches the installed plugin folder
        $slug = $this->get_plugin_slug();
        $parent = trailingslashit(dirname(rtrim($source,'/\\')));
        $desired = $parent . $slug . '/';
        if(rtrim($source,'/\\') === rtrim($desired,'/\\')){
            return $source; // already matching
        }
        // Try to rename extracted dir
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
        $info->author = 'Hedea - Kacper Baranowski';
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
