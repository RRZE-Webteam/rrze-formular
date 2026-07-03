<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class SubmissionCsv
{
    private const DELIMITER = ',';

    /**
     * @param list<string> $headers Field labels (row 1).
     * @param list<string> $values Submitted values in the same order (row 2).
     */
    public static function build(array $headers, array $values): string
    {
        $count = count($headers);
        if ($count === 0) {
            return '';
        }

        if (count($values) < $count) {
            $values = array_pad($values, $count, '');
        } elseif (count($values) > $count) {
            $values = array_slice($values, 0, $count);
        }

        return "\xEF\xBB\xBF"
            . self::formatRow($headers)
            . "\r\n"
            . self::formatRow($values);
    }

    public static function filename(string $formTitle): string
    {
        $slug = sanitize_title($formTitle);
        if ($slug === '') {
            $slug = 'formular';
        }

        $filename = wp_date('Y-m-d-His') . '-' . $slug . '.csv';
        $filename = sanitize_file_name($filename);

        if ($filename === '' || !str_ends_with(strtolower($filename), '.csv')) {
            $filename = wp_date('Y-m-d-His') . '-formular.csv';
            $filename = sanitize_file_name($filename);
        }

        return $filename !== '' ? $filename : 'formular.csv';
    }

    /**
     * @param list<string> $fields
     */
    private static function formatRow(array $fields): string
    {
        return implode(self::DELIMITER, array_map([self::class, 'escapeField'], $fields));
    }

    private static function escapeField(string $value): string
    {
        if (
            !str_contains($value, '"')
            && !str_contains($value, self::DELIMITER)
            && !str_contains($value, "\n")
            && !str_contains($value, "\r")
        ) {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
