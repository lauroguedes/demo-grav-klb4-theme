<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\File;


/**
 * Class ThumbRatingsPlugin
 * @package Grav\Plugin
 */
class ThumbRatingsPlugin extends Plugin
{
    protected $callback;
    protected $thumbs_data_path;
    protected $ips_data_path;
    protected $vote_data;
    protected $thumbs_cache_id;
    protected $ips_cache_id;

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents() {
        return [
            'onPluginsInitialized'  => ['onPluginsInitialized', 0],
        ];
    }


    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized() {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Initialize core setup
        $this->initSetup();

        // Enable the main event we are interested in
        $this->enable([
            'onPageContentRaw'      => ['onPageContentRaw', 0],
            'onPageInitialized'     => ['onPageInitialized', 0],
            'onTwigInitialized'     => ['onTwigInitialized', 0],
            'onTwigSiteVariables'   => ['onTwigSiteVariables', 0],
        ]);
    }


    private function initSetup() {
        $cache = $this->grav['cache'];

        // set some default configuration options
        $data_path = $this->grav['locator']->findResource('user://data', true) . '/thumb-ratings/';
        $this->thumbs_data_path = $data_path . 'thumbs.json';
        $this->ips_data_path = $data_path . 'ips.json';
        $this->thumbs_cache_id = md5('thumbs-vote-data'.$cache->getKey());
        $this->ips_cache_id = md5('thumbs-ip-data'.$cache->getKey());

        // initialize data
        $this->getVoteData();
    }


    private function initSettings($page) {
        // if not in admin merge potential page-level configs
        if (!$this->isAdmin() && isset($page->header()->{'thumb-ratings'})) {
            $this->config->set('plugins.thumb-ratings', $this->mergeConfig($page));
        }
        $this->callback = $this->config->get('plugins.thumb-ratings.callback');
    }


    public function onPageContentRaw(Event $e) {
        // initialize with page settings (needed when twig used in page content / pre-cache)
        $this->initSettings($e['page']);
    }


    public function onPageInitialized() {
        // initialize with page settings (post-cache)
        $this->initSettings($this->grav['page']);

        // Process vote if required
        if ($this->callback === $this->grav['uri']->path()) {

            // try to add the vote
            $result = $this->addVote();

            echo json_encode(['status' => $result[0], 'message' => $result[1]]);
            exit();
        }
    }


