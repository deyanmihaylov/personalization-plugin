<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
/*
    Plugin Name: Personalization plugin by Flowcraft
    Plugin URI: https://personalizationwp.com/
    description: The Personalization plugin for WordPress by Flowcraft UX Design Studio allows you to segment and target different user audiences, based on geo location, pages visited, device, date and time, day of the week and more and show different content to each group. The plugin is very easy to use and does not require any coding skills. Simply choose what content to display when a visitor meets a certain condition and what to show when the condition is not met. You also have the option not to show anything (leave your website unchanged) when the condition is not met.
    Version: 1.1.3
    Author: Flowcraft UX Design Studio
    Author URI: http://flowcraft.cc/
    License: GPL2
*/

include 'includes/vars.php';
include 'includes/Flowcraft_Mobile_Detect.php';

class Flowcraft_PersonalizationPlugin
{
    private $conditionsCount = 0;

    public function __construct()
    {
        $fields = array(
            'visits'        => 0,
            'user-location' => 0,
            'user-leaves'   => 0
        );

        foreach ($fields as $key => $value) {
            if (!Flowcraft_PersCookie::has($key)) {
                Flowcraft_PersCookie::set($key, $value);
            }
        }

        add_action('wp', array($this, 'initActions'));
        add_action('after_setup_theme', array($this, 'afterSetupTheme'));
        add_action('admin_menu', array($this, 'adminMenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
        add_shortcode('personalization-condition', array($this, 'shortcodeAction'));
        add_action('admin_post_add_condition_form_response', array($this, 'addPageFormResponse'));
        add_filter('manage_posts_columns', array($this, 'adminColumnsHead'));
        add_action('manage_posts_custom_column', array($this, 'adminColumnsContent'), 10, 2);
        add_action('admin_head-edit.php', array($this, 'addConditionButton'));
        add_action('admin_footer', array($this, 'adminFooterCondition'));
        add_action('wp_ajax_condition_verify_key', array($this, 'verifyKeyAjax'));
        add_action('wp_ajax_nopriv_condition_verify_key', array($this, 'verifyKeyAjax'));
        add_filter('post_row_actions', array($this, 'removeRowActionsFromAdmin'), 10, 1);
        add_action('wp_ajax_condition_user_leaves', array($this, 'userLeavesAjax'));
        add_action('wp_ajax_nopriv_condition_user_leaves', array($this, 'userLeavesAjax'));
        add_action( 'template_redirect', array($this, 'redirectConditions') );
    }

    /**
     * After setup theme hooks
     */
    function afterSetupTheme()
    {
        $this->registerPostType();
    }

    /**
     * Register admin menu pages
     */
    public function adminMenu()
    {
        add_submenu_page(
            'edit.php?post_type=personalization-cnd',
            'Add Condition',
            'Add Condition',
            'manage_options',
            'add-personalization-condition',
            array($this, 'addPage')
        );
    }

    /**
     * On WP init hooks
     */
    function initActions()
    {
        $currentLink = self::getCurrentLink();

        if (!is_admin()) {
            Flowcraft_PersCookie::set('visits', (int)Flowcraft_PersCookie::get('visits') + 1);

            Flowcraft_PersCookie::incrementPageVisit($currentLink);

            $locationData = Flowcraft_PersCookie::get('user-location');
            if (empty($locationData) || $locationData == '0') {
                $response = $this->getUserInfoByIp();
                if (!empty($response)) {
                    $locationData = @unserialize($response);
                    Flowcraft_PersCookie::set('user-location', $locationData, strtotime("+ 30 minutes", time()));
                }
            }
        }
    }

    /**
     * Redirect conditions
     *
     */
    function redirectConditions() {
        $queried_post_type = get_query_var('post_type');
        if ( is_single() && $queried_post_type == 'personalization-cnd') {
            wp_redirect( self::getCurrentBaseLink(), 301 );
            exit;
        }
    }

    /**
     * Init jQuery datepicker
     */
    function enqueueScripts($page)
    {
        global $post;

        if ($page == 'personalization-cnd_page_add-personalization-condition'
            || (!empty($post) && $post->post_type == 'personalization-cnd')
        ) {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_register_style('jquery-ui', plugins_url('assets/jquery-ui.css', __FILE__));
            wp_enqueue_style('jquery-ui');
        }

        if ($page == 'personalization-cnd_page_add-personalization-condition') {
            wp_enqueue_style('', plugins_url('assets/styles.css', __FILE__));
        }
    }

    /**
     * Output when using shortcode condition
     *
     * @param $atts
     * @return string
     */
    function shortcodeAction($atts)
    {
        $id = 0;
        if (!empty($atts)) {
            $id = (int)current($atts);

            return $this->getConditionOutput($id);
        }

        return 'There is no condition with the ID ' . $id;
    }

    /**
     * Get condition output for post
     *
     * @param $postId
     * @return string
     */
    function getConditionOutput($postId)
    {
        $this->conditionsCount++;

        $output = '';

        $visits = (int)Flowcraft_PersCookie::get('visits');
        $condition = get_post($postId);
        if ($condition->post_status != 'publish') {
            return '<strong>The shortcode is not yet published.</strong>';
        }

        $meta = get_post_meta($postId);
        if (!empty($meta['rules'])) {
            $output = '';

            if ($this->conditionsCount == 1) {
                // Things we use in all conditions
                $output .= "<script>
                                var $ = jQuery;
                                function userLeavesAjax(){
                                    $.ajax({
                                      method: 'POST',
                                      url: '" . $this->getCurrentBaseLink() . "/wp-admin/admin-ajax.php',
                                      data: {
                                        action: 'condition_user_leaves'
                                      },
                                      success: function (response) {
                                      //
                                      },
                                      error: function (xhr) {
                                      //
                                      }
                                    });
                                }
                            </script>";
            }

            $rules = json_decode($meta['rules'][0], TRUE);

            $userScrolls = [];
            $userLeaves = [];
            $friendlyStatement = '(';
            $statement = '(';

            $rules = array_values($rules);
            foreach ($rules as $key => $rule) {
                if (isset($rule['clause'])) {
                    if ($rule['clause'] == 'and') {
                        $statement .= ' &&';
                        $friendlyStatement .= ' AND';
                    } else if ($rule['clause'] == 'or') {
                        $statement .= ') || (';
                        $friendlyStatement .= ') OR (';
                    }
                }

                switch ($rule['name']) {
                    case FIRST_TIME_USER:
                        $friendlyStatement .= ' first time user';

                        if ($visits != 1) {
                            $statement .= " false";
                        } else {
                            $statement .= " true";
                        }
                        break;
                    case USER_IS_FROM:
                        $userLocation = Flowcraft_PersCookie::get('user-location');
                        if (!empty($userLocation) && $rule['value1'] == $userLocation['geoplugin_countryName']) {
                            $statement .= " true";
                        } else {
                            $statement .= " false";
                        }

                        $friendlyStatement .= ' user is from ' . $rule['value1'];

                        break;
                    case IS_DAY_OF_WEEK:
                        $currentDay = strtolower(date('l'));
                        if ($rule['value1'] == $currentDay) {
                            $statement .= " true";
                        } else {
                            $statement .= " false";
                        }

                        $friendlyStatement .= ' is day of the week ' . $rule['value1'];

                        break;
                    case USER_VISITS_PAGE:
                        if ($rule['value2'] == 'every-time') {
                            $statement .= " true";
                        } else if (
                            $rule['value2'] == 'exactly'
                            && Flowcraft_PersCookie::getPageCount($rule['value1']) == $rule['value3']
                        ) {
                            $statement .= " true";
                        } else if (
                            $rule['value2'] == 'more-than'
                            && (int)Flowcraft_PersCookie::getPageCount($rule['value1']) > $rule['value3']
                        ) {
                            $statement .= " true";
                        } else {
                            $statement .= " false";
                        }

                        $friendlyStatement .= ' user visits page ' . $rule['value2'] . ' ' . $rule['value3'] . ' times';

                        break;
                    case IS_DATE:
                        $currentDate = date('d.m.Y');
                        if ($rule['value1'] == $currentDate) {
                            $statement .= " true";
                        } else {
                            $statement .= " false";
                        }

                        $friendlyStatement .= ' is date ' . $currentDate;

                        break;
                    case BETWEEN_HOURS:
                        $userLocation = Flowcraft_PersCookie::get('user-location');
                        $timeZone = $userLocation['geoplugin_timezone'];
                        if ($rule['value3'] != 'users-location') {
                            $timeZone = $rule['value3'];
                        }

                        if ($timeZone != NULL) {
                            $currentTime = new DateTime("now", new DateTimeZone($timeZone));
                            $startTime = new DateTime($rule['value1'], new DateTimeZone($timeZone));
                            $endTime = new DateTime($rule['value2'], new DateTimeZone($timeZone));

                            if ($startTime >= $endTime) {
                                if($currentTime <= $endTime) {
                                    $currentTime = (new DateTime("now", new DateTimeZone($timeZone)))->modify('+1 day');
                                }

                                $endTime = (new DateTime($rule['value2'], new DateTimeZone($timeZone)))->modify('+1 day');
                            }

                            if (($currentTime >= $startTime) && ($currentTime <= $endTime)) {
                                $statement .= " true";
                            } else {
                                $statement .= " false";
                            }

                            $friendlyStatement .= ' between hours ' . $rule['value1'] . ' - ' . $rule['value2'] . ' ' . $rule['value3'];

                        } else {
                            $statement .= " false";
                        }

                        break;
                    case USER_TRIES_TO_LEAVE_SITE:
                        $leftSite = Flowcraft_PersCookie::get('user-leaves');
                        if (empty($leftSite)) {
                            $userLeaves[$key] = TRUE;
                        }

                        $statement .= "false";
                        break;
                    case USER_SCROLLS:
                        $userScrolls[$key] = [
                            'value1' => $rule['value1'],
                            'value2' => $rule['value2']
                        ];
                        $statement .= "false";

                        $friendlyStatement .= ' user scrolls ' . $rule['value1'] . $rule['value2'];

                        break;
                    case DEVICE_IS:
                        $detect = new Flowcraft_Mobile_Detect;
                        if (
                            ($rule['value1'] == 'phone' && $detect->isMobile())
                            || ($rule['value1'] == 'tablet' && $detect->isTablet())
                            || ($rule['value1'] == 'desktop' && !$detect->isMobile() && !$detect->isTablet())
                        ) {
                            $statement .= "true";
                        } else {
                            $statement .= "false";
                        }

                        $friendlyStatement .= ' device is ' . $rule['value1'];

                        break;
                }
            }

            $statement .= ')';
            $friendlyStatement .= ')';

            $conditionIsMet = eval("return $statement;");

            $currentLink = self::getCurrentLink();

            if (!empty($userScrolls)) {
                $output .= '<div style="display: none;" class="personalization-cnd-' . $postId . '-content personalization-cnd-met-html-content">
                            ' . apply_filters('the_content', $meta['condition_met_html'][0]) .
                    '</div>';

                $output .= '<div class="personalization-cnd-' . $postId . '-content personalization-cnd-not-met-html-content">
                            ' . apply_filters('the_content', $meta['condition_not_met_html'][0]) .
                    '</div>';
            } else if (!empty($userLeaves)) {
                $output .= '<div style="display: none;" class="personalization-cnd-' . $postId . '-content personalization-cnd-met-html-content">
                            ' . apply_filters('the_content', $meta['condition_met_html'][0]) .
                    '</div>';

                $output .= '<div class="personalization-cnd-' . $postId . '-content personalization-cnd-not-met-html-content">
                            ' . apply_filters('the_content', $meta['condition_not_met_html'][0]) .
                    '</div>';
            }

            $output .= "<script>
                        (function () {
                            let userScrollRules = [];
                            let statement = '$statement';
                            let statementValues = statement.match(/(true|false)/g);
                            let userLeaveRules = [];
                            let newStatement = '';

                            function showContentIfStatement(statement, showGA){
                                if(eval(statement)) {
                                    $('.personalization-cnd-$postId-content.personalization-cnd-met-html-content').show();
                                    $('.personalization-cnd-$postId-content.personalization-cnd-not-met-html-content').hide();

                                    setTimeout(function(){
                                      if (showGA && 'ga' in window) {
                                          var tracker = ga.getAll()[0];
                                          if (tracker) {
                                              tracker.send('event', '$condition->post_title', '$friendlyStatement', '$currentLink');
                                          }
                                      }
                                    });
                                } else {
                                    $('.personalization-cnd-$postId-content.personalization-cnd-met-html-content').hide();
                                    $('.personalization-cnd-$postId-content.personalization-cnd-not-met-html-content').show();
                                }
                            }";

            if (!empty($userLeaves)) {
                foreach ($userLeaves as $key => $userLeave) {
                    $output .= "userLeaveRules[$key] = 'true';";
                }
            }


            if (!empty($userScrolls)) {
                $output .= "var showGA = false;";

                if (isset($meta['activate_ga']) && $meta['activate_ga'][0] == 'yes') {
                    $output .= "showGA = true;";
                }

                foreach ($userScrolls as $key => $userScroll) {
                    $value1 = $userScroll['value1'];
                    $value2 = $userScroll['value2'];
                    $output .= "userScrollRules[$key] = {value1: '$value1', value2: '$value2'};";
                }

                $output .= "var isScrolling;
                        $(document).scroll(function() {
                            window.clearTimeout( isScrolling );

                            isScrolling = setTimeout(function() {
                                let count = 0;
                                newStatement = statement.replace(/(true|false)/g, function(){
                                    return '{state-'+(count++)+'}';
                                  });

                                  for(let i in userScrollRules) {
                                    var rule = userScrollRules[i];
                                    var compareValue = rule.value1;
                                    if(rule.value2 == '%'){
                                      compareValue = (rule.value1 * $(document).height()) / 100;
                                    }

                                    if($(document).scrollTop() > compareValue) {
                                      statementValues[i] = true;
                                    } else {
                                      statementValues[i] = false;
                                    }
                                  }

                                  for(let i in statementValues){
                                    newStatement = newStatement.replace('{state-' + i + '}', statementValues[i]);
                                  }

                                showContentIfStatement(newStatement, showGA);
                            }, 66);
                        });

                        if(userLeaveRules.length !== 0){
                            var userLeft = false;
                            var lastYposition = 0;
                            $('body').mousemove(function(e){
                                if(e.pageY < lastYposition && e.pageY <= 50 && !userLeft) {
                                    userLeft = true;
                                    let count = 0;
                                    newStatement = newStatement.replace(/(true|false)/g, function(val){
                                      let newValue = val;

                                      for(let i in userLeaveRules) {
                                        if(count == i){
                                          newValue = '{state-' + i + '}';
                                        }
                                      }

                                      count++;

                                      return newValue;
                                    });

                                    for(let i in userLeaveRules) {
                                      statementValues[i] = true;
                                    }

                                    for(let i in statementValues){
                                      newStatement = newStatement.replace('{state-' + i + '}', statementValues[i]);
                                    }

                                    userLeavesAjax();
                                    showContentIfStatement(newStatement, showGA);
                                }
                                lastYposition = e.pageY;
                            });
                        }
                        })();
                        </script>";
            } else if (!empty($userLeaves)) {
                $output .= "var showGA = false;";

                if (isset($meta['activate_ga']) && $meta['activate_ga'][0] == 'yes') {
                    $output .= "showGA = true;";
                }

                $output .= "if(userLeaveRules.length !== 0){
                            var userLeft = false;
                            var lastYposition = 0;
                            $('body').mousemove(function(e){
                                if(e.pageY < lastYposition && e.pageY <= 50 && !userLeft) {
                                    userLeft = true;
                                    let count = 0;
                                    newStatement = statement.replace(/(true|false)/g, function(val){
                                      let newValue = val;

                                      for(let i in userLeaveRules) {
                                        if(count == i){
                                          newValue = '{state-' + i + '}';
                                        }
                                      }

                                      count++;

                                      return newValue;
                                    });

                                    for(let i in userLeaveRules) {
                                      statementValues[i] = true;
                                    }

                                    for(let i in statementValues){
                                      newStatement = newStatement.replace('{state-' + i + '}', statementValues[i]);
                                    }

                                    userLeavesAjax();
                                    showContentIfStatement(newStatement, showGA);
                                }

                                lastYposition = e.pageY;
                            });
                        }
                        })();
                        </script>";
            } else {
                $output .= '})();
                        </script>';
                $output .= '<div class="personalization-cnd-' . $postId . '-content">';
                if ($conditionIsMet) {
                    $output .= apply_filters('the_content', $meta['condition_met_html'][0]);

                    if (isset($meta['activate_ga']) && $meta['activate_ga'][0] == 'yes') {
                        $output .= '<script>
                                    setTimeout(function(){
                                      if (\'ga\' in window) {
                                          var tracker = ga.getAll()[0];
                                          if (tracker) {
                                              tracker.send(\'event\', \'' . $condition->post_title . '\', \'' . $friendlyStatement . '\', \'' . $currentLink . '\');
                                          }
                                      }
                                    }, 1000);
                                </script>';
                    }
                } else {
                    $output .= apply_filters('the_content', $meta['condition_not_met_html'][0]);
                }
                $output .= '</div>';
            }
        }

        return $output;
    }

