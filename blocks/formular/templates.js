import { __ } from '@wordpress/i18n';

const field = ( id, type, label, options = {} ) => ( {
	id,
	type,
	label,
	required: options.required ?? false,
	placeholder: options.placeholder ?? '',
	options: options.options ?? [],
} );

const heading = ( id, label ) => field( id, 'heading', label );

const personalFields = ( withPhone = false ) => {
	const fields = [
		heading( 'personal_heading', __( 'Personal details', 'rrze-formular' ) ),
		field( 'firstname', 'text', __( 'First name', 'rrze-formular' ), {
			required: true,
		} ),
		field( 'lastname', 'text', __( 'Last name', 'rrze-formular' ), {
			required: true,
		} ),
		field( 'email', 'email', __( 'E-mail address', 'rrze-formular' ), {
			required: true,
		} ),
	];

	if ( withPhone ) {
		fields.push(
			field( 'phone', 'tel', __( 'Telephone number', 'rrze-formular' ) )
		);
	}

	return fields;
};

const studentFields = () => [
	field( 'study_program', 'text', __( 'Study programme', 'rrze-formular' ) ),
	field( 'matriculation', 'text', __( 'Matriculation number', 'rrze-formular' ), {
		placeholder: __( 'If applicable', 'rrze-formular' ),
	} ),
];

