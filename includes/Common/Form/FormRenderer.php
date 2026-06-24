<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class FormRenderer
{
    public static function render(array $attributes): string
    {
        $attributes = self::normalizeAttributes($attributes);
        $trustedConfig = FormConfigAuth::buildTrustedConfig($attributes);
        $configHash = FormConfigAuth::configHash($trustedConfig);
        $signedConfig = FormConfigAuth::sign($trustedConfig);
        $fields = FieldTypes::localizeFieldsForDisplay($trustedConfig['fields']);
        $attributes['formTitle'] = FieldTypes::localizeDisplayString($attributes['formTitle']);
        $attributes['formDescription'] = FieldTypes::localizeDisplayString($attributes['formDescription']);
        $attributes['submitLabel'] = FieldTypes::localizeDisplayString($attributes['submitLabel']);
        $tokenData = SpamProtection::createToken($configHash);
        $formId = wp_unique_id('rrze-fw-');

        ob_start();
        ?>
        <div class="rrze-formular" id="<?php echo esc_attr($formId); ?>"
             data-form-id="<?php echo esc_attr($formId); ?>">
            <?php if ($attributes['formTitle'] !== '') : ?>
                <h2 class="rrze-formular__title"><?php echo esc_html($attributes['formTitle']); ?></h2>
            <?php endif; ?>

            <?php if ($attributes['formDescription'] !== '') : ?>
                <p class="rrze-formular__description"><?php echo esc_html($attributes['formDescription']); ?></p>
            <?php endif; ?>

            <form class="rrze-formular__form"
                  method="post"
                  action="#"
                  novalidate>
                <input type="hidden" name="token" value="<?php echo esc_attr($tokenData['token']); ?>">
                <input type="hidden" name="issuedAt" value="<?php echo esc_attr((string) $tokenData['issuedAt']); ?>">
                <input type="hidden" name="formConfig" value="<?php echo esc_attr($signedConfig['payload']); ?>">
                <input type="hidden" name="formConfigSig" value="<?php echo esc_attr($signedConfig['signature']); ?>">

                <div class="rrze-formular__hp" aria-hidden="true">
                    <label for="<?php echo esc_attr($formId); ?>-website"><?php esc_html_e('Website', 'rrze-formular'); ?></label>
                    <input type="text"
                           id="<?php echo esc_attr($formId); ?>-website"
                           name="website"
                           tabindex="-1"
                           autocomplete="off">
                </div>

                <fieldset class="rrze-formular__fields">
                    <?php foreach ($fields as $field) : ?>
                        <?php echo self::renderField($field, $formId); ?>
                    <?php endforeach; ?>
                </fieldset>

                <div class="rrze-formular__actions">
                    <button type="submit" class="rrze-formular__submit">
                        <span class="rrze-formular__submit-text"><?php echo esc_html($attributes['submitLabel']); ?></span>
                        <span class="rrze-formular__submit-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">
                                <path d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6z"/>
                            </svg>
                        </span>
                    </button>
                </div>

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
            'submitLabel' => sanitize_text_field((string) ($attributes['submitLabel'] ?? __('Send', 'rrze-formular'))),
            'successMessage' => sanitize_text_field((string) ($attributes['successMessage'] ?? __('Thank you. Your message has been sent.', 'rrze-formular'))),
            'includeSsoInfo' => !empty($attributes['includeSsoInfo']),
            'sendConfirmation' => !empty($attributes['sendConfirmation']),
            'template' => sanitize_key((string) ($attributes['template'] ?? 'blank')),
            'fields' => is_array($attributes['fields'] ?? null) ? $attributes['fields'] : [],
        ];
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
        $required = FieldTypes::isFieldRequired($field);
        $requiredAttr = $required ? ' required' : '';
        $ariaRequired = $required ? ' aria-required="true"' : '';
        $requiredMark = self::requiredIndicator($required);
        $autocomplete = FieldTypes::getAutocomplete($field);
        $autocompleteAttr = $autocomplete !== '' ? ' autocomplete="' . esc_attr($autocomplete) . '"' : '';
        $usesChoiceLabel = in_array($field['type'], ['checkbox'], true);
        $isDropdown = in_array($field['type'], ['select', 'multiselect'], true);
        $labelFor = $isDropdown ? $fieldId . '-toggle' : $fieldId;
        $errorId = $fieldId . '-error';

        ob_start();
        ?>
        <div class="rrze-formular__field rrze-formular__field--<?php echo esc_attr($field['type']); ?>"
             id="<?php echo esc_attr($fieldId); ?>-field"
             data-field-id="<?php echo esc_attr($field['id']); ?>"
             data-field-label="<?php echo esc_attr($field['label']); ?>">
            <?php if (!$usesChoiceLabel && $field['type'] !== 'radio') : ?>
                <label class="rrze-formular__field-label"
                       id="<?php echo esc_attr($fieldId); ?>-label"
                       for="<?php echo esc_attr($labelFor); ?>">
                    <?php echo esc_html($field['label']); ?><?php echo wp_kses_post($requiredMark); ?>
                </label>
            <?php endif; ?>

            <?php if ($field['type'] === 'textarea') : ?>
                <textarea class="rrze-formular__input"
                          id="<?php echo esc_attr($fieldId); ?>"
                          name="<?php echo esc_attr($field['id']); ?>"
                          placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                          rows="5"<?php echo $requiredAttr; ?><?php echo $ariaRequired; ?><?php echo $autocompleteAttr; ?>></textarea>
            <?php elseif ($field['type'] === 'select') : ?>
                <?php echo self::renderDropdown($field, $fieldId, false, $required); ?>
            <?php elseif ($field['type'] === 'multiselect') : ?>
                <?php echo self::renderDropdown($field, $fieldId, true, $required); ?>
            <?php elseif ($field['type'] === 'radio') : ?>
                <fieldset class="rrze-formular__radio-group"<?php echo $ariaRequired; ?>>
                    <legend class="rrze-formular__field-label">
                        <?php echo esc_html($field['label']); ?><?php echo wp_kses_post($requiredMark); ?>
                    </legend>
                    <?php foreach ($field['options'] as $optionIndex => $option) : ?>
                        <?php
                        $optionId = $fieldId . '-' . $optionIndex;
                        $optionRequired = $required && $optionIndex === 0 ? ' required' : '';
                        ?>
                        <label class="rrze-formular__radio" for="<?php echo esc_attr($optionId); ?>">
                            <span class="rrze-formular__choice-control">
                                <input type="radio"
                                       id="<?php echo esc_attr($optionId); ?>"
                                       name="<?php echo esc_attr($field['id']); ?>"
                                       value="<?php echo esc_attr($option['value']); ?>"<?php echo $optionRequired; ?>>
                                <span class="rrze-formular__choice-mark" aria-hidden="true"></span>
                            </span>
                            <span class="rrze-formular__choice-text">
                                <span class="rrze-formular__choice-title"><?php echo esc_html($option['label']); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php elseif ($field['type'] === 'checkbox') : ?>
                <label class="rrze-formular__checkbox" for="<?php echo esc_attr($fieldId); ?>">
                    <span class="rrze-formular__choice-control">
                        <input type="checkbox"
                               class="rrze-formular__input"
                               id="<?php echo esc_attr($fieldId); ?>"
                               name="<?php echo esc_attr($field['id']); ?>"
                               value="1"<?php echo $requiredAttr; ?><?php echo $ariaRequired; ?>>
                        <span class="rrze-formular__choice-mark" aria-hidden="true"></span>
                    </span>
                    <span class="rrze-formular__choice-text">
                        <span class="rrze-formular__choice-title">
                            <?php echo esc_html($field['label']); ?><?php echo wp_kses_post($requiredMark); ?>
                        </span>
                        <?php if ($field['placeholder'] !== '') : ?>
                            <span class="rrze-formular__choice-help"><?php echo esc_html($field['placeholder']); ?></span>
                        <?php endif; ?>
                    </span>
                </label>
            <?php else : ?>
                <input class="rrze-formular__input"
                       type="<?php echo esc_attr(FieldTypes::TYPES[$field['type']]['inputType'] ?? 'text'); ?>"
                       id="<?php echo esc_attr($fieldId); ?>"
                       name="<?php echo esc_attr($field['id']); ?>"
                       placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                       <?php echo $requiredAttr; ?><?php echo $ariaRequired; ?><?php echo $autocompleteAttr; ?>>
            <?php endif; ?>

            <p class="rrze-formular__error"
               id="<?php echo esc_attr($errorId); ?>"
               data-field="<?php echo esc_attr($field['id']); ?>"
               hidden></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function renderDropdown(array $field, string $fieldId, bool $multiple, bool $required): string
    {
        $placeholder = __('Please choose…', 'rrze-formular');
        $confirmLabel = __('Confirm selection', 'rrze-formular');
        $multipleAttr = $multiple ? '1' : '0';
        $requiredAttr = $required ? ' required' : '';
        $ariaRequired = $required ? ' aria-required="true"' : '';

        ob_start();
        ?>
        <div class="rrze-formular__dropdown"
             id="<?php echo esc_attr($fieldId); ?>"
             data-multiple="<?php echo esc_attr($multipleAttr); ?>"
             data-field-name="<?php echo esc_attr($field['id']); ?>">
            <button type="button"
                    class="rrze-formular__dropdown-toggle"
                    aria-expanded="false"
                    aria-haspopup="listbox"
                    aria-controls="<?php echo esc_attr($fieldId); ?>-panel"
                    aria-labelledby="<?php echo esc_attr($fieldId); ?>-label"
                    id="<?php echo esc_attr($fieldId); ?>-toggle"<?php echo $ariaRequired; ?>>
                <span class="rrze-formular__dropdown-value is-placeholder"><?php echo esc_html($placeholder); ?></span>
                <span class="rrze-formular__dropdown-chevron" aria-hidden="true"></span>
            </button>
            <div class="rrze-formular__dropdown-panel"
                 id="<?php echo esc_attr($fieldId); ?>-panel"
                 role="listbox"
                 <?php echo $multiple ? '' : 'aria-labelledby="' . esc_attr($fieldId) . '-toggle"'; ?>
                 hidden>
                <div class="rrze-formular__dropdown-list">
                    <?php foreach ($field['options'] as $option) : ?>
                        <div class="rrze-formular__dropdown-item">
                            <?php if ($multiple) : ?>
                                <label class="rrze-formular__dropdown-option rrze-formular__dropdown-option--multi">
                                    <span class="rrze-formular__choice-control">
                                        <input type="checkbox"
                                               value="<?php echo esc_attr($option['value']); ?>"
                                               data-option-checkbox>
                                        <span class="rrze-formular__choice-mark" aria-hidden="true"></span>
                                    </span>
                                    <span class="rrze-formular__dropdown-option-label"><?php echo esc_html($option['label']); ?></span>
                                </label>
                            <?php else : ?>
                                <button type="button"
                                        class="rrze-formular__dropdown-option"
                                        role="option"
                                        data-value="<?php echo esc_attr($option['value']); ?>">
                                    <span class="rrze-formular__dropdown-option-label"><?php echo esc_html($option['label']); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($multiple) : ?>
                    <div class="rrze-formular__dropdown-footer">
                        <button type="button" class="rrze-formular__dropdown-confirm">
                            <span class="rrze-formular__dropdown-confirm-text"><?php echo esc_html($confirmLabel); ?></span>
                            <span class="rrze-formular__dropdown-confirm-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">
                                    <path d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6z"/>
                                </svg>
                            </span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <input type="hidden"
                   class="rrze-formular__dropdown-input"
                   id="<?php echo esc_attr($fieldId); ?>-input"
                   name="<?php echo esc_attr($field['id']); ?>"
                   value=""<?php echo $requiredAttr; ?>>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function requiredIndicator(bool $required): string
    {
        if (!$required) {
            return '';
        }

        return sprintf(
            ' <span class="rrze-formular__required" aria-hidden="true">*</span><span class="screen-reader-text"> %s</span>',
            esc_html__('(required)', 'rrze-formular')
        );
    }
}