    /**
     * Register custom post type
     */
    function registerPostType()
    {
        $singleName = 'Condition';
        $pluralName = 'Conditions';
        $postType = 'personalization-cnd';
        $slug = 'personalization-cnd';
        $themeName = '';

        $labels = array(
            'name'               => _x($pluralName, 'post type general name', $themeName),
            'singular_name'      => _x($singleName, 'post type singular name', $themeName),
            'menu_name'          => _x($pluralName, 'admin menu', $themeName),
            'name_admin_bar'     => _x($singleName, 'add new on admin bar', $themeName),
            'add_new'            => _x('Add New', $postType, $themeName),
            'add_new_item'       => __('Add New ' . $singleName, $themeName),
            'new_item'           => __('New ' . $singleName, $themeName),
            'edit_item'          => __('Edit ' . $singleName, $themeName),
            'view_item'          => __('View ' . $singleName, $themeName),
            'all_items'          => __('All ' . $pluralName, $themeName),
            'search_items'       => __('Search ' . $pluralName, $themeName),
            'parent_item_colon'  => __('Parent ' . $pluralName . ':', $themeName),
            'not_found'          => __('No ' . strtolower($pluralName) . ' found.', $themeName),
            'not_found_in_trash' => __('No ' . strtolower($pluralName) . ' found in Trash.', $themeName),
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __('Description.', $themeName),
            'public'             => TRUE,
            'publicly_queryable' => TRUE,
            'show_ui'            => TRUE,
            'show_in_menu'       => TRUE,
            'query_var'          => TRUE,
            'rewrite'            => array('slug' => $slug),
            'capability_type'    => 'post',
            'has_archive'        => FALSE,
            'hierarchical'       => FALSE,
            'menu_position'      => NULL,
            'menu_icon'          => plugin_dir_url(__FILE__) . 'assets/icon-dashboard.svg', // icon
            'supports'           => array(
                'title',
            ),
            'capabilities'       => array(
                'create_posts' => 'do_not_allow'
            ),
            'map_meta_cap'       => TRUE,
        );

        register_post_type($postType, $args);
    }

