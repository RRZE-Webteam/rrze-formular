<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class FormHandler
{
    public function handle(array $payload): array
    {
        return FormLocale::withSiteLocale(fn (): array => $this->processSubmission($payload), $payload);
    }

    private function processSubmission(array $payload): array
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
        $submissionUrl = Mailer::resolveSubmissionUrl((string) ($payload['pageUrl'] ?? ''));
        $websiteHeaders = Mailer::websiteHeaders($submissionUrl);
        $mailBody = $this->buildMailBody($inputFields, $sanitized, $ssoData);
        $subject = $this->buildSubject($attributes, $sanitized);

        $sent = Mailer::sendOperatorMail($recipient, $subject, $mailBody, $websiteHeaders);
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
                $this->buildConfirmationBody($inputFields, $sanitized, $ssoData),
                $websiteHeaders
            );
        }

        $successMessage = $attributes['successMessage'] !== ''
            ? $attributes['successMessage']
            : __('Thank you. Your message has been sent.', 'rrze-formular');

        return [
            'success' => true,
            'message' => sanitize_text_field($successMessage),
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
                case 'date':
                    $value = sanitize_text_field((string) $raw);
                    $sanitized[$id] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
                    break;
                case 'time':
                    $value = sanitize_text_field((string) $raw);
                    $sanitized[$id] = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? $value : '';
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
                case 'multiselect':
                    $allowed = array_column($field['options'], 'value');
                    $rawValues = is_array($raw)
                        ? $raw
                        : array_filter(array_map('trim', explode(',', (string) $raw)));
                    $filtered = [];
                    foreach ($rawValues as $rawValue) {
                        $value = sanitize_text_field((string) $rawValue);
                        if ($value !== '' && in_array($value, $allowed, true)) {
                            $filtered[] = $value;
                        }
                    }
                    $sanitized[$id] = $filtered;
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

            if (!empty($field['required'])) {
                $isEmpty = is_array($value) ? $value === [] : ($value === '' || $value === null);
                if ($isEmpty) {
                    $errors[$id] = __('This field is required.', 'rrze-formular');
                    continue;
                }
            }

            if ($field['type'] === 'email' && $value !== '' && !is_email($value)) {
                $errors[$id] = __('Please enter a valid e-mail address.', 'rrze-formular');
            }
        }

        return $errors;
    }

    private function buildSubject(array $attributes, array $values): string
    {
        if (($values['subject'] ?? '') !== '') {
            return sanitize_text_field((string) $values['subject']);
        }

        $title = $attributes['formTitle'] !== ''
            ? $attributes['formTitle']
            : get_bloginfo('name');

        return sprintf(__('Form submission: %s', 'rrze-formular'), $title);
    }

    private function buildMailBody(array $fields, array $values, ?array $ssoData): string
    {
        $lines = [];
        $messageFieldId = $this->findLeadingMessageFieldId($fields, $values);

        if ($messageFieldId !== null) {
            $lines[] = $values[$messageFieldId];
        }

        $lines[] = '';

        $fullName = trim(($values['firstname'] ?? '') . ' ' . ($values['lastname'] ?? ''));
        if ($fullName !== '') {
            $lines[] = $fullName;
        }

        foreach (['email', 'phone', 'organisation'] as $fieldId) {
            $value = $this->valueForFieldId($fields, $values, $fieldId);
            if ($value !== '') {
                $lines[] = $value;
            }
        }

        $reservedIds = ['message', 'subject', 'firstname', 'lastname', 'email', 'phone', 'organisation'];
        foreach ($fields as $field) {
            if (in_array($field['id'], $reservedIds, true) || $field['id'] === $messageFieldId) {
                continue;
            }

            $value = $this->formatFieldValueForMail($field, $values[$field['id']] ?? '');
            if ($value === '') {
                continue;
            }

            $label = $field['label'] !== '' ? $field['label'] : $field['id'];
            $lines[] = $label . ': ' . $value;
        }

        $lines[] = '';

        if ($ssoData !== null) {
            $lines[] = SSO::formatCompactLine($ssoData);
        }

        $lines[] = Mailer::formatSiteLinkLine();
        $lines[] = Mailer::formatMailDateLine();

        return implode("\n", $lines);
    }

    private function buildConfirmationBody(array $fields, array $values, ?array $ssoData): string
    {
        $lines = [
            __('We received your submission.', 'rrze-formular'),
            '',
        ];

        $messageFieldId = $this->findLeadingMessageFieldId($fields, $values);
        if ($messageFieldId !== null) {
            $lines[] = $values[$messageFieldId];
            $lines[] = '';
        }

        $fullName = trim(($values['firstname'] ?? '') . ' ' . ($values['lastname'] ?? ''));
        if ($fullName !== '') {
            $lines[] = $fullName;
        }

        foreach (['email', 'phone', 'organisation'] as $fieldId) {
            $value = $this->valueForFieldId($fields, $values, $fieldId);
            if ($value !== '') {
                $lines[] = $value;
            }
        }

        $lines[] = '';

        if ($ssoData !== null) {
            $lines[] = SSO::formatCompactLine($ssoData);
        }

        $lines[] = Mailer::formatSiteLinkLine();
        $lines[] = Mailer::formatMailDateLine();

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

    private function findLeadingMessageFieldId(array $fields, array $values): ?string
    {
        foreach ($fields as $field) {
            if ($field['id'] === 'message' && ($values['message'] ?? '') !== '') {
                return 'message';
            }
        }

        foreach ($fields as $field) {
            if ($field['type'] === 'textarea' && ($values[$field['id']] ?? '') !== '') {
                return $field['id'];
            }
        }

        return null;
    }

    private function valueForFieldId(array $fields, array $values, string $fieldId): string
    {
        if (($values[$fieldId] ?? '') === '') {
            return '';
        }

        foreach ($fields as $field) {
            if ($field['id'] !== $fieldId) {
                continue;
            }

            return $this->formatFieldValueForMail($field, $values[$fieldId]);
        }

        return sanitize_text_field((string) $values[$fieldId]);
    }

    private function formatFieldValueForMail(array $field, string|array $value): string
    {
        if ($field['type'] === 'checkbox') {
            $value = is_array($value) ? '' : (string) $value;
            return $value !== '' ? __('Yes', 'rrze-formular') : __('No', 'rrze-formular');
        }

        if ($field['type'] === 'select' || $field['type'] === 'radio' || $field['type'] === 'multiselect') {
            if ($field['type'] === 'multiselect') {
                $selected = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)));
                $labels = [];
                foreach ($field['options'] as $option) {
                    if (in_array($option['value'], $selected, true)) {
                        $labels[] = $option['label'];
                    }
                }
                return implode(', ', $labels);
            }

            foreach ($field['options'] as $option) {
                if ($option['value'] === (string) $value) {
                    return $option['label'];
                }
            }
        }

        return is_array($value) ? implode(', ', $value) : (string) $value;
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
