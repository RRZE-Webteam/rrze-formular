<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;

$tour_attr = $option->getName() === 'frequency' ? ' data-rrze-tour="import-frequency"' : '';
?>
<tr valign="top">
    <th scope="row" class="rrze-wp-form-label">
        <label for="<?php echo esc_attr($option->getIdAttribute()); ?>" <?php echo $option->getLabelClassAttribute(); ?>><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="rrze-wp-form rrze-wp-form-input"<?php echo $tour_attr; ?>>
        <select id="<?php echo esc_attr($option->getIdAttribute()); ?>" name="<?php echo esc_attr($option->getNameAttribute()); ?>" <?php echo $option->getInputClassAttribute(); ?>>
            <?php foreach ($option->getArg('options', []) as $key => $label) { ?>
                <option value="<?php echo esc_attr((string) $key); ?>" <?php selected($option->getValueAttribute(), $key); ?>><?php echo esc_html((string) $label); ?></option>
            <?php } ?>
        </select>
        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo esc_html((string) $description); ?></p>
        <?php } ?>
        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-formular-settings-error"><?php echo esc_html((string) $error); ?></div>
        <?php } ?>
    </td>
</tr>