    /**
     * Add condition page
     */
    function addPage()
    {
        include_once 'views/add-condition-page.php';
    }

    /**
     * Hooks on add / edit form submit
     */
    function addPageFormResponse()
    {
        $data = array(
            'post_status'  => isset($_POST['publish']) ? 'publish' : 'draft',
            'post_title'   => sanitize_text_field($_POST['title']),
            'post_content' => sanitize_text_field($_POST['description']),
            'post_type'    => 'personalization-cnd',
            'meta_input'   => array(
                'condition_met'          => sanitize_text_field($_POST['condition_met']),
                // CAN NOT ESCAPE THE HTML OUTPUT, BECAUSE THE HTML IS RENDERED INSIDE THE EDITOR AS HTML
                'condition_met_html'     => sanitize_text_field($_POST['condition_met']) == 'html' ? $_POST['condition_met_html'] : '',
                'condition_not_met'      => sanitize_text_field($_POST['condition_not_met']),
                // CAN NOT ESCAPE THE HTML OUTPUT, BECAUSE THE HTML IS RENDERED INSIDE THE EDITOR AS HTML
                'condition_not_met_html' => sanitize_text_field($_POST['condition_not_met']) == 'html' ? $_POST['condition_not_met_html'] : '',
                'activate_ga'            => isset($_POST['activate_ga']) ? 'yes' : 'no',
                'rules'                  => sanitize_text_field(json_encode($_POST['rules']))
            )
        );

        if (isset($_POST['id'])) {
            $data['ID'] = (int)$_POST['id'];
            $post = wp_update_post($data);
            $message = 'You have edited the condition.';
            $redirectLink = sanitize_text_field($_POST['_wp_http_referer']);
        } else {
            $postId = wp_insert_post($data);
            $message = 'You have added the condition.';
            $redirectLink = admin_url('edit.php?post_type=personalization-cnd&page=add-personalization-condition&id=' . $postId);
        }

        $_COOKIE['condition-add-success'] = $message;
        wp_redirect($redirectLink);
    }


