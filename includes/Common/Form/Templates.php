<?php

namespace RRZE\FormWizard\Common\Form;

defined('ABSPATH') || exit;

class Templates
{
    public static function all(): array
    {
        $templates = [
            'blank' => [
                'label' => __('Blank form', 'rrze-formular'),
                'formTitle' => '',
                'formDescription' => '',
                'fields' => [],
            ],
            'contact' => [
                'label' => __('Contact form', 'rrze-formular'),
                'formTitle' => __('Contact', 'rrze-formular'),
                'formDescription' => __('Send us a message.', 'rrze-formular'),
                'fields' => [
                    [
                        'id' => 'name',
                        'type' => 'text',
                        'label' => __('Name', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                    [
                        'id' => 'email',
                        'type' => 'email',
                        'label' => __('E-mail address', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                    [
                        'id' => 'subject',
                        'type' => 'text',
                        'label' => __('Subject', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                    [
                        'id' => 'message',
                        'type' => 'textarea',
                        'label' => __('Message', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                ],
            ],
            'feedback' => [
                'label' => __('Feedback form', 'rrze-formular'),
                'formTitle' => __('Feedback', 'rrze-formular'),
                'formDescription' => __('We appreciate your feedback.', 'rrze-formular'),
                'fields' => [
                    [
                        'id' => 'rating',
                        'type' => 'select',
                        'label' => __('Overall rating', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                        'options' => [
                            ['value' => '5', 'label' => __('Excellent', 'rrze-formular')],
                            ['value' => '4', 'label' => __('Good', 'rrze-formular')],
                            ['value' => '3', 'label' => __('Average', 'rrze-formular')],
                            ['value' => '2', 'label' => __('Poor', 'rrze-formular')],
                            ['value' => '1', 'label' => __('Very poor', 'rrze-formular')],
                        ],
                    ],
                    [
                        'id' => 'comment',
                        'type' => 'textarea',
                        'label' => __('Your feedback', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                ],
            ],
            'event' => [
                'label' => __('Event registration', 'rrze-formular'),
                'formTitle' => __('Event registration', 'rrze-formular'),
                'formDescription' => __('Register for the event.', 'rrze-formular'),
                'fields' => [
                    [
                        'id' => 'personal_heading',
                        'type' => 'heading',
                        'label' => __('Personal details', 'rrze-formular'),
                        'step' => 1,
                    ],
                    [
                        'id' => 'firstname',
                        'type' => 'text',
                        'label' => __('First name', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                    [
                        'id' => 'lastname',
                        'type' => 'text',
                        'label' => __('Last name', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                    [
                        'id' => 'email',
                        'type' => 'email',
                        'label' => __('E-mail address', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                    [
                        'id' => 'event_heading',
                        'type' => 'heading',
                        'label' => __('Event details', 'rrze-formular'),
                        'step' => 2,
                    ],
                    [
                        'id' => 'attendance',
                        'type' => 'radio',
                        'label' => __('Participation', 'rrze-formular'),
                        'required' => true,
                        'step' => 2,
                        'options' => [
                            ['value' => 'in_person', 'label' => __('In person', 'rrze-formular')],
                            ['value' => 'online', 'label' => __('Online', 'rrze-formular')],
                        ],
                    ],
                    [
                        'id' => 'diet',
                        'type' => 'select',
                        'label' => __('Dietary requirements', 'rrze-formular'),
                        'required' => false,
                        'step' => 2,
                        'options' => [
                            ['value' => 'none', 'label' => __('None', 'rrze-formular')],
                            ['value' => 'vegetarian', 'label' => __('Vegetarian', 'rrze-formular')],
                            ['value' => 'vegan', 'label' => __('Vegan', 'rrze-formular')],
                        ],
                    ],
                ],
            ],
            'support' => [
                'label' => __('Support request', 'rrze-formular'),
                'formTitle' => __('Support request', 'rrze-formular'),
                'formDescription' => __('Describe your issue and we will get back to you.', 'rrze-formular'),
                'fields' => [
                    [
                        'id' => 'name',
                        'type' => 'text',
                        'label' => __('Name', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                    [
                        'id' => 'email',
                        'type' => 'email',
                        'label' => __('E-mail address', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                    [
                        'id' => 'category',
                        'type' => 'select',
                        'label' => __('Category', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                        'options' => [
                            ['value' => 'technical', 'label' => __('Technical issue', 'rrze-formular')],
                            ['value' => 'content', 'label' => __('Content issue', 'rrze-formular')],
                            ['value' => 'other', 'label' => __('Other', 'rrze-formular')],
                        ],
                    ],
                    [
                        'id' => 'description',
                        'type' => 'textarea',
                        'label' => __('Description', 'rrze-formular'),
                        'required' => true,
                        'step' => 1,
                    ],
                ],
            ],
        ];

        return apply_filters('rrze_formwizard_templates', $templates);
    }

    public static function get(string $key): ?array
    {
        $all = self::all();
        return $all[$key] ?? null;
    }
}
