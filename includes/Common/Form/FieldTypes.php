<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class FieldTypes
{
    public const TYPES = [
        'text' => [
            'label' => 'Text',
            'hasOptions' => false,
            'inputType' => 'text',
        ],
        'email' => [
            'label' => 'E-Mail',
            'hasOptions' => false,
            'inputType' => 'email',
        ],
        'tel' => [
            'label' => 'Telephone',
            'hasOptions' => false,
            'inputType' => 'tel',
        ],
        'number' => [
            'label' => 'Number',
            'hasOptions' => false,
            'inputType' => 'number',
        ],
        'date' => [
            'label' => 'Date',
            'hasOptions' => false,
            'inputType' => 'date',
        ],
        'time' => [
            'label' => 'Time',
            'hasOptions' => false,
            'inputType' => 'time',
        ],
        'textarea' => [
            'label' => 'Textarea',
            'hasOptions' => false,
            'inputType' => 'textarea',
        ],
        'select' => [
            'label' => 'Select',
            'hasOptions' => true,
            'inputType' => 'select',
        ],
        'multiselect' => [
            'label' => 'Multiselect',
            'hasOptions' => true,
            'inputType' => 'multiselect',
        ],
        'radio' => [
            'label' => 'Radio',
            'hasOptions' => true,
            'inputType' => 'radio',
        ],
        'checkbox' => [
            'label' => 'Checkbox',
            'hasOptions' => false,
            'inputType' => 'checkbox',
        ],
        'heading' => [
            'label' => 'Section heading',
            'hasOptions' => false,
            'inputType' => 'heading',
        ],
    ];

    public static function isValidType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function sanitizeFieldDefinition(array $field, int $index = 0): array
    {
        $type = sanitize_key($field['type'] ?? 'text');
        if (!self::isValidType($type)) {
            $type = 'text';
        }

        $id = sanitize_key($field['id'] ?? ('field_' . ($index + 1)));
        if ($id === '') {
            $id = 'field_' . ($index + 1);
        }

        $options = [];
        if (!empty($field['options']) && is_array($field['options'])) {
            foreach ($field['options'] as $option) {
                $value = sanitize_text_field((string) ($option['value'] ?? ''));
                $label = sanitize_text_field((string) ($option['label'] ?? $value));
                if ($value !== '') {
                    $options[] = ['value' => $value, 'label' => $label];
                }
            }
        }

        return [
            'id' => $id,
            'type' => $type,
            'label' => sanitize_text_field((string) ($field['label'] ?? '')),
            'placeholder' => sanitize_text_field((string) ($field['placeholder'] ?? '')),
            'required' => self::isFieldRequired($field),
            'options' => $options,
        ];
    }

    public static function isFieldRequired(array $field): bool
    {
        if (!array_key_exists('required', $field)) {
            return false;
        }

        $required = $field['required'];

        if (is_bool($required)) {
            return $required;
        }

        if (is_int($required) || is_float($required)) {
            return (int) $required === 1;
        }

        if (is_string($required)) {
            return in_array(strtolower(trim($required)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    public static function sanitizeFields(array $fields): array
    {
        $sanitized = [];
        foreach (array_values($fields) as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $sanitized[] = self::sanitizeFieldDefinition($field, $index);
        }

        return $sanitized;
    }

    public static function localizeDisplayString(string $value): string
    {
        return $value === '' ? '' : __($value, 'rrze-formular');
    }

    public static function localizeFieldForDisplay(array $field): array
    {
        $field['label'] = self::localizeDisplayString($field['label']);
        $field['placeholder'] = self::localizeDisplayString($field['placeholder']);

        if (!empty($field['options'])) {
            foreach ($field['options'] as $index => $option) {
                $field['options'][$index]['label'] = self::localizeDisplayString((string) ($option['label'] ?? ''));
            }
        }

        return $field;
    }

    public static function localizeFieldsForDisplay(array $fields): array
    {
        return array_map([self::class, 'localizeFieldForDisplay'], $fields);
    }

    /**
     * HTML autocomplete token for a field (https://html.spec.whatwg.org/#autofill).
     */
    public static function getAutocomplete(array $field): string
    {
        $id = sanitize_key((string) ($field['id'] ?? ''));
        $type = (string) ($field['type'] ?? 'text');

        $byId = [
            'firstname' => 'given-name',
            'first_name' => 'given-name',
            'vorname' => 'given-name',
            'lastname' => 'family-name',
            'last_name' => 'family-name',
            'nachname' => 'family-name',
            'name' => 'name',
            'email' => 'email',
            'e_mail' => 'email',
            'phone' => 'tel',
            'telephone' => 'tel',
            'tel' => 'tel',
            'mobile' => 'tel',
            'organisation' => 'organization',
            'organization' => 'organization',
            'company' => 'organization',
            'street' => 'street-address',
            'address' => 'street-address',
            'postal_code' => 'postal-code',
            'zip' => 'postal-code',
            'plz' => 'postal-code',
            'city' => 'address-level2',
            'country' => 'country-name',
            'url' => 'url',
            'website' => 'url',
            'username' => 'username',
        ];

        if (isset($byId[$id])) {
            return $byId[$id];
        }

        $byType = [
            'email' => 'email',
            'tel' => 'tel',
        ];

        if (isset($byType[$type])) {
            return $byType[$type];
        }

        if (in_array($id, ['message', 'comment', 'subject', 'topic', 'abstract', 'motivation', 'skills'], true)) {
            return 'off';
        }

        return '';
    }
}