    /**
     * Modify the conditions list columns
     *
     * @param $defaults
     * @return mixed
     */
    function adminColumnsHead($defaults)
    {
        global $post;

        if ($post->post_type != 'personalization-cnd') {
            return $defaults;
        }

        $newDefaults['cb'] = $defaults['cb'];
        $newDefaults['title'] = $defaults['title'];
        $newDefaults['shortcode'] = 'Shortcode';
        $newDefaults['id'] = 'ID';
        $newDefaults['description'] = 'Description';
        $newDefaults['date'] = $defaults['date'];

        return $newDefaults;
    }

    /**
     * Modify the conditions list columns content
     *
     * @param $columnName
     * @param $postId
     * @return string
     */
    function adminColumnsContent($columnName, $postId)
    {
        global $post;

        if ($post->post_type != 'personalization-cnd') {
            return;
        }

        if ($columnName == 'shortcode') {
            echo '<strong>[' . SHORTCODE_SLUG . ' ' . $postId . ']</strong>';
        }

        if ($columnName == 'id') {
            echo $postId;
        }

        if ($columnName == 'description') {
            echo $post->post_content;
        }
    }

    /**
     * Adds "Import" button on module list page
     */
    function addConditionButton()
    {
        global $current_screen;

        if ($current_screen->post_type !== 'personalization-cnd') {
            return;
        }
        ?>
        <script type="text/javascript">
          jQuery(document).ready(function ($) {
            jQuery(jQuery(".wrap h1")[0]).after("<a class='add-new-h2' href='<?php menu_page_url('add-personalization-condition');?>'>Add Condition</a>");
          });
        </script>
        <?php
    }