    public function addVote() {
        $nonce = $this->grav['uri']->param('nonce');
        if (!Utils::verifyNonce($nonce, 'thumb-ratings')) {
            return [false, 'Invalid security nonce'];
        }

        // get and filter the data
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING);
        $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);

        // check for duplicate vote if configured
        if ($this->config->get('plugins.thumb-ratings.unique_ip_check')) {
            if (!$this->validateIp($id)) {
                return [false, 'This IP has already voted'];
            }
        }

        $vote_data = $this->getVoteData();

        if (array_key_exists($id, $vote_data)) {
            $rating = $vote_data[$id];
            $rating[$type]++;
        } else {
            $rating = [0,0];
            $rating[$type]++;
        }

        $this->saveVoteData($id, $rating);

        return [true, 'Your vote has been added!'];
    }


    /**
     * Add simple `thumbs()` Twig function
     */
    public function onTwigInitialized() {
        $this->grav['twig']->twig()->addFunction(
            new \Twig_SimpleFunction('thumbs', [$this, 'generateThumbs'])
        );
    }


    /**
     * Add CSS and JS to page header
     */
    public function onTwigSiteVariables() {
        if ($this->config->get('plugins.thumb-ratings.built_in_css')) {
            $this->grav['assets']
                ->addCss('plugin://thumb-ratings/assets/thumb-ratings.css');
        }
        $this->grav['assets']
            ->add('jquery', 101)
            ->addJs('plugin://thumb-ratings/assets/jquery.thumb-ratings.js')
            ->addJs('plugin://thumb-ratings/assets/thumb-ratings.js')
            ->addJs('plugin://thumb-ratings/assets/jquery.cookie.js');
    }


    public function generateThumbs($id=null, $options = []) {
        if ($id === null) {
            return '<i>ERROR: no id provided to <code>thumbs()</code> twig function</i>';
        }

        $thumbs = $this->getThumbs($id);
        $count_up='';
        $count_down='';

        $id = str_replace("/blog/","",$id);

        $data = [
            'id' => $id,
            'uri' => Uri::addNonce($this->grav['base_url'] . $this->config->get('plugins.thumb-ratings.callback') . '.json','thumb-ratings'),
            'options' => [ 'readOnly' => $this->config->get('plugins.thumb-ratings.readonly'), 'disableAfterRate' => $this->config->get('plugins.thumb-ratings.disable_after_vote') ]
        ];
        $data = htmlspecialchars(json_encode($data, ENT_QUOTES));

        $count_up = '<span id="thumb0-count-'.$id.'">'.$thumbs[0].'</span>';
        $count_down = '<span id="thumb1-count-'.$id.'">'.$thumbs[1].'</span>';

        if ( $this->config->get('plugins.thumb-ratings.up_bgcolor') ) {
            $up_bgcolor = $this->config->get('plugins.thumb-ratings.up_bgcolor');
        }
        if ( $this->config->get('plugins.thumb-ratings.down_bgcolor') ) {
            $down_bgcolor = $this->config->get('plugins.thumb-ratings.down_bgcolor');
        }
        if ( $this->config->get('plugins.thumb-ratings.up_color') ) {
            $up_color = $this->config->get('plugins.thumb-ratings.up_color');
        }
        if ( $this->config->get('plugins.thumb-ratings.down_bgcolor') ) {
            $down_color = $this->config->get('plugins.thumb-ratings.down_color');
        }

        $existsIp='';
        if ($this->existsIp($id)) {
            $existsIp = ' true';
        }
        
        $html = '<div class="thumb-rating-container" data-thumb-rating="'.$data.'">';

        $html .= '<div id="t0-'.$id.'" class="thumb'.$existsIp.'" style="color:'.$up_color.';background-color:'.$up_bgcolor.';">';
        $html .= '<i class="fa fa-thumbs-o-up" aria-hidden="true"></i>&nbsp;';
        $html .= $count_up;
        $html .= '</div>';

        $html .= '<div id="t1-'.$id.'" class="thumb'.$existsIp.'" style="color:'.$down_color.';background-color:'.$down_bgcolor.';">';
        $html .= '<i class="fa fa-thumbs-o-down" aria-hidden="true"></i>&nbsp;';
        $html .= $count_down;
        $html .= '</div>';
        
        $html .= '</div>';

        $html .= '<input type="hidden" id="input-'.$id.'" value="">';

        return $html;
    }


    private function getVoteData() {
        if (is_null($this->vote_data)) {
            $cache = $this->grav['cache'];
            $vote_data = $cache->fetch($this->thumbs_cache_id);

            if ($vote_data === false) {
                $fileInstance = File::instance($this->thumbs_data_path);

                // load file contents and decode JSON
                if (!$fileInstance->content()) {
                    $vote_data = [];
                } else {
                    $vote_data = json_decode($fileInstance->content(), true);
                }
                // store data in cache
                $cache->save($this->thumbs_cache_id, $vote_data);
            }
            // set vote data on object
            $this->vote_data = $vote_data;

            // set to empty data if nothing found
            if (is_null($this->vote_data)) {
                $this->vote_data = [];
            }
        }
        return $this->vote_data;
    }


    private function saveVoteData($id = null, $data = null) {
        if ($id != null && $data !=null) {
            $this->vote_data[$id] = $data;
        }

        // update data in cache
        $this->grav['cache']->save($this->thumbs_cache_id, $this->vote_data);

        // save in file
        $fileInstance = File::instance($this->thumbs_data_path);
        $data = json_encode((array)$this->vote_data);
        $fileInstance->content($data);
        $fileInstance->save();
    }


    private function getThumbs($id) {
        $vote_data = $this->getVoteData();
        if (array_key_exists($id, $vote_data)) {
            $votes = $vote_data[$id];
            $thumb_up = $votes[0];
            $thumb_down = $votes[1];
            return [$thumb_up, $thumb_down];
        } else {
            return [0, 0];
        }
    }


    private function validateIp($id) {
        $user_ip = $this->grav['uri']->ip();
        $fileInstance = File::instance($this->ips_data_path);

        if (!$fileInstance->content()) {
            $ip_data = [];
        } else {
            $ip_data = json_decode($fileInstance->content(), true);
        }

        if (array_key_exists($user_ip, $ip_data)) {
            $user_ip_data = $ip_data[$user_ip];
            if (in_array($id, $user_ip_data)) {
                return false;
            }  else {
                array_push($user_ip_data, $id);
            }
        } else {
            $user_ip_data = [$id];
        }

        $ip_data[$user_ip] = $user_ip_data;
        $data = json_encode((array)$ip_data);
        $fileInstance->content($data);
        $fileInstance->save();
        return true;
    }


    private function existsIp($id) {
        $user_ip = $this->grav['uri']->ip();
        $fileInstance = File::instance($this->ips_data_path);

        if (!$fileInstance->content()) {
            $ip_data = [];
        } else {
            $ip_data = json_decode($fileInstance->content(), true);
        }

        if (array_key_exists($user_ip, $ip_data)) {
            $user_ip_data = $ip_data[$user_ip];
            if (in_array($id, $user_ip_data)) {
                return true;
            }
        }
        return false;
    }

}
