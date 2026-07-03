<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class SubmissionCsv
{
    /**
     * @param list<array{0: string, 1: string}> $rows Label/value pairs.
     */
    public static function build(array $rows): string
    {
        $lines = [
            self::formatRow([
                __('Field', 'rrze-formular'),
                __('Value', 'rrze-formular'),
            ]),
        ];

        foreach ($rows as $row) {
            $lines[] = self::formatRow([
                (string) ($row[0] ?? ''),
                (string) ($row[1] ?? ''),
            ]);
        }

        return "\xEF\xBB\xBF" . implode("\n", $lines);
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
        return implode(',', array_map([self::class, 'escapeField'], $fields));
    }

    private static function escapeField(string $value): string
    {
        if (
            !str_contains($value, '"')
            && !str_contains($value, ',')
            && !str_contains($value, "\n")
            && !str_contains($value, "\r")
        ) {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
