<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;

?>
<tr valign="top">
    <th scope="row" class="rrze-wp-form-label">
        <label for="<?php echo esc_attr($option->getIdAttribute()); ?>" <?php echo $option->getLabelClassAttribute(); ?>><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="rrze-wp-form rrze-wp-form-input">
        <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo esc_attr($option->getIdAttribute()); ?>" type="password" value="<?php echo esc_attr((string) ($option->getValueAttribute() ?? '')); ?>" <?php echo $option->getInputClassAttribute(); ?>>
        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo esc_html((string) $description); ?></p>
        <?php } ?>
        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-formular-settings-error"><?php echo esc_html((string) $error); ?></div>
        <?php } ?>
    </td>
</tr>
