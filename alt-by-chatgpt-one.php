<?php
/**
 * Plugin Name: AI ALT Generator by Hedea
 * Description: Generowanie ALT dla obrazów w Mediach z ChatGPT. Przyciski, ustawienia (API key, model, prompt), akcje masowe, kontekst produktu/wpisu/strony.
 * Version: 0.0.1
 * Author: Hedea - Kacper Baranowski
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class ALT_By_ChatGPT_One {
    const OPT_KEY = 'altgpt_one_options';
    const NONCE_ACTION = 'altgpt_one_nonce';
    const AJAX_ACTION = 'altgpt_one_generate';
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

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
        if(is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message()]);
        wp_send_json_success(['alt'=>$res]);
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
        $r=wp_remote_post(self::API_ENDPOINT,['headers'=>['Authorization'=>'Bearer '.$o['api_key'],'Content-Type'=>'application/json'],'body'=>json_encode($payload)]);
        if(is_wp_error($r)) return $r;
        $b=json_decode(wp_remote_retrieve_body($r),true);
        return $b['choices'][0]['message']['content']??'ALT';
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
}
new ALT_By_ChatGPT_One();
