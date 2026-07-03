<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row" class="rrze-wp-form-label">
        <label for="<?php echo esc_attr($option->getIdAttribute()); ?>" <?php echo $option->getLabelClassAttribute(); ?>><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="rrze-wp-form rrze-wp-form-input">
        <?php
        $synonym = $option->getsynonymAttribute();
        echo $synonym !== null ? wp_kses_post((string) $synonym) : '';
        ?>
    </td>
</tr>