    /**
     * Output in admin footer for condition pages
     */
    function adminFooterCondition()
    {
        if (get_current_screen()->post_type == 'personalization-cnd') { ?>
            <script type="text/javascript">
              jQuery(document).ready(function ($) {
                jQuery('.row-title').each(function (item) {
                  var id = $(this).parents('tr').first().find('.column-id').text();
                  var newUrl = decodeURI('<?php echo admin_url('edit.php?post_type=personalization-cnd&page=add-personalization-condition');?>&id=' + id);
                  jQuery(this).attr('href', newUrl);

                  jQuery(this).parent().parent().find('.row-actions .edit a').attr('href', newUrl);
                });
              });
            </script>
            <?php
        }
    }

    /**
     * Verify key ajax action
     */
    public static function verifyKeyAjax()
    {
        $response = array(
            'message'          => 'The premium key you entered is incorrect.',
            'premiumRulesHTML' => array(),
            'code'             => 400
        );

        $isKeyValid = self::isKeyValid(sanitize_text_field($_POST['key']));
        if ($isKeyValid['isPremium']) {
            $premiumRulesHTML = get_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML');
            if ($premiumRulesHTML == FALSE && $premiumRulesHTML != '') {
                add_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML', $isKeyValid['premiumRulesHTML']);
            } else {
                update_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML', $isKeyValid['premiumRulesHTML']);
            }

            $key = get_option(CONDITIONS_PLUGIN_NAME . '-key');
            if ($key == FALSE && $key != '') {
                add_option(CONDITIONS_PLUGIN_NAME . '-key', $isKeyValid['key']);
            } else {
                update_option(CONDITIONS_PLUGIN_NAME . '-key', $isKeyValid['key']);
            }

            $response['message'] = 'You have unlocked the premium features.';
            $response['premiumRulesHTML'] = $isKeyValid['premiumRulesHTML'];
            $response['code'] = 200;
        }

        wp_send_json($response, $response['code']);
    }

