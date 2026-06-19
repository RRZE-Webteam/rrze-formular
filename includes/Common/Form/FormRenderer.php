<?php

namespace RRZE\FormWizard\Common\Form;

defined('ABSPATH') || exit;

class FormRenderer
{
    public static function render(array $attributes): string
    {
        $attributes = self::normalizeAttributes($attributes);
        $fields = FieldTypes::sanitizeFields($attributes['fields']);
        $steps = self::groupBySteps($fields);
        $tokenData = SpamProtection::createToken();
        $formId = wp_unique_id('rrze-fw-');

        ob_start();
        ?>
        <div class="rrze-formular" id="<?php echo esc_attr($formId); ?>"
             data-form-id="<?php echo esc_attr($formId); ?>"
             data-steps="<?php echo esc_attr((string) count($steps)); ?>">
            <?php if ($attributes['formTitle'] !== '') : ?>
                <h2 class="rrze-formular__title"><?php echo esc_html($attributes['formTitle']); ?></h2>
            <?php endif; ?>

            <?php if ($attributes['formDescription'] !== '') : ?>
                <p class="rrze-formular__description"><?php echo esc_html($attributes['formDescription']); ?></p>
            <?php endif; ?>

            <?php if (count($steps) > 1) : ?>
                <ol class="rrze-formular__progress" aria-label="<?php esc_attr_e('Form progress', 'rrze-formular'); ?>">
                    <?php foreach ($steps as $index => $stepFields) : ?>
                        <?php $stepNumber = $index + 1; ?>
                        <li class="rrze-formular__progress-item<?php echo $stepNumber === 1 ? ' is-active' : ''; ?>"
                            data-step="<?php echo esc_attr((string) $stepNumber); ?>">
                            <span><?php echo esc_html(sprintf(__('Step %d', 'rrze-formular'), $stepNumber)); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <form class="rrze-formular__form"
                  method="post"
                  action="#"
                  novalidate
                  data-attributes="<?php echo esc_attr(wp_json_encode($attributes)); ?>">
                <input type="hidden" name="token" value="<?php echo esc_attr($tokenData['token']); ?>">
                <input type="hidden" name="issuedAt" value="<?php echo esc_attr((string) $tokenData['issuedAt']); ?>">

                <div class="rrze-formular__hp" aria-hidden="true">
                    <label for="<?php echo esc_attr($formId); ?>-website"><?php esc_html_e('Website', 'rrze-formular'); ?></label>
                    <input type="text"
                           id="<?php echo esc_attr($formId); ?>-website"
                           name="website"
                           tabindex="-1"
                           autocomplete="off">
                </div>

                <?php foreach ($steps as $index => $stepFields) : ?>
                    <?php $stepNumber = $index + 1; ?>
                    <fieldset class="rrze-formular__step<?php echo $stepNumber === 1 ? ' is-active' : ''; ?>"
                              data-step="<?php echo esc_attr((string) $stepNumber); ?>"
                              <?php echo $stepNumber > 1 ? ' hidden' : ''; ?>>
                        <?php if (count($steps) > 1) : ?>
                            <legend class="screen-reader-text">
                                <?php echo esc_html(sprintf(__('Step %d', 'rrze-formular'), $stepNumber)); ?>
                            </legend>
                        <?php endif; ?>

                        <?php foreach ($stepFields as $field) : ?>
                            <?php echo self::renderField($field, $formId); ?>
                        <?php endforeach; ?>

                        <div class="rrze-formular__nav">
                            <?php if ($stepNumber > 1) : ?>
                                <button type="button" class="rrze-formular__prev">
                                    <?php esc_html_e('Back', 'rrze-formular'); ?>
                                </button>
                            <?php endif; ?>

                            <?php if ($stepNumber < count($steps)) : ?>
                                <button type="button" class="rrze-formular__next">
                                    <?php esc_html_e('Next', 'rrze-formular'); ?>
                                </button>
                            <?php else : ?>
                                <button type="submit" class="rrze-formular__submit">
                                    <?php echo esc_html($attributes['submitLabel']); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>

                <div class="rrze-formular__message" role="status" aria-live="polite" hidden></div>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function normalizeAttributes(array $attributes): array
    {
        return [
            'formTitle' => sanitize_text_field((string) ($attributes['formTitle'] ?? '')),
            'formDescription' => sanitize_textarea_field((string) ($attributes['formDescription'] ?? '')),
            'recipientEmail' => sanitize_email((string) ($attributes['recipientEmail'] ?? '')),
            'submitLabel' => sanitize_text_field((string) ($attributes['submitLabel'] ?? __('Send', 'rrze-formular'))),
            'successMessage' => sanitize_text_field((string) ($attributes['successMessage'] ?? __('Thank you. Your message has been sent.', 'rrze-formular'))),
            'includeSsoInfo' => !empty($attributes['includeSsoInfo']),
            'sendConfirmation' => !empty($attributes['sendConfirmation']),
            'template' => sanitize_key((string) ($attributes['template'] ?? 'blank')),
            'fields' => is_array($attributes['fields'] ?? null) ? $attributes['fields'] : [],
        ];
    }

    private static function groupBySteps(array $fields): array
    {
        $steps = [];

        foreach ($fields as $field) {
            $step = max(1, (int) ($field['step'] ?? 1));
            $steps[$step][] = $field;
        }

        if ($steps === []) {
            return [1 => []];
        }

        ksort($steps);
        return array_values($steps);
    }

    private static function renderField(array $field, string $formId): string
    {
        if ($field['type'] === 'heading') {
            return sprintf(
                '<h3 class="rrze-formular__heading">%s</h3>',
                esc_html($field['label'])
            );
        }

        $fieldId = $formId . '-' . $field['id'];
        $required = !empty($field['required']);
        $requiredAttr = $required ? ' required' : '';
        $requiredMark = $required ? ' <span class="rrze-formular__required" aria-hidden="true">*</span>' : '';

        ob_start();
        ?>
        <div class="rrze-formular__field rrze-formular__field--<?php echo esc_attr($field['type']); ?>">
            <?php if ($field['type'] !== 'checkbox') : ?>
                <label for="<?php echo esc_attr($fieldId); ?>">
                    <?php echo esc_html($field['label']); ?><?php echo wp_kses_post($requiredMark); ?>
                </label>
            <?php endif; ?>

            <?php if ($field['type'] === 'textarea') : ?>
                <textarea id="<?php echo esc_attr($fieldId); ?>"
                          name="<?php echo esc_attr($field['id']); ?>"
                          placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                          rows="5"<?php echo $requiredAttr; ?>></textarea>
            <?php elseif ($field['type'] === 'select') : ?>
                <select id="<?php echo esc_attr($fieldId); ?>"
                        name="<?php echo esc_attr($field['id']); ?>"<?php echo $requiredAttr; ?>>
                    <option value=""><?php esc_html_e('Please choose…', 'rrze-formular'); ?></option>
                    <?php foreach ($field['options'] as $option) : ?>
                        <option value="<?php echo esc_attr($option['value']); ?>">
                            <?php echo esc_html($option['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($field['type'] === 'radio') : ?>
                <fieldset class="rrze-formular__radio-group">
                    <legend class="screen-reader-text"><?php echo esc_html($field['label']); ?></legend>
                    <?php foreach ($field['options'] as $optionIndex => $option) : ?>
                        <?php $optionId = $fieldId . '-' . $optionIndex; ?>
                        <label class="rrze-formular__radio" for="<?php echo esc_attr($optionId); ?>">
                            <input type="radio"
                                   id="<?php echo esc_attr($optionId); ?>"
                                   name="<?php echo esc_attr($field['id']); ?>"
                                   value="<?php echo esc_attr($option['value']); ?>"<?php echo $requiredAttr; ?>>
                            <span><?php echo esc_html($option['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php elseif ($field['type'] === 'checkbox') : ?>
                <label class="rrze-formular__checkbox" for="<?php echo esc_attr($fieldId); ?>">
                    <input type="checkbox"
                           id="<?php echo esc_attr($fieldId); ?>"
                           name="<?php echo esc_attr($field['id']); ?>"
                           value="1"<?php echo $requiredAttr; ?>>
                    <span><?php echo esc_html($field['label']); ?><?php echo wp_kses_post($requiredMark); ?></span>
                </label>
            <?php else : ?>
                <input type="<?php echo esc_attr(FieldTypes::TYPES[$field['type']]['inputType'] ?? 'text'); ?>"
                       id="<?php echo esc_attr($fieldId); ?>"
                       name="<?php echo esc_attr($field['id']); ?>"
                       placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                       <?php echo $requiredAttr; ?>>
            <?php endif; ?>

            <p class="rrze-formular__error" data-field="<?php echo esc_attr($field['id']); ?>" hidden></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
