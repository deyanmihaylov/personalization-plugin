<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

global $CONDITIONS_PLUGIN_API, $rules;

if (isset($_GET['key'])) {
    add_option(CONDITIONS_PLUGIN_NAME . '-key', sanitize_text_field($_GET['key']));
}

$verifyPremiumKey = Flowcraft_PersonalizationPlugin::verifyPremiumKey();

$pro = '';
if(!$verifyPremiumKey['isPremium']){
    $pro = ' (pro)';
}

$rules = array(
    FIRST_TIME_USER          => ['label' => 'is first time user'],
    USER_IS_FROM             => ['label' => 'user is from'],
    IS_DAY_OF_WEEK           => ['label' => 'is day of the week'],
    USER_VISITS_PAGE         => ['label' => 'user visits page' . $pro],
    IS_DATE                  => ['label' => 'is date' . $pro],
    BETWEEN_HOURS            => ['label' => 'is between hours' . $pro],
    USER_TRIES_TO_LEAVE_SITE => ['label' => 'user tries to leave the site' . $pro],
    USER_SCROLLS             => ['label' => 'user scrolls' . $pro],
    DEVICE_IS                => ['label' => 'device is' . $pro]
);
?>

<?php if (isset($_COOKIE['condition-add-success'])) { ?>
    <div class="notice notice-success is-dismissible">
        <p><?php echo $_COOKIE['condition-add-success']; ?></p>
    </div>
    <?php unset($_COOKIE['condition-add-success']);
}

if (isset($_COOKIE['condition-add-error'])) { ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo $_COOKIE['condition-add-error']; ?></p>
    </div>
    <?php unset($_COOKIE['condition-add-error']);
}


if (isset($_REQUEST['id'])) {
    $id = (int)$_REQUEST['id'];
    $post = get_post($id);
    $postMeta = get_post_meta($id);

    if (empty($post)) {
        echo '<p>Condition with ID <strong>' . $id . '</strong>  doesn\'t exist. Go back to <a href="' . admin_url('edit.php?post_type=condition') . '">all conditions</a></p>';
        exit;
    }
}
?>
<div class="wrap">
    <div class="metabox-holder columns-1">
        <h1><?php echo isset($post) ? 'Edit condition' : 'Add condition'; ?></h1>

        <form id="add-condition-form" method="post" action="<?php echo esc_html(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="add_condition_form_response">
            <?php if (isset($post)): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>"/>
            <?php endif; ?>

            <div class="column-left">
                <div>
                    <input placeholder="Condition name" type="text" name="title" id="title" autocomplete="off"
                           value="<?php echo isset($post) ? $post->post_title : ''; ?>">
                </div>

                <div class="condition-code-holder">
                    <?php if (isset($post)) : ?>
                        <small>Copy this shortcode and paste it into your post, page or text widget content</small>
                        <p>[<?php echo SHORTCODE_SLUG; ?> <?php echo $post->ID; ?>]</p>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <textarea placeholder="Description (Optional)" rows="3"
                              name="description"><?php echo isset($post) ? $post->post_content : ''; ?></textarea>
                </div>

                <div class="postbox learn-conditions-box">
                    <h2 class="hndle">
                        <span>1. Condition rules</span>
                        <a href="https://personalizationwp.com/how-create-condition-personalization-plugin"
                           target="_blank" class="learn-conditions-link">
                            <span class="dashicons dashicons-info"></span> Learn how to set up conditions
                        </a>
                    </h2>
                    <div class="inside"><?php include_once 'condition-rules.php'; ?></div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><span>2. When condition is met</span></h2>
                    <div class="inside">
                        <div class="condition-choice-container">
                            <div class="options-group">
                                <div class="option-item">
                                    <label class="condition-choice-html">
                                        <input type="radio"
                                               name="condition_met" <?php echo ((isset($postMeta) && $postMeta['condition_met'][0] == 'html') || !isset($post)) ? 'checked' : ''; ?>
                                               value="html"/>
                                        Show content
                                    </label>
                                </div>

                                <div class="option-item">
                                    <label class="condition-choice-none">
                                        <input type="radio"
                                               name="condition_met" <?php echo (isset($postMeta) && $postMeta['condition_met'][0] == 'none') ? 'checked' : ''; ?>
                                               value="none"/>
                                        Don't show anything
                                    </label>
                                </div>
                            </div>

                            <?php wp_editor(isset($postMeta['condition_met_html'][0]) ? $postMeta['condition_met_html'][0] : '',
                                'condition_met_html',
                                array('textarea_rows' => 12)); ?>

                            <?php if (isset($postMeta) && $postMeta['condition_met'][0] == 'none'): ?>
                                <script>
                                  $(window).load(function () {
                                    jQuery('#wp-condition_met_html-wrap').hide();
                                  });
                                </script>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><span>3. When condition is NOT met</span></h2>
                    <div class="inside">
                        <div class="condition-choice-container">
                            <div class="options-group">
                                <div class="option-item">
                                    <label class="condition-choice-html">
                                        <input type="radio"
                                               name="condition_not_met" <?php echo ((isset($postMeta) && $postMeta['condition_not_met'][0] == 'html') || !isset($post)) ? 'checked' : ''; ?>
                                               value="html"/>
                                        Show content
                                    </label>
                                </div>

                                <div class="option-item">
                                    <label class="condition-choice-none">
                                        <input type="radio"
                                               name="condition_not_met" <?php echo (isset($postMeta) && $postMeta['condition_not_met'][0] == 'none') ? 'checked' : ''; ?>
                                               value="none"/>
                                        Don't show anything
                                    </label>
                                </div>
                            </div>

                            <?php wp_editor(isset($postMeta['condition_not_met_html'][0]) ? $postMeta['condition_not_met_html'][0] : '',
                                'condition_not_met_html',
                                array('textarea_rows' => 12)); ?>

                            <?php if (isset($postMeta) && $postMeta['condition_not_met'][0] == 'none'): ?>
                                <script>
                                  $(window).load(function () {
                                    jQuery('#wp-condition_not_met_html-wrap').hide();
                                  });
                                </script>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="ga-box">
                    <input type="checkbox" name="activate_ga"
                           id="activate-ga" <?php echo (isset($postMeta) && $postMeta['activate_ga'][0] == 'yes') ? 'checked' : ''; ?> />
                    <label for="activate-ga">Turn on Google Analytics tracking</label>
                    <p>If you have google analytics connected to your website you can track how your condition is
                        doing.</p>
                </div>

                <div id="activate-premium-box">
                    <h3>Enter your key and activate all condition rules</h3>
                    <div class="premium-key-holder">
                        <?php if ($verifyPremiumKey['isPremium']): ?>
                            <input type="text" name="premium_key" value="<?php echo $verifyPremiumKey['key']; ?>"
                                   id="premium_key" readonly/>
                            <p class="premium-key-message success">
                                The premium features are activated.
                            </p>
                        <?php else : ?>
                            <input type="text" name="premium_key" id="premium_key"/>
                            <a class="button button-primary verify-key-button">
                                Verify key
                            </a>
                        <?php endif; ?>

                    </div>

                    <p class="premium-key-message" style="display: none;"></p>
                    <a class="note" href="<?php echo NO_PREMIUM_KEY_LINK; ?>" target="_blank">
                        Don't have a key ? Get one here.
                    </a>
                </div>
            </div>

            <div class="column-right">
                <div id="side-sortables" class="meta-box-sortables ui-sortable">
                    <div id="submitdiv" class="postbox ">
                        <div class="inside">
                            <div class="submitbox" id="submitpost">

                                <div id="minor-publishing">
                                    <div id="misc-publishing-actions">
                                        <div class="misc-pub-section misc-pub-post-status">
                                            Status:
                                            <span id="post-status-display">
                                                <?php echo isset($post) ? ucfirst($post->post_status) : 'Draft'; ?>
                                            </span>
                                        </div><!-- .misc-pub-section -->
                                    </div>
                                    <div class="clear"></div>
                                    <br/>

                                    <!--                                    --><?php
                                    wp_nonce_field('acme-settings-save', 'acme-custom-message');
                                    //                                    submit_button();
                                    ?>

                                    <button type="submit" class="button button-primary publish-btn" name="publish">
                                        <?php if (isset($post) && $post->post_status == 'publish'): ?>
                                            Update
                                        <?php else: ?>
                                            Publish
                                        <?php endif; ?>
                                    </button>

                                    <button type="submit" class="button draft-btn" name="draft">
                                        Save draft
                                    </button>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="postbox">
                        <h2 class="hndle ui-sortable-handle"><span>Privacy</span></h2>
                        <div class="inside">
                            <div class="helper-metabox-container">
                                This plugin uses cookies to collect some data.
                                Please, make sure you have updated your privacy policy. <a target="_blank"
                                                                                           rel="nofollow"
                                                                                           href="https://personalizationwp.com/ensure-comply-privacy">See
                                    how</a>
                            </div>
                        </div>
                    </div>
                    <div class="postbox ">
                        <h2 class="hndle ui-sortable-handle"><span>Need Help?</span></h2>
                        <div class="inside">
                            <div class="helper-metabox-container">
                                We have plenty of tutorials at <a target="_blank" rel="nofollow"
                                                                  href="https://personalizationwp.com/topic/learning-center/">our
                                    learning center.</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div><!-- .wrap -->