export function getTemplates() {
	return [
	{
		label: __( 'Blank form', 'rrze-formular' ),
		value: 'blank',
		fields: [],
		formTitle: '',
		formDescription: '',
	},
	{
		label: __( 'Contact · General enquiry', 'rrze-formular' ),
		value: 'contact',
		formTitle: __( 'Contact', 'rrze-formular' ),
		formDescription: __(
			'Send us a message. We will reply as soon as possible.',
			'rrze-formular'
		),
		fields: [
			...personalFields( true ),
			field( 'organisation', 'text', __( 'Organisation / unit', 'rrze-formular' ), {
								placeholder: __( 'e.g. chair, office, institution', 'rrze-formular' ),
			} ),
			field( 'subject', 'text', __( 'Subject', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'message', 'textarea', __( 'Message', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Contact · Callback request', 'rrze-formular' ),
		value: 'callback',
		formTitle: __( 'Callback request', 'rrze-formular' ),
		formDescription: __(
			'Leave your number and we will call you back.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			field( 'phone', 'tel', __( 'Telephone number', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'preferred_time', 'select', __( 'Preferred time', 'rrze-formular' ), {
								options: [
					{ value: 'morning', label: __( 'Morning (8–12)', 'rrze-formular' ) },
					{ value: 'afternoon', label: __( 'Afternoon (12–17)', 'rrze-formular' ) },
					{ value: 'flexible', label: __( 'Flexible', 'rrze-formular' ) },
				],
			} ),
			field( 'topic', 'textarea', __( 'Topic of the call', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Contact · Website feedback', 'rrze-formular' ),
		value: 'feedback',
		formTitle: __( 'Website feedback', 'rrze-formular' ),
		formDescription: __(
			'Help us improve this website.',
			'rrze-formular'
		),
		fields: [
			field( 'page_url', 'text', __( 'Page URL', 'rrze-formular' ), {
								placeholder: 'https://',
			} ),
			field( 'rating', 'select', __( 'Overall rating', 'rrze-formular' ), {
				required: true,
								options: [
					{ value: '5', label: __( 'Excellent', 'rrze-formular' ) },
					{ value: '4', label: __( 'Good', 'rrze-formular' ) },
					{ value: '3', label: __( 'Average', 'rrze-formular' ) },
					{ value: '2', label: __( 'Poor', 'rrze-formular' ) },
					{ value: '1', label: __( 'Very poor', 'rrze-formular' ) },
				],
			} ),
			field( 'comment', 'textarea', __( 'Your feedback', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'email', 'email', __( 'E-mail address (optional)', 'rrze-formular' ), {
							} ),
		],
	},
	{
		label: __( 'Contact · Website or technical issue', 'rrze-formular' ),
		value: 'website_issue',
		formTitle: __( 'Report an issue', 'rrze-formular' ),
		formDescription: __(
			'Report a technical problem or an error on this website.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			field( 'page_url', 'text', __( 'Affected page URL', 'rrze-formular' ), {
				required: true,
								placeholder: 'https://',
			} ),
			field( 'category', 'select', __( 'Issue type', 'rrze-formular' ), {
				required: true,
								options: [
					{ value: 'broken_link', label: __( 'Broken link', 'rrze-formular' ) },
					{ value: 'display', label: __( 'Display or layout', 'rrze-formular' ) },
					{ value: 'accessibility', label: __( 'Accessibility', 'rrze-formular' ) },
					{ value: 'content', label: __( 'Outdated or incorrect content', 'rrze-formular' ) },
					{ value: 'other', label: __( 'Other', 'rrze-formular' ) },
				],
			} ),
			field( 'description', 'textarea', __( 'Description of the issue', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Contact · Press enquiry', 'rrze-formular' ),
		value: 'press',
		formTitle: __( 'Press enquiry', 'rrze-formular' ),
		formDescription: __(
			'Contact our press office or communications team.',
			'rrze-formular'
		),
		fields: [
			field( 'media_outlet', 'text', __( 'Media outlet', 'rrze-formular' ), {
				required: true,
							} ),
			...personalFields( true ),
			field( 'deadline', 'text', __( 'Deadline', 'rrze-formular' ), {
								placeholder: __( 'e.g. date and time', 'rrze-formular' ),
			} ),
			field( 'topic', 'textarea', __( 'Enquiry', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Teaching · Office hours appointment', 'rrze-formular' ),
		value: 'office_hours',
		formTitle: __( 'Office hours appointment', 'rrze-formular' ),
		formDescription: __(
			'Request an appointment during office hours.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			...studentFields(),
			field( 'preferred_date', 'text', __( 'Preferred date', 'rrze-formular' ), {
				required: true,
								placeholder: __( 'e.g. DD.MM.YYYY', 'rrze-formular' ),
			} ),
			field( 'topic', 'textarea', __( 'Topic / reason for appointment', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Teaching · Bachelor thesis enquiry', 'rrze-formular' ),
		value: 'thesis_bachelor',
		formTitle: __( 'Bachelor thesis enquiry', 'rrze-formular' ),
		formDescription: __(
			'Express your interest in writing a bachelor thesis with us.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			...studentFields(),
			field( 'study_semester', 'number', __( 'Current semester', 'rrze-formular' ), {
							} ),
			field( 'topic_interest', 'textarea', __( 'Topic ideas or areas of interest', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'start_date', 'text', __( 'Preferred start date', 'rrze-formular' ), {
							} ),
		],
	},
	{
		label: __( 'Teaching · Master thesis enquiry', 'rrze-formular' ),
		value: 'thesis_master',
		formTitle: __( 'Master thesis enquiry', 'rrze-formular' ),
		formDescription: __(
			'Express your interest in writing a master thesis with us.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			...studentFields(),
			field( 'topic_interest', 'textarea', __( 'Topic ideas or research area', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'supervisor', 'text', __( 'Preferred supervisor', 'rrze-formular' ), {
							} ),
			field( 'start_date', 'text', __( 'Preferred start date', 'rrze-formular' ), {
							} ),
		],
	},
	{
		label: __( 'Teaching · Student assistant application', 'rrze-formular' ),
		value: 'hiwi',
		formTitle: __( 'Student assistant application', 'rrze-formular' ),
		formDescription: __(
			'Apply for a student assistant (HiWi) position.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			...studentFields(),
			field( 'skills', 'textarea', __( 'Skills and experience', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'hours', 'select', __( 'Desired weekly hours', 'rrze-formular' ), {
								options: [
					{ value: '5', label: __( 'Up to 5 hours', 'rrze-formular' ) },
					{ value: '10', label: __( 'Up to 10 hours', 'rrze-formular' ) },
					{ value: '15', label: __( 'Up to 15 hours', 'rrze-formular' ) },
					{ value: 'flexible', label: __( 'Flexible', 'rrze-formular' ) },
				],
			} ),
			field( 'availability', 'text', __( 'Available from', 'rrze-formular' ), {
							} ),
			field( 'motivation', 'textarea', __( 'Motivation', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Teaching · Internship application', 'rrze-formular' ),
		value: 'internship',
		formTitle: __( 'Internship application', 'rrze-formular' ),
		formDescription: __(
			'Apply for an internship with our team.',
			'rrze-formular'
		),
		fields: [
			...personalFields( true ),
			...studentFields(),
			field( 'period', 'text', __( 'Desired internship period', 'rrze-formular' ), {
				required: true,
								placeholder: __( 'e.g. 01.03.–31.08.', 'rrze-formular' ),
			} ),
			field( 'motivation', 'textarea', __( 'Motivation and expectations', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Events · Event registration', 'rrze-formular' ),
		value: 'event',
		formTitle: __( 'Event registration', 'rrze-formular' ),
		formDescription: __( 'Register for the event.', 'rrze-formular' ),
		fields: [
			...personalFields(),
			heading( 'event_heading', __( 'Event details', 'rrze-formular' ) ),
			field( 'attendance', 'radio', __( 'Participation', 'rrze-formular' ), {
				required: true,
								options: [
					{ value: 'in_person', label: __( 'In person', 'rrze-formular' ) },
					{ value: 'online', label: __( 'Online', 'rrze-formular' ) },
				],
			} ),
			field( 'diet', 'select', __( 'Dietary requirements', 'rrze-formular' ), {
								options: [
					{ value: 'none', label: __( 'None', 'rrze-formular' ) },
					{ value: 'vegetarian', label: __( 'Vegetarian', 'rrze-formular' ) },
					{ value: 'vegan', label: __( 'Vegan', 'rrze-formular' ) },
				],
			} ),
			field( 'accessibility', 'textarea', __( 'Accessibility requirements', 'rrze-formular' ), {
								placeholder: __( 'If applicable', 'rrze-formular' ),
			} ),
		],
	},
	{
		label: __( 'Events · Workshop registration', 'rrze-formular' ),
		value: 'workshop',
		formTitle: __( 'Workshop registration', 'rrze-formular' ),
		formDescription: __(
			'Register for the workshop.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			...studentFields(),
			field( 'experience', 'select', __( 'Prior knowledge', 'rrze-formular' ), {
								options: [
					{ value: 'none', label: __( 'No prior knowledge', 'rrze-formular' ) },
					{ value: 'basic', label: __( 'Basic', 'rrze-formular' ) },
					{ value: 'advanced', label: __( 'Advanced', 'rrze-formular' ) },
				],
			} ),
			field( 'expectations', 'textarea', __( 'Expectations', 'rrze-formular' ), {
							} ),
		],
	},
	{
		label: __( 'Events · Abstract submission', 'rrze-formular' ),
		value: 'abstract',
		formTitle: __( 'Abstract submission', 'rrze-formular' ),
		formDescription: __(
			'Submit an abstract for the conference or symposium.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			field( 'affiliation', 'text', __( 'Affiliation', 'rrze-formular' ), {
				required: true,
								placeholder: __( 'e.g. chair, institute, company', 'rrze-formular' ),
			} ),
			field( 'title', 'text', __( 'Paper title', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'abstract', 'textarea', __( 'Abstract', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'keywords', 'text', __( 'Keywords', 'rrze-formular' ), {
							} ),
			field( 'presentation', 'radio', __( 'Preferred format', 'rrze-formular' ), {
								options: [
					{ value: 'talk', label: __( 'Talk', 'rrze-formular' ) },
					{ value: 'poster', label: __( 'Poster', 'rrze-formular' ) },
					{ value: 'either', label: __( 'Either', 'rrze-formular' ) },
				],
			} ),
		],
	},
	{
		label: __( 'Research · Study participant registration', 'rrze-formular' ),
		value: 'subject_participation',
		formTitle: __( 'Study participant registration', 'rrze-formular' ),
		formDescription: __(
			'Register as a participant in our research study.',
			'rrze-formular'
		),
		fields: [
			...personalFields( true ),
			field( 'age', 'number', __( 'Age', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'availability', 'textarea', __( 'Availability', 'rrze-formular' ), {
				required: true,
								placeholder: __( 'Days and times you are available', 'rrze-formular' ),
			} ),
			field(
				'consent',
				'checkbox',
				__( 'I confirm that I have read the participant information.', 'rrze-formular' ),
				{ required: true }
			),
		],
	},
	{
		label: __( 'Research · Lab or equipment reservation', 'rrze-formular' ),
		value: 'lab_reservation',
		formTitle: __( 'Lab or equipment reservation', 'rrze-formular' ),
		formDescription: __(
			'Request a lab space or research equipment.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			field( 'resource', 'text', __( 'Lab / equipment', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'date', 'text', __( 'Date', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'time', 'text', __( 'Time slot', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'purpose', 'textarea', __( 'Purpose of use', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Research · Cooperation enquiry', 'rrze-formular' ),
		value: 'cooperation',
		formTitle: __( 'Cooperation enquiry', 'rrze-formular' ),
		formDescription: __(
			'Propose a research or industry cooperation.',
			'rrze-formular'
		),
		fields: [
			field( 'organisation', 'text', __( 'Organisation / company', 'rrze-formular' ), {
				required: true,
							} ),
			...personalFields( true ),
			field( 'cooperation_type', 'select', __( 'Type of cooperation', 'rrze-formular' ), {
				required: true,
								options: [
					{ value: 'research', label: __( 'Joint research project', 'rrze-formular' ) },
					{ value: 'industry', label: __( 'Industry partnership', 'rrze-formular' ) },
					{ value: 'thesis', label: __( 'Thesis with external partner', 'rrze-formular' ) },
					{ value: 'guest_lecture', label: __( 'Guest lecture', 'rrze-formular' ) },
					{ value: 'other', label: __( 'Other', 'rrze-formular' ) },
				],
			} ),
			field( 'description', 'textarea', __( 'Project description', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'IT · Support request', 'rrze-formular' ),
		value: 'it_support',
		formTitle: __( 'IT support request', 'rrze-formular' ),
		formDescription: __(
			'Describe your IT problem. For account issues, please include your IdM user name.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			field( 'idm_user', 'text', __( 'IdM user name', 'rrze-formular' ), {
								placeholder: __( 'If applicable', 'rrze-formular' ),
			} ),
			field( 'category', 'select', __( 'Category', 'rrze-formular' ), {
				required: true,
								options: [
					{ value: 'account', label: __( 'Account / login', 'rrze-formular' ) },
					{ value: 'email', label: __( 'E-mail', 'rrze-formular' ) },
					{ value: 'network', label: __( 'Network / VPN', 'rrze-formular' ) },
					{ value: 'software', label: __( 'Software', 'rrze-formular' ) },
					{ value: 'hardware', label: __( 'Hardware', 'rrze-formular' ) },
					{ value: 'website', label: __( 'Website / CMS', 'rrze-formular' ) },
					{ value: 'other', label: __( 'Other', 'rrze-formular' ) },
				],
			} ),
			field( 'description', 'textarea', __( 'Problem description', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Infrastructure · Room booking request', 'rrze-formular' ),
		value: 'room_booking',
		formTitle: __( 'Room booking request', 'rrze-formular' ),
		formDescription: __(
			'Request a room or meeting space.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			field( 'organisation', 'text', __( 'Organisation / unit', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'room_type', 'select', __( 'Room type', 'rrze-formular' ), {
								options: [
					{ value: 'meeting', label: __( 'Meeting room', 'rrze-formular' ) },
					{ value: 'seminar', label: __( 'Seminar room', 'rrze-formular' ) },
					{ value: 'lab', label: __( 'Laboratory', 'rrze-formular' ) },
					{ value: 'other', label: __( 'Other', 'rrze-formular' ) },
				],
			} ),
			field( 'date', 'text', __( 'Date', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'time', 'text', __( 'Time', 'rrze-formular' ), {
				required: true,
							} ),
			field( 'participants', 'number', __( 'Number of participants', 'rrze-formular' ), {
							} ),
			field( 'purpose', 'textarea', __( 'Purpose', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	{
		label: __( 'Public relations · Newsletter signup', 'rrze-formular' ),
		value: 'newsletter',
		formTitle: __( 'Newsletter signup', 'rrze-formular' ),
		formDescription: __(
			'Subscribe to our newsletter.',
			'rrze-formular'
		),
		fields: [
			field( 'firstname', 'text', __( 'First name', 'rrze-formular' ), {
							} ),
			field( 'lastname', 'text', __( 'Last name', 'rrze-formular' ), {
							} ),
			field( 'email', 'email', __( 'E-mail address', 'rrze-formular' ), {
				required: true,
							} ),
			field(
				'consent',
				'checkbox',
				__( 'I agree to receive the newsletter and have read the privacy notice.', 'rrze-formular' ),
				{ required: true }
			),
		],
	},
	{
		label: __( 'Public relations · Alumni contact', 'rrze-formular' ),
		value: 'alumni',
		formTitle: __( 'Alumni contact', 'rrze-formular' ),
		formDescription: __(
			'Get in touch with our alumni network.',
			'rrze-formular'
		),
		fields: [
			...personalFields(),
			field( 'graduation_year', 'number', __( 'Year of graduation', 'rrze-formular' ), {
							} ),
			field( 'study_program', 'text', __( 'Study programme', 'rrze-formular' ), {
							} ),
			field( 'message', 'textarea', __( 'Message', 'rrze-formular' ), {
				required: true,
							} ),
		],
	},
	];
}
