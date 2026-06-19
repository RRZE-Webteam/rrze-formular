<?php

use RRZE\FormWizard\Common\Form\FormRenderer;

defined('ABSPATH') || exit;

echo FormRenderer::render((array) ($attributes ?? []));
