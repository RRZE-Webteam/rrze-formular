<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;
?>
<h2 class="nav-tab-wrapper">
    <?php foreach ($settings->tabs as $tab) { ?>
        <a href="<?php echo esc_url($settings->getUrl() . '&tab=' . rawurlencode((string) $tab->slug)); ?>" class="nav-tab <?php echo $tab->slug === $settings->getActiveTab()->slug ? 'nav-tab-active' : ''; ?>" data-rrze-tour="tab-<?php echo esc_attr((string) $tab->slug); ?>"><?php echo esc_html((string) $tab->title); ?></a>
    <?php } ?>
</h2>
