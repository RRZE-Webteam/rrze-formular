<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row">
        <?php echo $option->getLabel(); ?>
    </th>
    <td>
        <fieldset>
        <legend class="screen-reader-text"><span><?php echo $option->getLabel(); ?></span></legend>
            <?php foreach ($option->getArg('options', []) as $key => $label) : ?>
                <label for="<?php echo esc_attr($option->getIdAttribute() . '_' . $key); ?>">
                    <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo esc_attr($option->getIdAttribute() . '_' . $key); ?>" type="checkbox" value="<?php echo esc_attr((string) $key); ?>" <?php echo in_array($key, $option->getValueAttribute() ?? [], true) ? 'checked' : null; ?> <?php echo $option->getInputClassAttribute(); ?>> <?php echo esc_html((string) $label); ?>
                </label><br>
            <?php endforeach ?>
            <?php if ($description = $option->getArg('description')) : ?>
                <p class="description"><?php echo esc_html((string) $description); ?></p>
                <?php if ($error = $option->hasError()) : ?>
                    <div class="rrze-formular-settings-error"><?php echo esc_html((string) $error); ?></div>
                <?php endif ?>
            <?php endif ?>
        </fieldset>
    </td>
</tr>
