<?php

	Class extension_default_event_values extends Extension{

		public function about(){
			return array(
				'name' => 'Default Event Values',
				'version' => '0.3',
				'release-date' => '2011-06-05',
				'author' => array(
					'name' => 'Brendan Abbott',
					'website' => 'http://bloodbone.ws',
					'email' => 'brendan@bloodbone.ws'
				)
			);
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/backend/',
					'delegate' => 'AdminPagePreGenerate',
					'callback' => 'AdminPagePreGenerate'
				),
				array(
					'page' => '/blueprints/events/',
					'delegate' => 'EventPreCreate',
					'callback' => 'saveEssentials'
				),
				array(
					'page' => '/blueprints/events/',
					'delegate' => 'EventPreEdit',
					'callback' => 'saveEssentials'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPreSaveFilter',
					'callback' => 'setDefaultValues'
				),
			);
		}

	/*-------------------------------------------------------------------------
		Delegate Callbacks:
	-------------------------------------------------------------------------*/

		/**
		 * @uses AdminPagePreGenerate
		 */
		public function AdminPagePreGenerate(&$context) {
			if($context['oPage'] instanceof contentBlueprintsEvents) {
				$callback = Administration::instance()->getPageCallback();

				if(in_array($callback['context'][0], array('edit', 'new'))) {
					$page = $context['oPage'];

					// Get the children of the Contents wrapper
					$xChildren = $page->Contents->getChildren();

					// Create a new instance of Contents
					$page->Contents = new XMLElement('div', null, array('id' => 'contents'));
					// Append the heading to Contents
					$page->Contents->appendChild(
						array_shift($xChildren)
					);

					// Pop off the current <form> array and get it's children
					$form = array_shift($xChildren);
					$formChildren = $form->getChildren();
					$formAttributes = $form->getAttributes();

					// Pop off the essentials fieldset and save it for later
					$essentials = array_shift($formChildren);

					// Create a new form element and append the result of the children to it
					$form = new XMLElement('form');
					$form->appendChildArray($formChildren);
					$form->setAttributeArray($formAttributes);

					// Inject our custom goodness, using `prependChild` so that it's
					// at the start of the form
					$this->injectFields($form, $callback);

					// Add the essentials fieldset to the start of the form
					$form->prependChild($essentials);

					// Append the form back to our Contents page
					$page->Contents->appendChild($form);
				}
			}
		}

		/**
		 * @uses EventPreEdit
		 * @uses EventPostEdit
		 */
		public function saveEssentials(&$context) {
			$default_values = 'public $eDefaultValues = array(';

			if(is_array($_POST['default_event_values'])) {
				foreach($_POST['default_event_values'] as $field => $dv) {
					$default_values .= sprintf('
					"%s" => array(
						%s
						%s
					),',
						$field,
						isset($dv['value']) ? "'value' => '" . $dv['value'] . "'," : null,
						isset($dv['override']) ? "'override' => '" . $dv['override'] . "'"  : null
					);
				}
			}

			$default_values .= PHP_EOL . str_repeat("\t", 2) . ');';
			$default_values .= PHP_EOL . PHP_EOL . str_repeat("\t", 2) . 'public $eParamFILTERS';

			$context['contents'] = preg_replace('/public \$eParamFILTERS/i', $default_values, $context['contents']);
		}

		/**
		 * @uses EventPreSaveFilter
		 */
		public function setDefaultValues(&$context) {
			if(!isset($context['event']->eDefaultValues) || !is_array($context['event']->eDefaultValues)) return;

			// Create a Datasource class, which has the logic for finding Parameters
			// and turning them into the values.
			$datasource = new Datasource(Frontend::instance(), null, false);

			// Fake an environment to find Parameters in
			$env = array(
				'env' => Frontend::instance()->Page()->Env(),
				'param' => Frontend::instance()->Page()->_param
			);

			// Loop over the Default Values, setting them in the $_POST data
			// as appropriate
			foreach($context['event']->eDefaultValues as $field => $dv) {
				$value = $datasource->__processParametersInString($dv['value'], $env);

				// If the DV is an override, set it regardless
				if($dv['override'] = 'yes') {
					$context['fields'][$field] = $value;
				}

				// DV is not an override, so only set if it hasn't already been set
				else if(!isset($context['fields'][$field])) {
					$context['fields'][$field] = $value;
				}
			}
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		private function injectFields(XMLElement &$form, array $callback) {
			$eventManager = new EventManager(Symphony::Engine());
			$event = $eventManager->create($callback['context'][1]);
			$event_source = null;

			if(method_exists($event, 'getSource')) {
				$event_source = $event->getSource();
			}

			// This isn't a typical event, so return
			if(!is_numeric($event_source)) return null;

			$sectionManager = new SectionManager(Symphony::Engine());
			$section = $sectionManager->fetch($event_source);

			// For whatever reason, the Section doesn't exist anymore
			if(!$section instanceof Section) return null;

			$this->injectDefaultValues($form, $event, $section);
		}

		private function injectDefaultValues(XMLElement &$form, Event $event, Section $section) {
			// Create the Default Values fieldset
			$fieldset = new XMLElement('fieldset', null, array('class' => 'settings'));
			$fieldset->appendChild(
				new XMLElement('legend', __('Default Values'))
			);
			$fieldset->appendChild(
				new XMLElement('p', __('Use Default Values to set field values without having them in your Frontend markup. Use <code>{$param}</code> syntax to use page parameters.'), array(
					'class' => 'help'
				))
			);

			$div = new XMLElement('div', null);
			$div->appendChild(
				new XMLElement('p', __('Add Default Value'), array('class' => 'label'))
			);

			// Create Duplicators
			$ol = new XMLElement('ol');
			$ol->setAttribute('class', 'filters-duplicator');

			// Loop over this event's section's fields
			foreach($section->fetchFields() as $field) {
				// Create duplicator template
				$li = new XMLElement('li');
				$li->setAttribute('class', 'unique template');
				$li->setAttribute('data-type', $field->get('element_name'));
				$li->appendChild(new XMLElement('h4', $field->get('label')));

				// Value
				$label = Widget::Label(__('Value'));
				$label->appendChild(
					Widget::Input('default_event_values['.$field->get('element_name').'][value]')
				);
				$li->appendChild($label);

				// Will this value override?
				$li->appendChild(
					Widget::Input('default_event_values['.$field->get('element_name').'][override]', 'no', 'hidden')
				);
				$input = Widget::Input('default_event_values['.$field->get('element_name').'][override]', 'yes', 'checkbox');
				$label = Widget::Label(
					__('%s Value will override any value posted from the frontend', array($input->generate()))
				);

				$li->appendChild($label);

				$ol->appendChild($li);

				// Create real instance with real data
				if(isset($event->eDefaultValues[$field->get('element_name')])) {
					$filter = $event->eDefaultValues[$field->get('element_name')];

					$li = new XMLElement('li');
					$li->setAttribute('class', 'unique');
					$li->setAttribute('data-type', $field->get('element_name'));
					$li->appendChild(new XMLElement('h4', $field->get('label')));

					// Value
					$label = Widget::Label(__('Value'));
					$label->appendChild(
						Widget::Input('default_event_values['.$field->get('element_name').'][value]', $filter['value'])
					);
					$li->appendChild($label);

					// Will this value override?
					$li->appendChild(
						Widget::Input('default_event_values['.$field->get('element_name').'][override]', 'no', 'hidden')
					);
					$input = Widget::Input('default_event_values['.$field->get('element_name').'][override]', 'yes', 'checkbox');
					if(isset($filter['override']) && $filter['override'] == 'yes') $input->setAttribute('checked', 'checked');
					$label = Widget::Label(
						__('%s Value will override any value posted from the frontend', array($input->generate()))
					);

					$li->appendChild($label);

					$ol->appendChild($li);
				}
			}

			$div->appendChild($ol);
			$fieldset->appendChild($div);
			$form->prependChild($fieldset);
		}
	}
