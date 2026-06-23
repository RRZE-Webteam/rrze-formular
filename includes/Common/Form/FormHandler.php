<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class FormHandler
{
    public function handle(array $payload): array
    {
        if (!SpamProtection::checkRateLimit()) {
            return $this->error(__('Too many submissions. Please try again later.', 'rrze-formular'), 429);
        }

        $honeypot = (string) ($payload['website'] ?? '');
        if (!SpamProtection::checkHoneypot($honeypot)) {
            return $this->error(__('Spam detected.', 'rrze-formular'), 400);
        }

        $token = (string) ($payload['token'] ?? '');
        if (!SpamProtection::verifyToken($token)) {
            return $this->error(__('Invalid or too fast submission.', 'rrze-formular'), 400);
        }

        $attributes = $this->normalizeAttributes($payload['attributes'] ?? []);
        $fields = FieldTypes::localizeFieldsForDisplay(
            FieldTypes::sanitizeFields($attributes['fields'] ?? [])
        );
        $attributes['formTitle'] = FieldTypes::localizeDisplayString($attributes['formTitle']);
        $attributes['formDescription'] = FieldTypes::localizeDisplayString($attributes['formDescription']);
        $inputFields = array_values(array_filter($fields, static fn(array $field): bool => $field['type'] !== 'heading'));

        if ($inputFields === []) {
            return $this->error(__('This form has no fields.', 'rrze-formular'), 400);
        }

        $values = is_array($payload['values'] ?? null) ? $payload['values'] : [];
        $sanitized = $this->sanitizeValues($inputFields, $values);
        $errors = $this->validateValues($inputFields, $sanitized);

        if ($errors !== []) {
            return [
                'success' => false,
                'message' => __('Please fill in all required fields correctly.', 'rrze-formular'),
                'errors' => $errors,
                'status' => 422,
            ];
        }

        $recipient = Mailer::getRecipient();
        if ($recipient === '') {
            return $this->error(__('No valid recipient configured.', 'rrze-formular'), 500);
        }

        $options = Mailer::getOptions();
        $includeSso = array_key_exists('includeSsoInfo', $attributes)
            ? (bool) $attributes['includeSsoInfo']
            : !empty($options['include_sso_by_default']);

        $ssoData = $includeSso ? SSO::getUserData() : null;
        $mailBody = $this->buildMailBody($attributes, $inputFields, $sanitized, $ssoData);
        $subject = $this->buildSubject($attributes, $inputFields, $sanitized);

        $sent = Mailer::sendOperatorMail($recipient, $subject, $mailBody);
        if (!$sent) {
            return $this->error(__('The message could not be sent.', 'rrze-formular'), 500);
        }

        $sendConfirmation = !empty($attributes['sendConfirmation']);
        $submitterEmail = $this->findSubmitterEmail($inputFields, $sanitized);
        if ($sendConfirmation && $submitterEmail !== '') {
            Mailer::maybeSendConfirmation(
                true,
                $submitterEmail,
                sprintf(__('Confirmation: %s', 'rrze-formular'), $subject),
                $this->buildConfirmationBody($attributes, $inputFields, $sanitized)
            );
        }

        return [
            'success' => true,
            'message' => sanitize_text_field((string) ($attributes['successMessage'] ?? __('Thank you. Your message has been sent.', 'rrze-formular'))),
            'status' => 200,
        ];
    }

    private function normalizeAttributes(array $attributes): array
    {
        return [
            'formTitle' => sanitize_text_field((string) ($attributes['formTitle'] ?? '')),
            'formDescription' => sanitize_textarea_field((string) ($attributes['formDescription'] ?? '')),
            'submitLabel' => sanitize_text_field((string) ($attributes['submitLabel'] ?? __('Send', 'rrze-formular'))),
            'successMessage' => sanitize_text_field((string) ($attributes['successMessage'] ?? '')),
            'includeSsoInfo' => !empty($attributes['includeSsoInfo']),
            'sendConfirmation' => !empty($attributes['sendConfirmation']),
            'fields' => is_array($attributes['fields'] ?? null) ? $attributes['fields'] : [],
        ];
    }

    private function sanitizeValues(array $fields, array $values): array
    {
        $sanitized = [];

        foreach ($fields as $field) {
            $id = $field['id'];
            $raw = $values[$id] ?? '';

            switch ($field['type']) {
                case 'email':
                    $sanitized[$id] = sanitize_email((string) $raw);
                    break;
                case 'textarea':
                    $sanitized[$id] = sanitize_textarea_field((string) $raw);
                    break;
                case 'number':
                    $sanitized[$id] = is_numeric($raw) ? (string) (0 + $raw) : '';
                    break;
                case 'tel':
                    $sanitized[$id] = preg_replace('/[^0-9+()\-\s]/', '', (string) $raw) ?? '';
                    break;
                case 'checkbox':
                    $sanitized[$id] = !empty($raw) ? '1' : '';
                    break;
                case 'select':
                case 'radio':
                    $allowed = array_column($field['options'], 'value');
                    $value = sanitize_text_field((string) $raw);
                    $sanitized[$id] = in_array($value, $allowed, true) ? $value : '';
                    break;
                default:
                    $sanitized[$id] = sanitize_text_field((string) $raw);
            }
        }

        return $sanitized;
    }

    private function validateValues(array $fields, array $values): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $id = $field['id'];
            $value = $values[$id] ?? '';

            if (!empty($field['required']) && ($value === '' || $value === null)) {
                $errors[$id] = __('This field is required.', 'rrze-formular');
                continue;
            }

            if ($field['type'] === 'email' && $value !== '' && !is_email($value)) {
                $errors[$id] = __('Please enter a valid e-mail address.', 'rrze-formular');
            }
        }

        return $errors;
    }

    private function buildSubject(array $attributes, array $fields, array $values): string
    {
        $title = $attributes['formTitle'] !== ''
            ? $attributes['formTitle']
            : get_bloginfo('name');

        foreach ($fields as $field) {
            if ($field['id'] === 'subject' && !empty($values['subject'])) {
                return sprintf('%s: %s', $title, $values['subject']);
            }
        }

        return sprintf(__('Form submission: %s', 'rrze-formular'), $title);
    }

    private function buildMailBody(array $attributes, array $fields, array $values, ?array $ssoData): string
    {
        $lines = [];

        if ($attributes['formTitle'] !== '') {
            $lines[] = $attributes['formTitle'];
            $lines[] = str_repeat('-', min(40, strlen($attributes['formTitle'])));
        }

        if ($attributes['formDescription'] !== '') {
            $lines[] = $attributes['formDescription'];
            $lines[] = '';
        }

        foreach ($fields as $field) {
            $label = $field['label'] !== '' ? $field['label'] : $field['id'];
            $value = $values[$field['id']] ?? '';

            if ($field['type'] === 'checkbox') {
                $value = $value !== '' ? __('Yes', 'rrze-formular') : __('No', 'rrze-formular');
            } elseif ($field['type'] === 'select' || $field['type'] === 'radio') {
                foreach ($field['options'] as $option) {
                    if ($option['value'] === $value) {
                        $value = $option['label'];
                        break;
                    }
                }
            }

            $lines[] = $label . ': ' . $value;
        }

        if ($ssoData !== null) {
            $lines[] = '';
            $lines[] = SSO::formatForMail($ssoData);
        }

        $lines[] = '';
        $lines[] = __('Submitted from', 'rrze-formular') . ': ' . esc_url_raw((string) (wp_get_referer() ?: home_url('/')));
        $lines[] = __('Date', 'rrze-formular') . ': ' . wp_date('Y-m-d H:i:s');

        return implode("\n", $lines);
    }

    private function buildConfirmationBody(array $attributes, array $fields, array $values): string
    {
        $lines = [
            __('We received your submission.', 'rrze-formular'),
            '',
        ];

        if ($attributes['formTitle'] !== '') {
            $lines[] = $attributes['formTitle'];
            $lines[] = '';
        }

        foreach ($fields as $field) {
            if ($field['type'] === 'textarea') {
                continue;
            }

            $label = $field['label'] !== '' ? $field['label'] : $field['id'];
            $lines[] = $label . ': ' . ($values[$field['id']] ?? '');
        }

        return implode("\n", $lines);
    }

    private function findSubmitterEmail(array $fields, array $values): string
    {
        foreach ($fields as $field) {
            if ($field['type'] === 'email' && !empty($values[$field['id']])) {
                return sanitize_email((string) $values[$field['id']]);
            }
        }

        return '';
    }

    private function error(string $message, int $status): array
    {
        return [
            'success' => false,
            'message' => $message,
            'status' => $status,
        ];
    }
}