    /**
     * Is the given key valid ?
     *
     * @param $key
     * @return array
     */
    public static function isKeyValid($key)
    {
        global $CONDITIONS_PLUGIN_API;

        $data = $CONDITIONS_PLUGIN_API['responseData'];

        $response = wp_remote_get($CONDITIONS_PLUGIN_API['validateKey'] . '?key=' . $key, array(
            'sslverify' => FALSE
        ));
        $body = wp_remote_retrieve_body($response);

        if (!empty($body)) {
            $jsonResponse = json_decode($body, TRUE);

            if ($jsonResponse['isPremium']) {
                $data = $jsonResponse;
            }
        }

        return $data;
    }

    /**
     * Verify if key is premium on init
     *
     * @return array
     */
    public static function verifyPremiumKey()
    {
        global $CONDITIONS_PLUGIN_API;

        $data = $CONDITIONS_PLUGIN_API['responseData'];

        if (get_option(CONDITIONS_PLUGIN_NAME . '-key')) {
            $key = get_option(CONDITIONS_PLUGIN_NAME . '-key');

            $isKeyValid = self::isKeyValid($key);
            if ($isKeyValid['isPremium']) {
                $premiumRulesHTML = get_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML');
                if ($premiumRulesHTML == FALSE && $premiumRulesHTML != '') {
                    add_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML', $isKeyValid['premiumRulesHTML']);
                } else {
                    update_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML', $isKeyValid['premiumRulesHTML']);
                }

                $data = $isKeyValid;
            }
        }

        return $data;
    }