<script>
  var $ = jQuery;
  $('.condition-choice-container label.condition-choice-none').on('click', function () {
    $(this).parent().parent().parent().find('.wp-editor-wrap').hide();
  });

  $('.condition-choice-container label.condition-choice-html').on('click', function () {
    $(this).parent().parent().parent().find('.wp-editor-wrap').show();
  });

  $('.verify-key-button').on('click', function () {
    $.ajax({
      method: 'POST',
      url: '<?php echo admin_url('admin-ajax.php');?>',
      data: {
        action: 'condition_verify_key',
        key: $('#premium_key').val()
      },
      beforeSend: function () {
        $('.premium-key-message').text('').hide().removeClass('error').removeClass('success');
      },
      success: function (response) {
        console.log(response);
        window.location.reload();
      },
      error: function (xhr) {
        let jsonResponse = JSON.parse(xhr.responseText);
        $('.premium-key-message').text(jsonResponse['message']).addClass('error').show();
      }
    });
  });

  $('#add-condition-form').on('submit', function () {
    let hasError = false;
    $('#add-condition-form .error-message').remove();

    let firstSelectRule = $('.first-select-rule');
    let title = $('#title');

    if (firstSelectRule.val() === '') {
      hasError = true;
      firstSelectRule.after('<div class="error-message">Please create at least 1 rule.</div>');
    }

    if (title.val() === '') {
      hasError = true;
      title.after('<div class="error-message">This field is required.</div>');
    }

    if (hasError) {
      return false;
    }
  });
</script>
<?php if (isset($_REQUEST['id'])) : ?>
    <style>
        #adminmenu .wp-submenu li a[href="edit.php?post_type=personalization-cnd&page=add-personalization-condition"] {
            color: rgba(240, 245, 250, .7);
            font-weight: normal;
        }
    </style>
<?php endif ?>
