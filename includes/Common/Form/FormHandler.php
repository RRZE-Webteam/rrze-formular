<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

/**
 * Validates a form submission and sends one message to the resolved operator.
 *
 * Only fields from the signed configuration are processed. A submission token
 * is consumed before field validation and delivery, so it cannot be reused
 * even when later validation fails. Results include an HTTP status for FormAPI.
 */
class FormHandler
{
    /**
     * Process a submission using the form locale and restore the previous locale afterwards.
     *
     * @param array<string, mixed> $payload Submitted values, signed configuration, token and optional page/locale data.
     * @return array{success: bool, message: string, status: int, errors?: array<string, string>} Submission result.
     */
    public function handle(array $payload): array
    {
        return FormLocale::withSiteLocale(fn (): array => $this->processSubmission($payload), $payload);
    }

    /**
     * Validate anti-spam credentials, fields and recipient before sending operator mail.
     *
     * The honeypot and configuration are checked before the token is claimed.
     * Field and recipient validation run before an hourly submission slot is used.
     * Delivery failure is returned to the caller; no confirmation mail is sent.
     *
     * @param array<string, mixed> $payload Submission payload assembled by FormAPI.
     * @return array{success: bool, message: string, status: int, errors?: array<string, string>} Submission result.
     */
    private function processSubmission(array $payload): array
    {
        $honeypot = (string) ($payload['website'] ?? '');
        if (!SpamProtection::checkHoneypot($honeypot)) {
            return $this->error(__('Spam detected.', 'rrze-formular'), 400);
        }

        $token = (string) ($payload['token'] ?? '');
        $trustedConfig = FormConfigAuth::verify(
            (string) ($payload['formConfig'] ?? ''),
            (string) ($payload['formConfigSig'] ?? '')
        );

        if ($trustedConfig === null) {
            return $this->error(__('Invalid form configuration.', 'rrze-formular'), 400);
        }

        $configHash = FormConfigAuth::configHash($trustedConfig);
        $tokenData = SpamProtection::claimToken(
            $token,
            $configHash,
            (string) ($payload['pageUrl'] ?? '')
        );
        if ($tokenData === null) {
            return $this->error(__('Invalid or too fast submission.', 'rrze-formular'), 400);
        }

        $attributes = $this->attributesFromTrustedConfig($trustedConfig);
        $fields = FieldTypes::localizeFieldsForDisplay($trustedConfig['fields']);
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

        $recipient = Mailer::resolveRecipient($attributes);
        $recipientError = Mailer::validateResolvedRecipient(
            $recipient,
            (string) ($trustedConfig['recipientEmail'] ?? '')
        );
        if ($recipientError !== null) {
            return $this->error($recipientError, 422);
        }

        if (!SpamProtection::tryAcquireSubmissionSlot()) {
            return $this->error(__('Too many submissions. Please try again later.', 'rrze-formular'), 429);
        }

        $submissionUrl = Mailer::resolveSubmissionUrl((string) ($payload['pageUrl'] ?? ''));
        $websiteHeaders = Mailer::websiteHeaders($submissionUrl);
        $submitterEmail = $this->findSubmitterEmail($inputFields, $sanitized);
        $submitterName = $this->findSubmitterName($sanitized);
        $operatorHeaders = $websiteHeaders;

        if ($submitterEmail !== '') {
            $operatorHeaders[] = sprintf(
                'Reply-To: %s',
                Mailer::formatMailboxAddress($submitterEmail, $submitterName)
            );
        }

        $mailBody = $this->buildMailBody($inputFields, $sanitized);
        $subject = $this->buildSubject($attributes, $sanitized);

        $sent = Mailer::sendOperatorMail(
            $recipient['email'],
            $subject,
            $mailBody,
            $operatorHeaders,
            $recipient['name']
        );

        if (!$sent) {
            return $this->error(__('The message could not be sent.', 'rrze-formular'), 500);
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

    /**
     * Extract supported form settings from an authenticated configuration.
     *
     * @param array<string, mixed> $trustedConfig Configuration returned by FormConfigAuth::verify().
     * @return array<string, mixed> Sanitized form and recipient attributes.
     */
    private function attributesFromTrustedConfig(array $trustedConfig): array
    {
        return [
            'formTitle' => sanitize_text_field((string) ($trustedConfig['formTitle'] ?? '')),
            'formDescription' => sanitize_textarea_field((string) ($trustedConfig['formDescription'] ?? '')),
            'successMessage' => sanitize_text_field((string) ($trustedConfig['successMessage'] ?? '')),
            'recipientEmail' => sanitize_text_field((string) ($trustedConfig['recipientEmail'] ?? '')),
            'recipientName' => sanitize_text_field((string) ($trustedConfig['recipientName'] ?? '')),
            'fields' => is_array($trustedConfig['fields'] ?? null) ? $trustedConfig['fields'] : [],
        ];
    }

    /**
     * Normalize submitted values according to their declared field types.
     *
     * Choice fields discard values outside their configured options. Multiselect
     * fields produce lists; checkbox values become 1 or an empty string.
     *
     * @param list<array<string, mixed>> $fields Normalized input fields; headings are excluded.
     * @param array<string, mixed> $values Raw submitted values keyed by field ID.
     * @return array<string, string|list<string>> Sanitized values for known fields only.
     */
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

    /**
     * Check required values and validate non-empty e-mail fields.
     *
     * @param list<array<string, mixed>> $fields Normalized input fields.
     * @param array<string, string|list<string>> $values Sanitized values keyed by field ID.
     * @return array<string, string> Translated errors keyed by field ID; empty when valid.
     */
    private function validateValues(array $fields, array $values): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $id = $field['id'];
            $value = $values[$id] ?? '';

            if (FieldTypes::isFieldRequired($field)) {
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

    /**
     * Use the submitted subject, or fall back to the form title and site name.
     *
     * @param array<string, mixed> $attributes Form attributes containing formTitle.
     * @param array<string, string|list<string>> $values Sanitized submitted values.
     * @return string Mail subject before the configured prefix is added.
     */
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

    /**
     * Compose a plain-text message from submitted fields and site/date metadata.
     *
     * @param list<array<string, mixed>> $fields Normalized input fields with localized labels.
     * @param array<string, string|list<string>> $values Sanitized submitted values.
     * @return string Mail body with the main message first, followed by contact and other fields.
     */
    private function buildMailBody(array $fields, array $values): string
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

        $lines[] = Mailer::formatSiteLinkLine();
        $lines[] = Mailer::formatMailDateLine();

        return implode("\n", $lines);
    }

    /**
     * Find the first non-empty submitted e-mail field for the Reply-To header.
     *
     * @param list<array<string, mixed>> $fields Normalized input fields in display order.
     * @param array<string, string|list<string>> $values Sanitized submitted values.
     * @return string Submitted e-mail address, or an empty string.
     */
    private function findSubmitterEmail(array $fields, array $values): string
    {
        foreach ($fields as $field) {
            if ($field['type'] === 'email' && !empty($values[$field['id']])) {
                return sanitize_email((string) $values[$field['id']]);
            }
        }

        return '';
    }

    /**
     * Read a name from submitted first/last-name fields, then the name field.
     *
     * @param array<string, string|list<string>> $values Sanitized submitted values.
     * @return string Submitted display name, or an empty string; no account data is used.
     */
    private function findSubmitterName(array $values): string
    {
        $fullName = trim(($values['firstname'] ?? '') . ' ' . ($values['lastname'] ?? ''));
        if ($fullName !== '') {
            return sanitize_text_field($fullName);
        }

        if (($values['name'] ?? '') !== '') {
            return sanitize_text_field((string) $values['name']);
        }

        return '';
    }

    /**
     * Select the message field or the first non-empty textarea as the mail opening.
     *
     * @param list<array<string, mixed>> $fields Normalized input fields in display order.
     * @param array<string, string|list<string>> $values Sanitized submitted values.
     * @return string|null ID of the leading message field, or null when none has content.
     */
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

    /**
     * Format a submitted value using its field definition when available.
     *
     * @param list<array<string, mixed>> $fields Normalized input field definitions.
     * @param array<string, string|list<string>> $values Sanitized submitted values.
     * @param string $fieldId Field ID to look up.
     * @return string Human-readable field value, or an empty string when no value is present.
     */
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

    /**
     * Convert a stored field value into readable plain text for an e-mail.
     *
     * @param array<string, mixed> $field Normalized field definition with localized choice labels.
     * @param string|list<string> $value Sanitized field value.
     * @return string Choice labels, translated Yes/No, or the plain submitted value.
     */
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

    /**
     * Build a failed-submission result for the API layer.
     *
     * @param string $message Public error message.
     * @param int $status HTTP status to use for the response.
     * @return array{success: false, message: string, status: int} Error result.
     */
    private function error(string $message, int $status): array
    {
        return [
            'success' => false,
            'message' => $message,
            'status' => $status,
        ];
    }
}