    /**
     * Remove row actions from the admin panel
     *
     * @param $actions
     * @return mixed
     */
    public function removeRowActionsFromAdmin($actions)
    {
        if (get_post_type() === 'personalization-cnd') {
            unset($actions['view']);
            unset($actions['inline hide-if-no-js']);
        }

        return $actions;
    }

    /**
     * Get client IP
     *
     * @return string
     */
    public static function getClientIp()
    {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_X_FORWARDED']))
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
            $ip = $_SERVER['HTTP_FORWARDED'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ip = $_SERVER['REMOTE_ADDR'];

        return $ip;
    }

    /**
     * Get current page link
     *
     * @return string
     */
    public static function getCurrentLink()
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    /**
     * Get current base link
     *
     * @return string
     */
    public static function getCurrentBaseLink()
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    }

    function clearRefFromCurl( $handle ) {
        curl_setopt($handle, CURLOPT_REFERER, '');
    }

    /**
     * Get user info by ip from geoplugin
     *
     * @return mixed
     */
    public function getUserInfoByIp()
    {
        add_action( 'http_api_curl', array($this, 'clearRefFromCurl'), 10, 1 );
        $response = wp_remote_get('http://www.geoplugin.net/php.gp?ip=' . self::getClientIp());

        return wp_remote_retrieve_body($response);
    }

