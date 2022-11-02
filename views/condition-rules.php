<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
global $countries, $timezones, $rules; ?>
<div class="condition-rules-container">
    <div class="rule-group first-rule-group">
        <strong>IF</strong>
        <select name="rules[0][name]" class="select-rule-type first-select-rule" data-row="0">
            <option value="">Start your rule...</option>
            <?php foreach ($rules as $key => $rule): ?>
                <option value="<?php echo $key; ?>"><?php echo $rule['label']; ?></option>
            <?php endforeach; ?>
        </select>

        <?php if (!get_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML')) :?>
            <div class="pro-version-message hidden">
                Available in <a href="https://personalizationwp.com/upgrade-pro-unlock-features/" target="_blank">pro version</a>
            </div>
        <?php endif;?>

        <div class="rule-holder"></div>
    </div>

    <div class="and-or-rule-group-prototype rule-group">
        <input type="hidden" name="rules[1][clause]" class="clause-input" value="and"/>

        <a class="button button-primary rule-btn-and">AND</a>
        <a class="button rule-btn-or">OR</a>

        <select name="rules[1][name]" class="select-rule-type" data-row="1">
            <?php foreach ($rules as $key => $rule): ?>
                <option value="<?php echo $key; ?>">
                    <?php echo $rule['label']; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="rule-holder"></div>

        <?php if (!get_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML')) :?>
            <div class="pro-version-message hidden">
                Available in <a href="https://personalizationwp.com/upgrade-pro-unlock-features/" target="_blank">pro version</a>
            </div>
        <?php endif;?>

        <a class="remove-button"><span class="dashicons dashicons-no-alt"></span></a>
    </div>
</div>

<div id="rules-container">
    <div class="rule-content rule-<?php echo USER_IS_FROM; ?>">
        <select class="rule-value1-options" data-name="value1">
            <?php foreach ($countries as $country): ?>
                <option value="<?php echo $country; ?>"><?php echo $country; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="rule-content rule-<?php echo IS_DAY_OF_WEEK; ?>">
        <select class="rule-value1-options" data-name="value1">
            <option value="monday">Monday</option>
            <option value="tuesday">Tuesday</option>
            <option value="wednesday">Wednesday</option>
            <option value="thursday">Thursday</option>
            <option value="friday">Friday</option>
            <option value="saturday">Saturday</option>
            <option value="sunday">Sunday</option>
        </select>
    </div>

    <?php
    if (get_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML')) {
        echo get_option(CONDITIONS_PLUGIN_NAME . '-premiumRulesHTML');
    }
    ?>
</div>

<div class="clear"></div>
<a class="button btn-add-rule" style="display: none;">Add rule</a>


<script>
  var $ = jQuery;
  var currentRow = 1;
  var firstSelectRuleActive = <?php echo isset($_POST['id']) ? 'false' : 'true';?>;

  $('body')
    .on('change', '.learn-conditions-box select', function () {
      var message = $(this).parent().find('.pro-version-message');
      message.hide();

      let text = $(this).find("option:selected").text();
      let isValuePro = text.match(/(pro)/g);
      if (isValuePro !== null) {
        message.css('display', 'inline-block');
      }
    })
    .on('change', '.first-select-rule', function () {
      $(this).parent().find('.error-message').hide();
      if (firstSelectRuleActive) {
        $('.btn-add-rule').show();
        $(this).find('option').first().remove();
        firstSelectRuleActive = false;
      }
    })
    .on('keyup', '#title', function () {
      if ($(this).val() !== '') {
        $(this).parent().find('.error-message').hide();
      } else {
        $(this).parent().find('.error-message').show();
      }
    })
    .on('change', '.select-rule-type', function () {
      var type = $(this).val();
      var ruleContent = $('.rule-' + type).clone();
      var row = $(this).data('row');

      ruleContent.find('input,select,textarea').each(function () {
        var name = $(this).data('name');

        $(this).attr('name', 'rules[' + row + '][' + name + ']');
      });

      $(this).parent().find('.rule-holder').html('');
      $(this).parent().find('.rule-holder').html(ruleContent.html());
      $(this).parent().find('.rule-holder .rule-' + type).css('display', 'inline-block');
      $(this).parent().find('.rule-holder').find(".datepicker").datepicker({
        dateFormat: 'dd.mm.yy'
      });
      $('.btn-add-rule').show();
    })
    .on('click', '.rule-btn-and', function () {
      $(this).parent().find('.clause-input').val('and');
      $(this).parent().find('.rule-btn-or').removeClass('button-primary');
      $(this).addClass('button-primary');
    })
    .on('click', '.rule-btn-or', function () {
      $(this).parent().find('.clause-input').val('or');
      $(this).parent().find('.rule-btn-and').removeClass('button-primary');
      $(this).addClass('button-primary');
    })
    .on('change', '.<?php echo USER_VISITS_PAGE; ?>-options', function () {
      let value = $(this).val();
      if (value != 'every-time') {
        $(this).parent().find('.rule-value3-options').css('display', 'inline-block');
      } else {
        $(this).parent().find('.rule-value3-options').hide();
      }
    })
    .on('click', '.remove-button', function () {
      // if(window.confirm('Are you sure you want to delete the row ?')){
      $(this).parent().remove();
      // }
    })
    .on('keyup', '.user-visits-page-times-input', function () {
      let value = parseInt($(this).val());
      let text = 'time';
      if (value > 1) {
        text = 'times';
      }

      if (value < 0) {
        $(this).val('0');
      }

      $(this).parent().find('.visits-page-times-holder').text(text);
    });

  var andOrRuleGroupPrototype = $('.and-or-rule-group-prototype');
  andOrRuleGroupPrototype.remove();

  $('.btn-add-rule').on('click', function () {
    currentRow++;

    var clonedAndOrRuleGroupPrototype = andOrRuleGroupPrototype.clone();
    clonedAndOrRuleGroupPrototype.css('display', 'block');
    clonedAndOrRuleGroupPrototype.find('.clause-input').attr('name', 'rules[' + currentRow + '][clause]');
    clonedAndOrRuleGroupPrototype.find('.select-rule-type').attr('name', 'rules[' + currentRow + '][name]');
    clonedAndOrRuleGroupPrototype.find('.select-rule-type').data('row', currentRow);

    $('.condition-rules-container').append(clonedAndOrRuleGroupPrototype);
  });

  <?php
  if(isset($post)) :
      if (isset($postMeta['rules'][0]) && !empty($postMeta['rules'][0])) :
          $postMeta['rules'][0] = str_replace(
              "Korea, Democratic People\'s Republic of",
              "Korea, Democratic People Republic of",
              $postMeta['rules'][0]
          );
          $rules = json_decode($postMeta['rules'][0]);
          $ruleCount = 0;
          foreach($rules as $key => $rule) :?>
              $(document).ready(function () {
                var firstRuleGroup = $('.first-rule-group');
                <?php if($ruleCount == 0):?>
                    firstRuleGroup.find('.select-rule-type').val('<?php echo $rule->name;?>').change();
                    <?php if(isset($rule->value1)):?>
                        firstRuleGroup.find('[data-name="value1"]').val('<?php echo $rule->value1;?>').change();
                    <?php endif;?>

                    <?php if(isset($rule->value2)):?>
                        firstRuleGroup.find('[data-name="value2"]').val('<?php echo $rule->value2;?>').change();
                    <?php endif;?>

                    <?php if(isset($rule->value3)):?>
                        firstRuleGroup.find('[data-name="value3"]').val('<?php echo $rule->value3;?>').change();
                    <?php endif;?>
                <?php else:?>
                    currentRow++;

                    var clonedAndOrRuleGroupPrototype = andOrRuleGroupPrototype.clone();
                    clonedAndOrRuleGroupPrototype.css('display', 'block');
                    clonedAndOrRuleGroupPrototype.find('.clause-input').attr('name', 'rules[' + currentRow + '][clause]');
                    clonedAndOrRuleGroupPrototype.find('.select-rule-type').attr('name', 'rules[' + currentRow + '][name]');
                    clonedAndOrRuleGroupPrototype.find('.select-rule-type').data('row', currentRow);

                    $('.condition-rules-container').append(clonedAndOrRuleGroupPrototype);
                    clonedAndOrRuleGroupPrototype.find('.select-rule-type').val('<?php echo $rule->name;?>').change();
                      <?php if(isset($rule->clause)):?>
                        clonedAndOrRuleGroupPrototype.find('.clause-input').val('<?php echo $rule->clause;?>');

                        <?php if($rule->clause == 'or'):?>
                        clonedAndOrRuleGroupPrototype.find('.rule-btn-and').removeClass('button-primary');
                        clonedAndOrRuleGroupPrototype.find('.rule-btn-or').addClass('button-primary');
                        <?php endif;?>
                      <?php endif;?>

                      <?php if(isset($rule->value1)):?>
                        clonedAndOrRuleGroupPrototype.find('[data-name="value1"]').val('<?php echo $rule->value1;?>').change();
                      <?php endif;?>

                      <?php if(isset($rule->value2)):?>
                        clonedAndOrRuleGroupPrototype.find('[data-name="value2"]').val('<?php echo $rule->value2;?>').change();
                      <?php endif;?>

                      <?php if(isset($rule->value3)):?>
                        clonedAndOrRuleGroupPrototype.find('[data-name="value3"]').val('<?php echo $rule->value3;?>').change();
                      <?php endif;?>
                <?php endif;?>
              });
              <?php
              $ruleCount++;
          endforeach;
      endif;
  endif;
  ?>
</script>
