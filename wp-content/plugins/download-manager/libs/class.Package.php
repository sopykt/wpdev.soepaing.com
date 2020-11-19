<?php


namespace WPDM;


use WPDM\libs\FileSystem;

class Package {

    public $ID;
    public $PackageData = array();

    function __construct($ID = null){
        global $post;
        if(!$ID && is_object($post) && $post->post_type == 'wpdmpro') $ID = $post->ID;
        $this->ID = $ID;
        return $this;
    }

    function prepare($ID = null, $template = null, $template_type = 'page')
    {
        global $post;

        if(!$ID) $ID = $this->ID;
        if(!$ID && isset($post->ID)) $ID = $post->ID;
        if(!$ID) {
            $this->PackageData = array('error' => __('ID missing!','download-manager'));
            return $this;
        }

        if(isset($this->PackageData['formatted'])) return $this;

        if(!is_object($post) || $post->ID != $ID ) {
            $post_vars = get_post($ID, ARRAY_A);
        }
        else
            $post_vars = (array)$post;

        $loginmsg = \WPDM_Messages::login_required();


        $ID = $post_vars['ID'];

        $post_vars['title'] = stripcslashes($post_vars['post_title']);
        $post_vars['description'] = stripcslashes($post_vars['post_content']);
        $post_vars['description'] = wpautop(stripslashes($post_vars['description']));
        $post_vars['excerpt'] = stripcslashes(strip_tags($post_vars['post_excerpt']));
        $author = get_user_by('id', $post_vars['post_author']);
        $post_vars['author_name'] = $author->display_name;
        $post_vars['author_profile_url'] = get_author_posts_url($post_vars['post_author']);
        $post_vars['avatar_url'] = get_avatar_url($author->user_email);
        $post_vars['author_package_count'] = count_user_posts( $post_vars['post_author'] , "wpdmpro"  );


        //Featured Image
        $src = wp_get_attachment_image_src(get_post_thumbnail_id($ID), 'full', false);

        $post_vars['preview'] = is_array($src) && isset($src['0']) ? $src['0'] : '';

        $post_vars['featured_image'] = get_the_post_thumbnail($ID, 'full');

        $post_vars['create_date'] = get_the_date('',$ID);

        $post_vars['update_date'] = date_i18n(get_option('date_format'), strtotime($post_vars['post_modified']));


        $post_vars['categories'] = get_the_term_list( $ID, 'wpdmcategory', '', ', ', '' );

        $data = self::metaData($post_vars['ID']);

        $post_vars = array_merge($data, $post_vars);
        if(!isset($post_vars['files']) || !is_array($post_vars['files']))
            $post_vars['files'] = get_post_meta($post_vars['ID'], '__wpdm_files', true);
        $post_vars['file_count'] = is_array($post_vars['files'])?count($post_vars['files']):0;

        $post_vars['link_label'] = isset($post_vars['link_label']) ? $post_vars['link_label'] : __('Download','download-manager');
        $post_vars['page_link'] = "<a href='" . get_permalink($post_vars['ID']) . "'>{$post_vars['title']}</a>";
        $post_vars['page_url'] = get_permalink($post_vars['ID']);


        if(!isset($post_vars['btnclass']))
            $post_vars['btnclass'] = wpdm_download_button_style(null, $ID);

        $tags = get_the_tags($post_vars['ID']);
        $taghtml = "";
        if(is_array($tags)){
            foreach ($tags as $tag)
            {
                $taghtml .= "<a class='btn btn-default btn-xs' style='margin:0 5px 5px 0' href=\""
                    . get_tag_link($tag->term_id)
                    . "\"><i class='fa fa-tag'></i> &nbsp; ".$tag->name."</a> &nbsp;";
            }}
        $post_vars['tags'] = $taghtml;

        if (is_array($post_vars['files']) && count($post_vars['files']) > 1) $post_vars['file_ext'] = 'zip';
        if (is_array($post_vars['files']) && count($post_vars['files']) == 1) {
            $tmpdata = $post_vars['files'];
            $tmpdata = array_shift($tmpdata);
            $tmpdata = explode(".", $tmpdata);
            $post_vars['file_ext'] = end($tmpdata);
        }
        $post_vars['file_size'] = self::Size($post_vars['ID']);


        $tmplfile = $post_vars['files'];
        $tmpfile = is_array($tmplfile) && count($tmplfile) >0 ? array_shift($tmplfile):'';
        if(strpos($tmpfile, 'youtu')) {
            if(preg_match('/youtu\.be\/([^\/]+)/', $tmpfile, $match))
                $vid = $match[1];
            else if(preg_match('/watch\?v=([^\/]+)/', $tmpfile, $match))
                $vid = $match[1];
            $post_vars['youtube_thumb_0'] = '<img src="https://img.youtube.com/vi/' . $vid . '/0.jpg" alt="Thumb 0" />';
            $post_vars['youtube_thumb_1'] = '<img src="https://img.youtube.com/vi/' . $vid . '/1.jpg" alt="Thumb 1" />';
            $post_vars['youtube_thumb_2'] = '<img src="https://img.youtube.com/vi/' . $vid . '/2.jpg" alt="Thumb 2" />';
            $post_vars['youtube_thumb_3'] = '<img src="https://img.youtube.com/vi/' . $vid . '/3.jpg" alt="Thumb 3" />';
            $post_vars['youtube_player'] = '<iframe width="1280" height="720" style="max-wdith:100%;" src="https://www.youtube.com/embed/'.$vid.'" frameborder="0" allowfullscreen></iframe>';
        }


        if (!isset($post_vars['icon']) || $post_vars['icon'] == '') {
            if(is_array($post_vars['files'])){
                $ifn = @end($post_vars['files']);
                $ifn = @explode('.', $ifn);
                $ifn = @end($ifn);
            }
            else
                $ifn = 'unknown';

            $post_vars['icon'] = '<img class="wpdm_icon" alt="'.__('Icon','download-manager').'" src="' . plugins_url('download-manager/assets/file-type-icons/') . (@count($post_vars['files']) <= 1 ? $ifn : 'zip') . '.svg" onError=\'this.src="' . plugins_url('download-manager/assets/file-type-icons/unknown.svg') . '";\' />';
        }
        else if (!strstr($post_vars['icon'], '//'))
            $post_vars['icon'] = '<img class="wpdm_icon" alt="'.__('Icon','download-manager').'"   src="' . plugins_url(str_replace('download-manager/file-type-icons/','download-manager/assets/file-type-icons/',$post_vars['icon'])) . '" />';
        else if (!strpos($post_vars['icon'], ">"))
            $post_vars['icon'] = '<img class="wpdm_icon" alt="'.__('Icon','download-manager').'"   src="' . str_replace('download-manager/file-type-icons/','download-manager/assets/file-type-icons/',$post_vars['icon']) . '" />';

        if (isset($post_vars['preview']) && $post_vars['preview'] != '') {
            $post_vars['thumb'] = "<img title='' alt='".__('Thumbnail','download-manager')."' src='" . wpdm_dynamic_thumb($post_vars['preview'], array(400, 300)) . "'/>";
        } else
            $post_vars['thumb'] = $post_vars['thumb_page'] = $post_vars['thumb_gallery'] = $post_vars['thumb_widget'] = "";

        $k = 1;
        $post_vars['additional_previews'] = isset($post_vars['more_previews']) ? $post_vars['more_previews'] : array();
        $img = "<img id='more_previews_{$k}' title='' class='more_previews' src='" . wpdm_dynamic_thumb($post_vars['preview'], array(575, 170)) . "'/>\n";
        $tmb = "<a href='#more_previews_{$k}' class='spt'><img title='' alt='".__('Thumbnail','download-manager')."' src='" . wpdm_dynamic_thumb($post_vars['preview'], array(100, 45)) . "'/></a>\n";


        global $blog_id;
        if (defined('MULTISITE')) {
            $post_vars['thumb'] = str_replace(home_url('/files'), ABSPATH . 'wp-content/blogs.dir/' . $blog_id . '/files', $post_vars['thumb']);
        }

        $post_vars['link_label'] = apply_filters('wpdm_button_image', $post_vars['link_label'], $post_vars);

        $post_vars['link_label'] = $post_vars['link_label']?$post_vars['link_label']:__('Download','download-manager');

        $post_vars['download_url'] = self::getDownloadURL($post_vars['ID']);
        $post_vars['download_link_popup'] =
        $post_vars['download_link_extended'] =
        $post_vars['download_link'] = (int)get_option('__wpdm_mask_dlink', 1) == 1?"<a class='wpdm-download-link download-on-click {$post_vars['btnclass']}' rel='nofollow' href='#' data-downloadurl=\"{$post_vars['download_url']}\">{$post_vars['link_label']}</a>":"<a class='wpdm-download-link {$post_vars['btnclass']}' rel='nofollow' href='{$post_vars['download_url']}'>{$post_vars['link_label']}</a>";
        $post_vars['play_button'] = self::audioPlayer($post_vars);
        $post_vars['audio_player'] = self::audioPlayer($post_vars, true, 'full');

        $limit_over = 0;
        $alert_size = ($template_type == 'link')?'alert-sm':'';
        if (self::userDownloadLimitExceeded($post_vars['ID'])) {
            $limit_over = 1;
            $post_vars['download_url'] = '#';
            $post_vars['link_label'] = __('Download Limit Exceeded','download-manager');
            $post_vars['download_link_popup'] =
            $post_vars['download_link_extended'] =
            $post_vars['download_link'] = "<div class='alert alert-warning {$alert_size}' data-title='".__('DOWNLOAD ERROR','download-manager')."'><i class='fas fa-arrow-alt-circle-down'></i> {$post_vars['link_label']}</div>";
        }

        else if (isset($post_vars['expire_date']) && $post_vars['expire_date'] != "" && strtotime($post_vars['expire_date']) < time()) {
            $post_vars['download_url'] = '#';
            $post_vars['link_label'] = __('Download was expired on','download-manager') . " " . date_i18n(get_option('date_format')." h:i A", strtotime($post_vars['expire_date']));
            $post_vars['download_link'] =
            $post_vars['download_link_extended'] =
            $post_vars['download_link_popup'] = "<div class='alert alert-warning {$alert_size}' data-title='".__('DOWNLOAD ERROR','download-manager')."'><i class='fas fa-arrow-alt-circle-down'></i> {$post_vars['link_label']}</div>";
        }

        else if (isset($post_vars['publish_date']) && $post_vars['publish_date'] !='' && strtotime($post_vars['publish_date']) > time()) {
            $post_vars['download_url'] = '#';
            $post_vars['link_label'] = __('Download will be available from ','download-manager') . " " . date_i18n(get_option('date_format')." h:i A", strtotime($post_vars['publish_date']));
            $post_vars['download_link'] =
            $post_vars['download_link_extended'] =
            $post_vars['download_link_popup'] = "<div class='alert alert-warning {$alert_size}' data-title='".__('DOWNLOAD ERROR','download-manager')."'><i class='fas fa-arrow-alt-circle-down'></i> {$post_vars['link_label']}</div>";
        }

        else if(is_user_logged_in() && !self::userCanAccess($post_vars['ID'])){
            $post_vars['download_url'] = '#';
            $post_vars['link_label'] = stripslashes(get_option('wpdm_permission_msg'));
            $post_vars['download_link'] =
            $post_vars['download_link_extended'] =
            $post_vars['download_link_popup'] = "<div class='alert alert-danger {$alert_size}' data-title='".__('DOWNLOAD ERROR','download-manager')."'><i class='fas fa-arrow-alt-circle-down'></i> {$post_vars['link_label']}</div>";
        }

        else if(!is_user_logged_in() && count(self::AllowedRoles($post_vars['ID'])) >= 0 && !self::userCanAccess($post_vars['ID'])){
            $loginform = wpdm_login_form(array('redirect'=>get_permalink($post_vars['ID'])));
            $post_vars['download_url'] = home_url('/wp-login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
            $post_vars['download_link'] =
            $post_vars['download_link_extended'] =
            $post_vars['download_link_popup'] = stripcslashes(str_replace(array("[loginform]","[this_url]", "[package_url]"), array($loginform, $_SERVER['REQUEST_URI'],get_permalink($post_vars['ID'])), $loginmsg));
            $post_vars['download_link'] =
            $post_vars['download_link_extended'] =
            $post_vars['download_link_popup'] = get_option('__wpdm_login_form', 0) == 1 ? $loginform : $post_vars['download_link'];
        }

        else if(self::isLocked($post_vars)){
            $post_vars['download_url'] = '#';
            $post_vars['download_link'] = "<a href='#' class='wpdm-download-link wpdm-download-locked {$post_vars['btnclass']}' data-package='{$post_vars['ID']}'>{$post_vars['link_label']}</a>"; //self::activeLocks($post_vars);
            $post_vars['download_link_extended'] = self::activeLocks($post_vars['ID'], array('embed' => 1));
            $post_vars['download_link_popup'] = self::activeLocks($post_vars['ID'], array('popstyle' => 'popup'));
        }

        if(isset($data['terms_lock']) && $data['terms_lock'] != 0 && (!function_exists('wpdmpp_effective_price') || wpdmpp_effective_price($post_vars['ID']) ==0) && $limit_over == 0){
            $data['terms_conditions'] = wpautop(strip_tags($data['terms_conditions'], "<p><br><a><strong><b><i>"));
            $data['terms_title'] = !isset($data['terms_title']) || $data['terms_title'] == ''?__("Terms and Conditions",'download-manager'):sanitize_text_field($data['terms_title']);
            $data['terms_check_label'] = !isset($data['terms_check_label']) || $data['terms_check_label'] == ''?__("I Agree With Terms and Conditions",'download-manager'):sanitize_text_field($data['terms_check_label']);
            if(!self::isLocked($post_vars)) {
                $post_vars['download_link_popup'] = $post_vars['download_link'] = "<a href='#unlock' class='wpdm-download-link wpdm-download-locked {$post_vars['btnclass']}' data-package='{$post_vars['ID']}'>{$post_vars['link_label']}</a>";
            }
            $data['terms_conditions'] = wpautop(strip_tags(\WPDM_Messages::decode_html($data['terms_conditions']), "<p><br><a><strong><b><i>"));
            $post_vars['download_link_extended'] = "<div class='panel panel-default card card terms-panel' style='margin: 0'><div class='panel-heading card-header'>{$data['terms_title']}</div><div class='panel-body card-body' style='max-height: 200px;overflow: auto'>{$data['terms_conditions']}</div><div class='panel-footer card-footer'><label><input data-pid='{$post_vars['ID']}' class='wpdm-checkbox terms_checkbox terms_checkbox_{$post_vars['ID']}' type='checkbox' onclick='jQuery(\".download_footer_{$post_vars['ID']}\").slideToggle();'> {$data['terms_check_label']}</label></div><div class='panel-footer card-footer download_footer_{$post_vars['ID']}' style='display:none;'>{$post_vars['download_link_extended']}</div></div>";

        }

        if (!isset($post_vars['formatted'])) $post_vars['formatted'] = 0;
        ++$post_vars['formatted'];

        $post_vars = apply_filters('wpdm_after_prepare_package_data', $post_vars);

        $this->PackageData =  $post_vars;

        foreach($post_vars as $key => $val){
            try {
                if(preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/',$key))
                    $this->$key = $val;
            }catch (\Exception $e){}
        }
        return $this;
    }

    /**
     * @usage Get all or any specific package info
     * @param $ID
     * @param null $meta
     * @return mixed
     */
    public static function Get($ID, $meta = null){
        $ID = (int)$ID;
        if($ID == 0) return null;
        if($meta != null)
            return get_post_meta($ID, "__wpdm_".$meta, true);
        $p = new Package();
        $package = $p->Prepare($ID);
        return $package->PackageData;
    }

    /**
     * @usage Verify single file download option
     * @param null $ID
     * @return mixed|void
     */
    public static function isSingleFileDownloadAllowed($ID = null){
        global $post;
        if(!$ID && $post->post_type == 'wpdmpro') $ID = $post->ID;
        $global = get_option('__wpdm_individual_file_download', 1);
        $package = get_post_meta($ID,'__wpdm_individual_file_download', true);
        $effective = $package == -1 || $package == '' ? $global:$package;
        return $effective;
    }

    /**
     * @param $id
     * @usage Returns the user roles who has access to specified package
     * @return array|mixed
     */
    public static function AllowedRoles($id){
        $roles = get_post_meta($id, '__wpdm_access', true);
        $roles = maybe_unserialize($roles);
        $cats = get_the_terms( $id, 'wpdmcategory' );
        if(!is_array($roles)) $roles = array();
        if(is_array($cats)){
            foreach($cats as $cat){
                $croles = \WPDM\libs\CategoryHandler::GetAllowedRoles($cat->term_id);
                $roles = array_merge($roles, $croles);
            }}

        $roles = array_unique($roles);

        $roles = apply_filters("wpdm_allowed_roles", $roles, $id);

        return $roles;
    }

    /**
     * @usage Check if a package is locked or public
     * @param $id
     * @return bool
     */
    public static function isLocked($package){
        if(!is_array($package) && (int)$package > 0) {
            $id = $package;
            $package = array();
            $package['ID'] = $id;

            $package['password_lock'] = (int)get_post_meta($id, '__wpdm_password_lock', true);

            $package['captcha_lock'] = (int)get_post_meta($id, '__wpdm_captcha_lock', true);

        } else
            $id = $package['ID'];
        $lock = '';

        if (isset($package['password_lock']) && (int)$package['password_lock'] === 1) $lock = 'locked';

        if (isset($package['captcha_lock']) && (int)$package['captcha_lock'] === 1) $lock = 'locked';

        if ($lock !== 'locked')
            $lock = apply_filters('wpdm_check_lock', $lock, $id);

        return ( $lock === 'locked' );


    }

    /**
     * @usage Check if current user can access to package or category
     * @param $id
     * @param string $type
     *
     * @return bool
     */
    public static function userCanAccess($ID, $type = 'package'){
        global $current_user;

        if($type=='package')
            $roles = self::AllowedRoles($ID);
        else $roles = \WPDM\libs\CategoryHandler::GetAllowedRoles($ID);

        $matched = is_user_logged_in()?array_intersect($current_user->roles, $roles):array();

        if(in_array('guest', $roles)) return true;
        if(count($matched) > 0) return true;

        return false;

    }

    /**
     * @usage Check if current user has access to package or category
     * @param $id
     * @param string $type
     *
     * @return bool
     */
    public static function userHasAccess($user_id, $ID, $type = 'package'){

        $user = get_user_by('ID', $user_id);

        if($type=='package')
            $roles = self::allowedRoles($ID);
        else $roles = \WPDM\libs\CategoryHandler::getAllowedRoles($ID);
        if(!is_array($roles)) $roles = array();
        $matched = is_user_logged_in()?array_intersect($user->roles, $roles):array();
        if($type == 'category' && count($roles) == 0) return true;
        if(in_array('guest', $roles)) return true;
        if(count($matched) > 0) return true;
        return false;

    }

    /**
     * @usage Check user's download limit
     * @param $ID
     * @return bool
     */
    public static function userDownloadLimitExceeded($ID){
        global $current_user;

        if (is_user_logged_in())
            $index = $current_user->ID;
        else
            $index = $_SERVER['REMOTE_ADDR'];

        $udl = maybe_unserialize(get_post_meta($ID, "__wpdmx_user_download_count", true));
        $td = isset($udl[$index])?$udl[$index]:0;
        $mx = get_post_meta($ID, '__wpdm_download_limit_per_user', true);

        $stock = get_post_meta($ID, '__wpdm_quota', true);
        $download_count = get_post_meta($ID, '__wpdm_download_count', true);
        if($stock > 0 && $download_count >= $stock) return true;

        if ($mx > 0 && $td >= $mx) return true;
        return false;
    }

    /**
     * @usage Check if user is can download this package
     * @param $ID
     * @return bool
     */
    public static function userCanDownload($ID){
        return self::UserCanAccess($ID) && self::userDownloadLimitExceeded($ID);
    }

    /**
     * @usage Count files in a package
     * @param $id
     * @return int
     */
    public static function fileCount($ID){

        $count = count(self::getFiles($ID));

        return $count;

    }

    /**
     * @usage Get list of attached files & all files inside attached dir with a package
     * @param $ID
     * @return array|mixed
     */
    public static function getFiles($ID){
        $files = get_post_meta($ID, '__wpdm_files', true);
        $package_dir = self::Get($ID, 'package_dir');
        if($package_dir != '') {
            $files += \WPDM\libs\FileSystem::scanDir($package_dir);
        }
        return $files;
    }

    /**
     * @usage Create zip from attached files
     * @param $ID
     * @return mixed|string|\WP_Error
     */
    public static function Zip($ID){
        $files = self::getFiles($ID);
        $zipped = get_post_meta( $ID , "__wpdm_zipped_file", true);
        if(count($files) > 0) {
            if ($zipped == '' || !file_exists($zipped)) {
                $zipped = UPLOAD_DIR . sanitize_file_name(get_the_title($ID)) . '-' . $ID . '.zip';
                $zipped = \WPDM\libs\FileSystem::zipFiles($files, $zipped);
                return $zipped;
            }
        }
        return new \WP_Error(404, __('No File Attached!','download-manager'));
    }

    /**
     * @usage Calculate package size
     * @param $ID
     * @param bool|false $recalculate
     * @return bool|float|int|mixed|string
     */
    public static function Size($ID, $recalculate = false){

        if(get_post_type($ID) !='wpdmpro') return false;

        $size = esc_attr(get_post_meta($ID, '__wpdm_package_size', true));

        if($size!="" && !$recalculate) return $size;

        $files = self::getFiles($ID);

        $size = 0;
        if (is_array($files)) {
            foreach ($files as $f) {
                $f = trim($f);
                if (file_exists($f))
                    $size += @filesize($f);
                else
                    $size += @filesize(UPLOAD_DIR . $f);
            }
        }


        update_post_meta($ID, '__wpdm_package_size_b', $size);
        $size = $size / 1024;
        if ($size > 1024) $size = number_format($size / 1024, 2) . ' MB';
        else $size = number_format($size, 2) . ' KB';
        update_post_meta($ID, '__wpdm_package_size', $size);
        return $size;
    }

    /**
     * @usage Generate play button for link template
     * @param $package
     * @param bool $return
     * @param $style
     * @return mixed|string|void
     */
    public static function audioPlayer($package, $return  = true, $style = 'primary btn-lg wpdm-btn-play-lg' )
    {

        $audiohtml = "";

        if (!is_array($package['files']) || count($package['files']) == 0) return;
        $audios = array();
        $nonce = wp_create_nonce($_SERVER['REQUEST_URI']);
        $audio = $audx = null;
        foreach($package['files'] as $index => $file){
            $realpath = file_exists($file)?$file:UPLOAD_DIR.$file;
            $filetype = wp_check_filetype( $realpath );
            $tmpvar = explode('/',$filetype['type']);
            if($tmpvar[0]=='audio') {
                $audio = $file;
                $audx = $index;
                break;
            }
        }

        if($audio != null){
            $song = home_url("/?wpdmdl={$package['ID']}&play=".basename($audio));
            if($style === 'full')
                $audiohtml = do_shortcode("[audio src='$song']");
            else
                $audiohtml = "<button data-player='wpdm-audio-player' data-song='{$song}' class='btn btn-{$style} wpdm-btn-play'><i class='fa fa-play'></i></button>";


        }

        if($return)
            return $audiohtml;

        echo  $audiohtml;

    }

    /**
     * @param $ID
     * @param $files
     * @param int $width
     * @return string
     */
    public static function videoPlayer($ID, $files = null, $width = 800){

        if(!$files)
            $files = \WPDM\Package::getFiles($ID);

        $videos = array();
        foreach($files as $index => $file){
            $realpath = file_exists($file)?$file:UPLOAD_DIR.$file;
            $filetype = wp_check_filetype( $realpath );
            $tmpvar = explode('/',$filetype['type']);
            if($tmpvar[0]=='video') {
                $videos[] = $file;
                $vidx[] = $index;
            }

        }

        $videothumbs = "";
        $mpvs = get_post_meta($ID,'__wpdm_additional_previews', true);
        $mmv = 0;

        if(is_array($mpvs) && count($mpvs) > 1 && count($videos) > 1) {

            foreach ($mpvs as $i => $mpv) {
                if($mmv < count($videos) ) {
                    //$url = self::expirableDownloadLink($ID, 3);
                    $ind = $vidx[$i]; //\WPDM_Crypt::Encrypt($videos[$mmv]);
                    //$video = $url . "&ind={$ind}&play=" . basename($videos[$mmv]);
                    $video = \WPDM\libs\FileSystem::mediaURL($ID, $ind, wpdm_basename($videos[$mmv]));

                    $videothumbs .= "<a href='#' data-video='{$video}' class='__wpdm_playvideo'><img class='thumbnail' src='" . wpdm_dynamic_thumb($mpv, array(64, 64)) . "'/></a>";
                }
                $mmv++;
            }
        }

        $player_html = '';
        if(count($videos)>0) {
            //$url = self::expirableDownloadLink($ID, 10);
            $ind = $vidx[0]; //\WPDM_Crypt::Encrypt($videos[0]);
            //$video = $url . "&ind={$ind}&play=" . basename($videos[0]);
            $video = \WPDM\libs\FileSystem::mediaURL($ID, $ind, wpdm_basename($videos[0]));

            $player_html = "<video id='__wpdm_videoplayer' class='thumbnail' width=\"{$width}\" controls><source src=\"{$video}\" type=\"video/mp4\">Your browser does not support HTML5 video.</video><div class='videothumbs'>{$videothumbs}</div>";
            //if(!\WPDM\Package::userCanAccess($ID)) $player_html = \WPDM_Messages::Error(stripslashes(get_option('wpdm_permission_msg')), -1);
        }

        $player_html = apply_filters("wpdm_video_player_html", $player_html, $ID, $file, $width);
        return $player_html;
    }

    /**
     * @usage Get All Custom Data of a Package
     * @param $pid
     * @return array
     */
    public static function metaData($ID)
    {
        $cdata = get_post_custom($ID);

        $data = array();
        if(is_array($cdata)){
            foreach ($cdata as $k => $v) {
                if(!strstr($k, '_wpdmkey_') && !strstr($k, '_wpdmx_')) {
                    $k = str_replace("__wpdm_", "", $k);
                    $data[$k] = maybe_unserialize($v[0]);
                }
            }}

        if(!isset($data['access']) || !is_array($data['access'])) $data['access'] = array();
        $data['download_count'] = isset($data['download_count'])? intval($data['download_count']):0;
        $data['view_count'] = isset($data['view_count'])? intval($data['view_count']):0;
        $data['version'] = isset($data['version'])? $data['version']:'1.0.0';
        $data['quota'] = isset($data['quota']) && $data['quota'] > 0? $data['quota']:'&#8734;';
        $data =  apply_filters('wpdm_custom_data',$data, $ID);
        return $data;
    }

    /**
     * @usage Generate download link of a package
     * @param $package
     * @param int $embed
     * @param array $extras
     * @return string
     */
    function prepareDownloadLink(&$package, $embed = 0, $extras = array())
    {
        global $wpdb, $current_user, $wpdm_download_icon, $wpdm_download_lock_icon, $btnclass;
        if(is_array($extras))
            extract($extras);
        $data = '';
        //get_currentuserinfo();
        $loginmsg = \WPDM_Messages::login_required();
        $package['link_url'] = home_url('/?download=1&');
        $package['link_label'] = !isset($package['link_label']) || $package['link_label'] == '' ? __("Download",'download-manager') : $package['link_label'];

        //Change link label using a button image
        $package['link_label'] = apply_filters('wpdm_button_image', $package['link_label'], $package);


        $package['download_url'] = wpdm_download_url($package);
        if (wpdm_is_download_limit_exceed($package['ID'])) {
            $package['download_url'] = '#';
            $package['link_label'] = __('Download Limit Exceeded','download-manager');
        }
        if (isset($package['expire_date']) && $package['expire_date'] != "" && strtotime($package['expire_date']) < time()) {
            $package['download_url'] = '#';
            $package['link_label'] = __('Download was expired on','download-manager') . " " . date_i18n(get_option('date_format')." h:i A", strtotime($package['expire_date']));
            $package['download_link'] = "<a href='#'>{$package['link_label']}</a>";
            return "<div class='alert alert-warning'><b>" . __('Download:','download-manager') . "</b><br/>{$package['link_label']}</div>";
        }

        if (isset($package['publish_date']) && $package['publish_date'] !='' && strtotime($package['publish_date']) > time()) {
            $package['download_url'] = '#';
            $package['link_label'] = __('Download will be available from ','download-manager') . " " . date_i18n(get_option('date_format')." h:i A", strtotime($package['publish_date']));
            $package['download_link'] = "<a href='#'>{$package['link_label']}</a>";
            return "<div class='alert alert-warning'><b>" . __('Download:','download-manager') . "</b><br/>{$package['link_label']}</div>";
        }

        $link_label = isset($package['link_label']) ? $package['link_label'] : __('Download','download-manager');

        $package['access'] = wpdm_allowed_roles($package['ID']);

        if ($package['download_url'] != '#')
            $package['download_link'] = "<a class='wpdm-download-link  download-on-click {$btnclass}' rel='nofollow' href='#' data-downloadurl=\"{$package['download_url']}\"><i class='$wpdm_download_icon'></i>{$link_label}</a>";
        else
            $package['download_link'] = "<div class='alert alert-warning'><b>" . __('Download:','download-manager') . "</b><br/>{$link_label}</div>";
        $caps = array_keys($current_user->caps);
        $role = array_shift($caps);

        $matched = (is_array(@maybe_unserialize($package['access'])) && is_user_logged_in())?array_intersect($current_user->roles, @maybe_unserialize($package['access'])):array();

        $skiplink = 0;

        if (is_user_logged_in() && count($matched) <= 0 && !@in_array('guest', @maybe_unserialize($package['access']))) {
            $package['download_url'] = "#";
            $package['download_link'] = $package['download_link_extended'] = stripslashes(get_option('wpdm_permission_msg'));
            $package = apply_filters('download_link', $package);
            if (get_option('_wpdm_hide_all', 0) == 1) { $package['download_link'] = $package['download_link_extended'] = 'blocked'; }
            return $package['download_link'];
        }
        if (!@in_array('guest', @maybe_unserialize($package['access'])) && !is_user_logged_in()) {

            $loginform = wpdm_login_form(array('redirect'=>get_permalink($package['ID'])));
            if (get_option('_wpdm_hide_all', 0) == 1) return 'loginform';
            $package['download_url'] = home_url('/wp-login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
            $package['download_link'] = stripcslashes(str_replace(array("[loginform]","[this_url]"), array($loginform,get_permalink($package['ID'])), $loginmsg));
            return get_option('__wpdm_login_form', 0) == 1 ? $loginform : $package['download_link'];

        }

        $package = apply_filters('download_link', $package);

        $unqid = uniqid();
        if (!isset($package['quota']) || (isset($package['quota']) && $package['quota'] > 0 && $package['quota'] > $package['download_count']) || $package['quota'] == 0) {
            $lock = 0;

            //isset($package['password_lock']) && (int)$package['password_lock'] == 1 &&
            if ($package['password'] != '') {
                $lock = 'locked';
                $data = \WPDM\PackageLocks::AskPassword($package);
            }


            $sociallock = "";

            if (isset($package['captcha_lock']) && (int)$package['captcha_lock'] == 1) {
                $lock = 'locked';
                $sociallock .=  \WPDM\PackageLocks::reCaptchaLock($package , true);

            }

            $extralocks = '';
            $extralocks = apply_filters("wpdm_download_lock", $extralocks, $package);

            if (is_array($extralocks) && $extralocks['lock'] === 'locked') {

                if(isset($extralocks['type']) && $extralocks['type'] == 'social')
                    $sociallock .= $extralocks['html'];
                else
                    $data .= $extralocks['html'];

                $lock = 'locked';
            }

            if($sociallock!=""){
                $data .= "<div class='panel panel-default'><div class='panel-heading'>".__("Download",'download-manager')."</div><div class='panel-body wpdm-social-locks text-center'>{$sociallock}</div></div>";
            }

            if ($lock === 'locked') {
                $popstyle = isset($popstyle) && in_array($popstyle, array('popup', 'pop-over')) ? $popstyle : 'pop-over';
                if ($embed == 1)
                    $adata = "<div class='package-locks'>" . $data . "</div>";
                else {

                    $adata = '<a href="#pkg_' . $package['ID'] . "_" . $unqid . '"  data-package="'.$package['ID'].'" class="wpdm-download-link wpdm-download-locked ' . $popstyle . ' ' . $btnclass . '"><i class=\'' . $wpdm_download_lock_icon . '\'></i>' . $package['link_label'] . '</a>';
                }

                $data = $adata;
            }
            if ($lock !== 'locked') {

                $data = $package['download_link'];


            }
        }
        else {
            $data = __("Download limit exceeded!",'download-manager');
        }


        //return str_replace(array("\r","\n"),"",$data);
        return $data;

    }

    private static function activeLocks($ID, $params = array('embed' => 0, 'popstyle' => 'pop-over')){

        $embed = isset($params['embed'])?$params['embed']:0;
        $template_type = isset($params['template_type'])?$params['template_type']:'link';
        $popstyle = isset($params['popstyle'])?$params['popstyle']:'pop-over';

        $package = array(
            'ID'                 => $ID,
            'password'           => get_post_meta($ID, '__wpdm_password', true),
            'password_lock'      => get_post_meta($ID, '__wpdm_password_lock', true),
            'email_lock'         => get_post_meta($ID, '__wpdm_email_lock', true),
            'linkedin_lock'      => get_post_meta($ID, '__wpdm_linkedin_lock', true),
            'twitterfollow_lock' => get_post_meta($ID, '__wpdm_twitterfollow_lock', true),
            'gplusone_lock'      => get_post_meta($ID, '__wpdm_gplusone_lock', true),
            'tweet_lock'         => get_post_meta($ID, '__wpdm_tweet_lock', true),
            'facebooklike_lock'  => get_post_meta($ID, '__wpdm_facebooklike_lock', true),
            'captcha_lock'       => get_post_meta($ID, '__wpdm_captcha_lock', true),
        );

        $package = apply_filters('wpdm_before_apply_locks', $package);
        $lock = $data = "";
        $unqid = uniqid();

        if (isset($package['password_lock']) && (int)$package['password_lock'] == 1 && $package['password'] != '') {
            $lock = 'locked';
            $data = \WPDM\PackageLocks::AskPassword($package);
        }

        $sociallock = "";

        $extralocks = '';
        $extralocks = apply_filters("wpdm_download_lock", $extralocks, $package);

        if (is_array($extralocks) && $extralocks['lock'] === 'locked') {

            if(isset($extralocks['type']) && $extralocks['type'] == 'social')
                $sociallock .= $extralocks['html'];
            else
                $data .= $extralocks['html'];

            $lock = 'locked';
        }

        if( $sociallock !== "" ){
            $socdesc = get_option('_wpdm_social_lock_panel_desc', '');
            $socdesc = $socdesc !== '' ? "<p>{$socdesc}</p>" : "";
            $data .= "<div class='panel panel-default card'><div class='panel-heading card-header'>".get_option('_wpdm_social_lock_panel_title', 'Like or Share to Download')."</div><div class='panel-body card-body wpdm-social-locks text-center'>{$socdesc}{$sociallock}</div></div>";
        }


        if (isset($package['captcha_lock']) && (int)$package['captcha_lock'] == 1) {
            $lock = 'locked';
            $captcha =  \WPDM\PackageLocks::reCaptchaLock($package , true);
            $data .= "<div class='panel panel-default card'><div class='panel-heading card-header'>".__( "Verify CAPTCHA to Download" , "download-manager" )."</div><div class='panel-body card-body wpdm-social-locks text-center'>{$captcha}</div></div>";
        }

        if ($lock === 'locked') {
            $popstyle = isset($popstyle) && in_array($popstyle, array('popup', 'pop-over')) ? $popstyle : 'pop-over';
            if ($embed == 1)
                $adata = "</strong>{$data}";
            else {
                $link_label = get_post_meta($ID, '__wpdm_link_label', true);
                $link_label = trim($link_label) ? $link_label : __( "Download", "download-manager" );
                $style = wpdm_download_button_style(($template_type === 'page'), $ID);
                $style = isset($params['btnclass']) && $params['btnclass'] !== '' ? $params['btnclass'] : $style;
                $adata = '<a href="#pkg_' . $ID . "_" . $unqid . '"  data-package="'.$ID.'" data-trigger="manual" class="wpdm-download-link wpdm-download-locked ' . $style . '">' . $link_label . '</a>';

            }

            $data = $adata;
        }
        return $data;
    }


    /**
     * @usage Generate download link of a package
     * @param $package
     * @param int $embed
     * @param array $extras
     * @return string
     */
    public static function downloadLink($ID, $embed = 0, $extras = array())
    {
        global $wpdb, $current_user, $wpdm_download_icon, $wpdm_download_lock_icon;
        if(is_array($extras))
            extract($extras);
        $data = '';

        $template_type = isset($extras['template_type'])?$extras['template_type']:'page';

        //$package = self::get($ID);

        $link_label = get_post_meta($ID, '__wpdm_link_label', true);
        $link_label = (trim($link_label) === '') ? __( "Download" , "download-manager" ) : $link_label;

        $template_type = isset($template_type) ? $template_type : 'link';
        $link_label = apply_filters("wpdm_link_label", $link_label, $ID, $template_type);

        $loginmsg = \WPDM_Messages::login_required($ID);

        $download_url = self::getDownloadURL($ID);

        $limit_over = 0;
        $alert_size = ($template_type == 'link')?'alert-sm':'';

        if (self::userDownloadLimitExceeded($ID)) {
            $limit_msg = \WPDM_Messages::download_limit_exceeded($ID);
            $download_url = '#';
            $link_label = $limit_msg;
            $limit_over = 1;
        }

        $expired = get_post_meta($ID, '__wpdm_expire_date', true);
        $publish = get_post_meta($ID, '__wpdm_publish_date', true);

        if ( $expired !== "" && strtotime($expired) < time()) {
            $download_url = '#';
            $link_label = __( "Download was expired on" , "download-manager" ) . " " . date_i18n(get_option('date_format')." h:i A", strtotime($expired));
            return "<div class='alert alert-warning  {$alert_size}' data-title='" . __( "DOWNLOAD ERROR:" , "download-manager" ) . "'>{$link_label}</div>";
        }

        if ($publish !== "" && strtotime($publish) > time()) {
            $download_url = '#';
            $link_label = __( "Download will be available from " , "download-manager" ) . " " . date_i18n(get_option('date_format')." h:i A", strtotime($publish));
            return "<div class='alert alert-warning  {$alert_size}' data-title='" . __( "DOWNLOAD ERROR:" , "download-manager" ) . "'>{$link_label}</div>";
        }

        $access = self::AllowedRoles($ID);
        if(!is_array($access)) $access = array();
        $style = wpdm_download_button_style(($template_type === 'page'), $ID);
        $style = isset($btnclass) && $btnclass !== '' ? $btnclass : $style;

        if ($download_url != '#')
            $download_link = $download_link_extended = $download_link_popup = (int)get_option('__wpdm_mask_dlink', 1) === 1 ? "<a class='wpdm-download-link  download-on-click {$style}' rel='nofollow' href='#' data-downloadurl=\"{$download_url}\">{$link_label}</a>" : "<a class='wpdm-download-link {$style}' rel='nofollow' href='{$download_url}'>{$link_label}</a>";
        //$download_link = $download_link_extended = $download_link_popup = (int)get_option('__wpdm_mask_dlink', 1) === 1 ? "<a class='wpdm-download-link {$style}' rel='nofollow' href='#' onclick=\"location.href='{$download_url}';return false;\">{$link_label}</a>" : "<a class='wpdm-download-link {$style}' rel='nofollow' href='{$download_url}'>{$link_label}</a>";
        else
            $download_link = "<div class='alert alert-warning {$alert_size}' data-title='" . __( "DOWNLOAD ERROR:" , "download-manager" ) . "'>{$link_label}</div>";

        $access = maybe_unserialize(get_post_meta($ID, '__wpdm_access', true));
        if(!is_array($access)) $access = [];
        $caps = is_user_logged_in() ? array_keys($current_user->caps) : array();
        $role = array_shift($caps);

        $matched = (is_array($access) && is_user_logged_in())?array_intersect($current_user->roles, $access):array();
        if(!$matched) $matched = [];

        $skiplink = 0;

        //User does't have permission to download
        if (is_user_logged_in() && count($matched) <= 0 && !@in_array('guest', $access)) {
            $download_url = "#";
            $download_link = $download_link_extended = $download_link_popup =  \WPDM_Messages::permission_denied($ID);
            if (get_option('_wpdm_hide_all', 0) == 1) { $download_link = $download_link_extended = $download_link_popup =  'blocked'; }
            return $download_link;
        }

        //Login is required to download
        if (!@in_array('guest', $access) && !is_user_logged_in()) {

            $loginform = WPDM()->shortCode->loginForm(array('redirect' => $_SERVER['REQUEST_URI']));
            if (get_option('_wpdm_hide_all', 0) == 1) {
                $hide_all_message = get_option('__wpdm_login_form', 0) == 1 ? $loginform : stripcslashes(str_replace(array("[loginform]","[this_url]", "[package_url]"), array($loginform, $_SERVER['REQUEST_URI'],get_permalink($ID)), $loginmsg));
                if($template_type == 'link')
                    return "<a href='".wpdm_login_url($_SERVER['REQUEST_URI'])."' class='btn btn-danger'>".__( "Login" , "download-manager" )."</a>";
                else
                    return $hide_all_message;
            }
            $download_url = home_url('/wp-login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
            $download_link = $download_link_extended = $download_link_popup = stripcslashes(str_replace(array("[loginform]","[this_url]", "[package_url]"), array($loginform, $_SERVER['REQUEST_URI'],get_permalink($ID)), $loginmsg));
            return get_option('__wpdm_login_form', 0) == 1 ? $loginform : $download_link;

        }

        //$package = apply_filters('wpdm_before_apply_locks', $package);
        //$package = apply_filters('wpdm_after_prepare_package_data', $package);

        $unqid = uniqid();
        $stock_limit = (int)get_post_meta($ID, '__wpdm_quota', true);
        $download_count = (int)get_post_meta($ID, '__wpdm_download_count', true);
        if ($stock_limit > $download_count || $stock_limit == 0) {
            $lock = 0;

            $extras['embed'] = $embed;
            $data = self::activeLocks($ID, $extras);

            $terms_lock = (int)get_post_meta($ID, '__wpdm_terms_lock', true);
            $terms_title = get_post_meta($ID, '__wpdm_terms_title', true);
            $terms_conditions = get_post_meta($ID, '__wpdm_terms_conditions', true);
            $terms_check_label = get_post_meta($ID, '__wpdm_terms_check_label', true);
            if($terms_lock !== 0 && (!function_exists('wpdmpp_effective_price') || wpdmpp_effective_price($ID) == 0)){
                if(!self::isLocked($ID) && !$embed) {
                    $data = "<a href='#unlock' class='wpdm-download-link wpdm-download-locked {$style}' data-package='{$ID}'>{$link_label}</a>";
                } else {
                    $data = $data ? $data : $download_link;
                }
                if($embed == 1)
                    $data = "<div class='panel panel-default card terms-panel' style='margin: 0 0 10px 0'><div class='panel-heading card-header'>{$terms_title}</div><div class='panel-body card-body' style='max-height: 200px;overflow: auto'>{$terms_conditions}</div><div class='panel-footer card-footer'><label><input data-pid='{$ID}' class='wpdm-checkbox terms_checkbox terms_checkbox_{$ID}' type='checkbox'> {$terms_check_label}</label></div><div class='panel-footer card-footer bg-white download_footer_{$ID}' style='display:none;'>{$data}</div></div><script>jQuery(function($){ $('#wpdm-filelist-{$ID} .btn.inddl, #xfilelist .btn.inddl').attr('disabled', 'disabled'); });</script>";

            }

            if($data!=""){
                $data = apply_filters('wpdm_download_link', $data, array('ID' => $ID, 'id' => $ID));
                return $data;
            }


            $data = $download_link;



        }
        else {
            $data = "<button class='btn btn-danger btn-block' type='button' disabled='disabled' data-title='DOWNLOAD ERROR:'>".__( "Limit Over!" , "download-manager" )."</button>";
        }
        if($data == 'loginform') return WPDM()->shortCode->loginForm();
        $data = apply_filters('wpdm_download_link', $data, array('ID' => $ID, 'id' => $ID));
        return $data;

    }

    /**
     * @param $ID
     * @param int $usageLimit
     * @param int $expirePeriod seconds
     * @return string
     */
    static function expirableDownloadLink($ID, $usageLimit = 10, $expirePeriod = 999999){
        $key = uniqid();
        $exp = array('use' => $usageLimit, 'expire' => time()+$expirePeriod);
        update_post_meta($ID, "__wpdmkey_".$key, $exp);
        Session::set( '__wpdm_unlocked_'.$ID , 1 );
        $download_url = self::getDownloadURL($ID, "_wpdmkey={$key}");
        return $download_url;
    }

    /**
     * @usage Generate download url for public/open downloads, the url will not work for the packages with lock option
     * @param $ID
     * @param $ext
     * @return string
     */
    public static function getDownloadURL($ID, $ext = ''){
        if(self::isLocked($ID) && !Session::get( '__wpdm_unlocked_'.$ID )) return '#locked';
        if ($ext) $ext = '&' . $ext;
        $permalink = get_permalink($ID);
        $sap = strpos($permalink, '?')?'&':'?';
        return $permalink.$sap."wpdmdl={$ID}{$ext}&refresh=".uniqid().time();
    }

    public static function getMasterDownloadURL($ID){
        $packageURL = get_permalink($ID);
        $packageURL .= (get_option('permalink_structure', false)?'?':'&').'wpdmdl='.$ID.'&masterkey='.get_post_meta($ID, '__wpdm_masterkey', true);
        return $packageURL;
    }

    /**
     * @param $ID
     * @param $Key
     * @return bool
     */
    public static function validateMasterKey($ID, $Key){
        if($Key === '') return false;
        $masterKey = get_post_meta($ID, '__wpdm_masterkey');
        if($masterKey === '') return false;
        if($masterKey === $Key) return true;
        return false;
    }

    /**
     * @usage Fetch link/page template and return generated html
     * @param $template
     * @param $vars
     * @param string $type
     * @return mixed|string
     */
    public static function fetchTemplate($template, $vars, $type = 'link')
    {

        if(!is_array($vars) && is_int($vars) && $vars > 0) $vars = array('ID' => $vars);
        if (!isset($vars['ID']) || intval($vars['ID']) <1 ) return '';
        $loginmsg = \WPDM_Messages::login_required();
        $default['link'] =  'link-template-default.php';
        $default['page'] =  'page-template-default.php';

        if (!isset($vars['formatted'])) {
            $pack = new \WPDM\Package($vars['ID']);
            $pack->prepare(null, null, $type);
            $vars = $pack->PackageData;
        }

        if ($template == '') {
            if(!isset($vars['page_template'])) $vars['page_template'] = 'page-template-default.php';
            $template = $type == 'page' ? $vars['page_template'] : $vars['template'];
        }

        if ($template == '')
            $template = $default[$type];

        $templates = maybe_unserialize(get_option("_fm_".$type."_templates", true));
        if(isset($templates[$template]) && isset($templates[$template]['content'])) $template = $templates[$template]['content'];
        else
            if(!strpos(strip_tags($template), "]")){
                $template = wpdm_basename($template);
                $ltpldir = get_stylesheet_directory().'/download-manager/'.$type.'-templates/';
                $pthemeltpldir = get_template_directory().'/download-manager/'.$type.'-templates/';

                if(!file_exists($ltpldir) || !file_exists($ltpldir.$template))
                    $ltpldir = WPDM_BASE_DIR.'tpls/'.$type.'-templates/';

                if (file_exists($ltpldir . $template)) $template = file_get_contents($ltpldir . $template);
                else if (file_exists($pthemeltpldir . '/' . $template)) $template = file_get_contents($pthemeltpldir . '/' . $template);
                else if (file_exists($ltpldir . $template . '.php')) $template = file_get_contents($ltpldir . $template . '.php');
                else if (file_exists($pthemeltpldir . $template . '.php')) $template = file_get_contents($pthemeltpldir . $template . '.php');
                else if (file_exists($ltpldir. $type . "-template-" . $template . '.php')) $template = file_get_contents($ltpldir. $type . "-template-" . $template . '.php');
                else $template = file_get_contents(wpdm_tpl_path($default[$type], $ltpldir));
            }

        preg_match_all("/\[cf ([^\]]+)\]/", $template, $cfmatches);
        preg_match_all("/\[thumb_([0-9]+)x([0-9]+)\]/", $template, $matches);
        preg_match_all("/\[thumb_url_([0-9]+)x([0-9]+)\]/", $template, $umatches);
        preg_match_all("/\[excerpt_([0-9]+)\]/", $template, $xmatches);
        preg_match_all("/\[pdf_thumb_([0-9]+)x([0-9]+)\]/", $template, $pmatches);
        preg_match_all("/\[txt=([^\]]+)\]/", $template, $txtmatches);
        preg_match_all("/\[hide_empty:([^\]]+)\]/", $template, $hematches);
        preg_match_all("/\[video_player_([0-9]+)\]/", $template, $vdmatches);

        $thumb = wp_get_attachment_image_src(get_post_thumbnail_id($vars['ID']), 'full');
        $vars['preview'] = is_array($thumb) && isset($thumb['0']) ? $thumb['0'] : '';

        $pdf = isset($vars['files'],$vars['files'][0])?$vars['files'][0]:'';
        $ext = explode(".", $pdf);
        $ext = end($ext);

        foreach ($vdmatches[0] as $nd => $scode) {
            $scode = str_replace(array('[', ']'), '', $scode);
            $vars[$scode] =  self::videoPlayer($vars['ID'], $vars['files'], $vdmatches[1][$nd]);
        }

        //Replace all txt variables
        foreach ($txtmatches[0] as $nd => $scode) {
            $scode = str_replace(array('[', ']'), '', $scode);
            $vars[$scode] =  __($txtmatches[1][$nd], "download-manager");
        }

        // Parse [pdf_thumb] tag in link/page template
        if(strpos($template, 'pdf_thumb')) {
            if ($ext == 'pdf')
                $vars['pdf_thumb'] = "<img alt='{$vars['title']}' src='" . wpdm_pdf_thumbnail($pdf, $vars['ID']) . "' />";
            else $vars['pdf_thumb'] = $vars['preview'] != '' ? "<img alt='{$vars['title']}' src='{$vars['preview']}' />" : "";
        }

        // Parse [pdf_thumb_WxH] tag in link/page template
        foreach ($pmatches[0] as $nd => $scode) {
            $keys[] = $scode;
            $imsrc  = wpdm_dynamic_thumb(wpdm_pdf_thumbnail($pdf, $vars['ID']), array($pmatches[1][$nd], $pmatches[2][$nd]));
            $values[] = $imsrc != '' ? "<img src='" . $imsrc . "' alt='{$vars['title']}' />" : '';
        }

        // Parse [file_type] tag in link/page template
        if(strpos($template, 'file_type')) {
            $vars['file_types'] = self::fileTypes($vars['ID'], false);
            $vars['file_type_icons'] = self::fileTypes($vars['ID']);
        }


        foreach ($matches[0] as $nd => $scode) {
            $keys[] = $scode;
            $imsrc  = wpdm_dynamic_thumb($vars['preview'], array($matches[1][$nd], $matches[2][$nd]));
            $values[] = $vars['preview'] != '' ? "<img src='" . $imsrc . "' alt='{$vars['title']}' />" : '';
        }

        foreach ($umatches[0] as $nd => $scode) {
            $keys[] = $scode;
            $values[] = $vars['preview'] != '' ? wpdm_dynamic_thumb($vars['preview'], array($umatches[1][$nd], $umatches[2][$nd])) : '';
        }

        foreach ($xmatches[0] as $nd => $scode) {
            $keys[] = $scode;
            $ss = substr(strip_tags($vars['description']), 0, intval($xmatches[1][$nd]));
            $tmp = explode(" ", substr(strip_tags($vars['description']), intval($xmatches[1][$nd])));
            $bw = array_shift($tmp);
            $ss .= $bw;
            $values[] = $ss . '...';
        }

        if(strpos($template, 'doc_preview'))
            $vars['doc_preview'] = self::docPreview($vars);

        foreach ($hematches[0] as $index => $hide_empty){
            $hide_empty = str_replace(array('[', ']'), '', $hide_empty);
            if(isset($vars[$hematches[1][$index]]) && ( $vars[$hematches[1][$index]] == '' || $vars[$hematches[1][$index]] == '0' ))
                $vars[$hide_empty] = 'wpdm_hide wpdm_remove_empty';
            else
                $value[$hide_empty] = '';
        }

        // If need to re-process any data before fetch template
        $vars = apply_filters("wdm_before_fetch_template", $vars, $template, $type);

        foreach ($vars as $key => $value) {
            if(!is_array($value)) {
                $keys[] = "[$key]";
                if(is_object($value) || is_array($value)) $value = json_encode($value);
                $values[] = $value;
            }
        }

        $loginform = wpdm_login_form(array('redirect'=>get_permalink($vars['ID'])));
        $hide_all_message = get_option('__wpdm_login_form', 0) == 1 ? $loginform : stripcslashes(str_replace(array("[loginform]","[this_url]"), array($loginform,get_permalink($vars['ID'])), $loginmsg));

        if ($vars['download_link'] == 'blocked' && $type == 'link') return "";
        if ($vars['download_link'] == 'blocked' && $type == 'page') return get_option('wpdm_permission_msg');
        if ($vars['download_link'] == 'loginform' && $type == 'link') return "";
        if ($vars['download_link'] == 'loginform' && $type == 'page') return $hide_all_message;

        //wp_reset_query();
        wp_reset_postdata();


        return @str_replace($keys, $values, @stripcslashes($template));
    }

    /**
     * @usage Find attached files types with a package
     * @param $ID
     * @param bool|true $img
     * @return array|string
     */
    public static function fileTypes($ID, $img = true){
        $files = maybe_unserialize(get_post_meta($ID, '__wpdm_files', true));
        $ext = array();
        if (is_array($files)) {
            foreach ($files as $f) {
                $_f = $f;
                $f = trim($f);
                $f = explode(".", $f);
                $_ext = end($f);
                if($_ext == '') $_ext = 'unknown';
                if($_ext == 'unknown' && strstr($_f, "://")) $_ext = 'web';
                $ext[] = $_ext;
            }
        }
        $ext = array_unique($ext);
        $exico = '';
        foreach($ext as $exi){
            if(file_exists(WPDM_BASE_DIR.'/assets/file-type-icons/'.$exi.'.svg'))
                $exico .= "<img alt='{$exi}' title='{$exi}' class='ttip' style='width:16px;height:16px;' src='".plugins_url('download-manager/assets/file-type-icons/'.$exi.'.svg')."' /> ";
        }
        if($img) return $exico;
        return $ext;
    }

    /**
     * Returns package icon
     * @param $ID
     * @return string
     */
    static function icon($ID){
        $icon = get_post_meta($ID, '__wpdm_icon', true);
        if($icon != '') return $icon;
        $file_types = \WPDM\Package::fileTypes($ID, false);
        if(count($file_types)){
            if(count($file_types) == 1) {
                $tmpavar = $file_types;
                $ext = $tmpvar = array_shift($tmpavar);
            } else
                $ext = 'zip';
        }  else
            $ext = "unknown";
        if($ext === '') $ext = 'wpdm';
        $icon = plugins_url("download-manager/assets/file-type-icons/{$ext}.svg");
        return apply_filters("wpdm_package_icon", $icon, $ID);
    }


    /**
     * @param $package
     * @return string
     * @usage Generate Google Doc Preview
     */
    public static function docPreview($package){


        $files = $package['files'];

        if(!is_array($files)) return "";
        $ind = -1;
        $fext = '';
        foreach($files as $i =>$sfile){
            $ifile = $sfile;
            $sfile = explode(".", $sfile);
            $fext = end($sfile);
            if(in_array(end($sfile),array('pdf','doc','docx','xls','xlsx','ppt','pptx'))) { $ind = $i; break; }
        }

        if($ind==-1) return "";
        $url = wpdm_download_url($package, 'ind='.$ind);
        if(strpos($ifile, "://")) $url = $ifile;
        $doc_preview_html = \WPDM\libs\FileSystem::docPreview($url, $fext);
        $doc_preview_html = apply_filters('wpdm_doc_preview', $doc_preview_html, $package, $url, $fext);
        return $doc_preview_html;
    }


    /**
     * @usage Create New Package
     * @param $data
     * @return mixed
     */
    public static function Create($package_data){

        if(isset($package_data['post_type']))
            unset($package_data['post_type']);

        $package_data_core = array(
            'post_title'           => '',
            'post_content'           => '',
            'post_excerpt'          => '',
            'post_status'           => 'publish',
            'post_type'             => 'wpdmpro',
            'post_author'           => get_current_user_id(),
            'ping_status'           => get_option('default_ping_status'),
            'post_parent'           => 0,
            'menu_order'            => 0,
            'to_ping'               =>  '',
            'pinged'                => '',
            'post_password'         => '',
            'guid'                  => '',
            'post_content_filtered' => '',
            'import_id'             => 0
        );

        $package_data_meta = array(
            'files'           => array(),
            'fileinfo'           => array(),
            //'password'           => '',
            'package_dir'           => '',
            'link_label'          => __('Download','download-manager'),
            'download_count'           => 0,
            'view_count'             => 0,
            'version'           => '1.0.0',
            'stock'           => 0,
            'package_size'           => 0,
            'package_size_b'           => 0,
            'access'            => 0,
            'individual_file_download'               =>  -1,
            'cache_zip'               =>  -1,
            'template'                => 'link-template-calltoaction3.php',
            'page_template'         => 'page-template-1col-flat.php',
            'password_lock'                  => '0',
            'facebook_lock'                  => '0',
            'gplusone_lock'                  => '0',
            'linkedin_lock'                  => '0',
            'tweet_lock'                  => '0',
            'email_lock'                  => '0',
            'icon' => '',
            'import_id'             => 0
        );

        foreach($package_data_core as $key => &$value){
            $value = isset($package_data[$key])?$package_data[$key]:$package_data_core[$key];
        }

        if(!isset($package_data['ID']))
            $post_id = wp_insert_post($package_data_core);
        else {
            $post_id = $package_data['ID'];
            $package_data_core['ID'] = $post_id;
            wp_update_post($package_data_core);
        }

        foreach($package_data_meta as $key => $value){
            $value = isset($package_data[$key])?$package_data[$key]:$package_data_meta[$key];
            update_post_meta($post_id, '__wpdm_'.$key, $value);
        }

        if(isset($package_data['cats']))
            wp_set_post_terms( $post_id, $package_data['cats'], 'wpdmcategory' );

        return $post_id;
    }




}