    /**
     * Verify key ajax action
     */
    public function userLeavesAjax()
    {
        Flowcraft_PersCookie::set('user-leaves', '1', strtotime("+60 days"));
    }
}

class Flowcraft_PersCookie
{
    /**
     * Set plugin cookie
     *
     * @param     $key
     * @param     $value
     * @param int $expires
     */
    public static function set($key, $value, $expires = 0)
    {
        if (!empty($value) && is_array($value)) {
            $value = @base64_encode(serialize($value));
        }

        setcookie(CONDITIONS_PLUGIN_SESSION_NAME . '_' . $key, $value, $expires, '/');
        $_COOKIE[CONDITIONS_PLUGIN_SESSION_NAME . '_' . $key] = $value;
    }

    /**
     * Check if plugin cookie exist
     *
     * @param $key
     * @return bool
     */
    public static function has($key)
    {
        if (isset($_COOKIE[CONDITIONS_PLUGIN_SESSION_NAME . '_' . $key])) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Get plugin cookie
     *
     * @param $key
     * @return mixed|string
     */
    public static function get($key)
    {
        if (self::has($key)) {
            if (($key == 'user-location' || $key == 'last-checked-valid-key-data')
                && !empty($_COOKIE[CONDITIONS_PLUGIN_SESSION_NAME . '_' . $key])) {
                $value = $_COOKIE[CONDITIONS_PLUGIN_SESSION_NAME . '_' . $key];

                return @unserialize(base64_decode($value));
            }

            return $_COOKIE[CONDITIONS_PLUGIN_SESSION_NAME . '_' . $key];
        }

        return '';
    }

    /**
     * Increment page visit for specific link
     *
     * @param $link
     */
    public static function incrementPageVisit($link)
    {
        $delimiter = '_psnlplg_';
        $cookieName = CONDITIONS_PLUGIN_SESSION_NAME . '_' . uniqid();
        $pageExists = FALSE;
        foreach ($_COOKIE as $key => $value) {
            $explodedCookie = explode($delimiter, $value);
            if (isset($explodedCookie[1]) && $explodedCookie[1] == $link) {
                $pageExists = TRUE;
                $oldCount = $explodedCookie[0];
                $cookieName = $key;
            }
        }

        $value = '1' . $delimiter . $link;
        if ($pageExists) {
            $value = ((int)$oldCount + 1) . $delimiter . $link;
        }

        setcookie($cookieName, $value, 0, '/');
        $_COOKIE[$cookieName] = $value;
    }

    /**
     * Get page link
     *
     * @param $link
     * @return int
     */
    public static function getPageCount($link)
    {
        $delimiter = '_psnlplg_';
        foreach ($_COOKIE as $key => $value) {
            $explodedCookie = explode($delimiter, $value);
            if (isset($explodedCookie[1]) && $explodedCookie[1] == $link) {
                return (int)$explodedCookie[0];
            }
        }

        return 0;
    }
}

$flowcraft_personalizationPlugin = new Flowcraft_PersonalizationPlugin();
