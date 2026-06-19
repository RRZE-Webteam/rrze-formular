<?php

use RRZE\Formular\Common\Form\FormRenderer;

defined('ABSPATH') || exit;

echo FormRenderer::render((array) ($attributes ?? []));
