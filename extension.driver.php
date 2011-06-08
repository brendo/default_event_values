<?php

	Class extension_default_event_values extends Extension{

		public function about(){
			return array(
				'name' => 'Default Event Values',
				'version' => '0.5',
				'release-date' => '2011-06-08',
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
					if($field == 'custom_value') {
						$max = count($_POST['default_event_values']['custom_value']);

						for($i = 1; $i <= $max; $i++) {
							$default_values .= self::addCustomDefaultValue($_POST['default_event_values']['custom_value'][$i]);
						}
					}
					else {
						$default_values .= self::addDefaultValue($field, $dv);
					}
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

			// Loop over the Default Values, setting them in $_POST or `$context['fields']`
			// as appropriate.
			foreach($context['event']->eDefaultValues as $field => $dv) {
				$value = $datasource->__processParametersInString($dv['value'], $env);

				// Custom field, this will set $_POST instead of the `$context['fields']`
				// as `$context['fields']` only contains things inside $_POST['fields']
				if($dv['custom'] == 'yes') {
					$matches = preg_split('/\[/U', $field);
					foreach($matches as $key => $match) {
						$matches[$key] = trim($match, ']');
					}

					if(count($matches) == 1) {
						self::setArrayValue($_POST, $field, $value, ($dv['override'] == 'yes'));
					}
					// We'll need to build out the relevant $_POST array
					else {
						$tree = self::addKey($matches, $value);

						// If the DV is an override, set it regardless
						// DV is not an override, so only set if it hasn't already been set
						if(($dv['override'] == 'no') && !self::checkArrayForTree($_POST, $tree)) {
							$_POST = array_merge_recursive($_POST, $tree);
						}
						else if($dv['override'] == 'yes') {
							$_POST = array_replace_recursive($_POST, $tree);
						}
					}

					continue;
				}

				self::setArrayValue($context['fields'], $field, $value, ($dv['override'] == 'yes'));
			}
		}

	/*-------------------------------------------------------------------------
		Helpers:
	-------------------------------------------------------------------------*/

		/**
		 * Given a flat array, build this out to be an associative array setting
		 * the last key to the `$value`.
		 *
		 * @param array $array
		 * @param string $value
		 * @return array
		 */
		private static function addKey(&$array, $value = null) {
			return ($key = array_pop($array))
				? self::addKey($array, array($key => $value))
				: $value;
		}

		/**
		 * Given an array, this function will set the `$value` at the `$key`.
		 * If `$override` is set to the true, the value will be set regardless,
		 * if not it will only be set if the key doesn't already exist
		 *
		 * @param array $array
		 * @param string $key
		 * @param string $value
		 * @param boolean $override
		 */
		private static function setArrayValue(&$array, $key, $value, $override) {
			if($override || !isset($array[$key])) {
				$array[$key] = $value;
			}
		}

		/**
		 * Given one `$array` structure, this functions checks to see if the `$tree`
		 * structure exists in the `$array` returning boolean
		 *
		 * @param array $array
		 * @param array $tree
		 * @return boolean
		 *  True if the structure does exist, false otherwise
		 */
		private static function checkArrayForTree(&$array, $tree) {
			// If either parameter is now not an array, return true
			// as the structure exists
			if(!is_array($array) || !is_array($tree)) {
				return true;
			}

			// Get keys in the tree
			foreach(array_keys($tree) as $key) {
				// Check to see if the key exists in $array
				if(in_array($key, array_keys($array))) {
					// If it exists, move down the tree and check the next one
					return self::checkArrayForTree($array[$key], $tree[$key]);
				}
				// If it doesn't, return false
				return false;
			}
		}

		private static function addCustomDefaultValue($custom) {
			return self::addDefaultValue($custom['key'], array(
				'value' => $custom['value'],
				'override' => $custom['override'],
				'custom' => 'yes'
			));
		}

		private static function addDefaultValue($name, $value) {
			return sprintf('
			"%s" => array(
				%s
				%s
				%s
			),',
				$name,
				isset($value['value']) ? "'value' => '" . $value['value'] . "'," : null,
				isset($value['override']) ? "'override' => '" . $value['override'] . "',"  : null,
				isset($value['custom']) ? "'custom' => '" . $value['custom'] . "'"	: null
			);
		}

	/*-------------------------------------------------------------------------
		Event Editor:
	-------------------------------------------------------------------------*/

		private function injectFields(XMLElement &$form, array $callback) {
			// skip when creating new events
			if ($callback['context'][0] == 'new') return $this->injectDefault($form);

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

		private function injectDefault(XMLElement &$form) {
			// Create the Default Values fieldset
			$fieldset = new XMLElement('fieldset', null, array('class' => 'settings'));
			$fieldset->appendChild(
				new XMLElement('legend', __('Default Values'))
			);

			$div = new XMLElement('div', null);
			$div->appendChild(
				new XMLElement('p', __('Default values can be set for this event after it has been created.'), array('class' => 'label'))
			);

			$fieldset->appendChild($div);
			$form->prependChild($fieldset);
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

			$custom_default_values = $event->eDefaultValues;

			// Loop over this event's section's fields
			foreach($section->fetchFields() as $field) {
				// Remove this from the `custom_default_values` array
				unset($custom_default_values[$field->get('element_name')]);

				// Add template
				$this->createDuplicatorTemplate($ol, $field->get('label'), $field->get('element_name'));

				// Create real instance with real data
				if(isset($event->eDefaultValues[$field->get('element_name')])) {
					$filter = $event->eDefaultValues[$field->get('element_name')];
					$this->createDuplicatorTemplate($ol, $field->get('label'), $field->get('element_name'), $filter);
				}
			}

			$this->createCustomValueDuplicatorTemplate($ol);

			if(is_array($custom_default_values)) {
				$custom_default_values = array_filter($custom_default_values);
				if(!empty($custom_default_values)) {
					foreach($custom_default_values as $name => $values) {
						$this->createCustomValueDuplicatorTemplate($ol, $name, $values);
					}
				}
			}

			$div->appendChild($ol);
			$fieldset->appendChild($div);
			$form->prependChild($fieldset);
		}

	/*-------------------------------------------------------------------------
		Duplicator Utilities
	-------------------------------------------------------------------------*/

		private function createDuplicatorTemplate(XMLElement $wrapper, $label, $name, array $values = null) {
			// Create duplicator template
			$li = new XMLElement('li');
			$li->setAttribute('data-type', $name);
			$li->appendChild(new XMLElement('h4', $label));

			if(is_null($values)) {
				$li->setAttribute('class', 'unique template');
			}
			else {
				$li->setAttribute('class', 'unique');
			}

			// Value
			$xLabel = Widget::Label(__('Value'));
			$xLabel->appendChild(
				Widget::Input('default_event_values['.$name.'][value]', !is_null($values) ? $values['value'] : null)
			);
			$li->appendChild($xLabel);

			// Will this value override?
			$li->appendChild(
				Widget::Input('default_event_values['.$name.'][override]', 'no', 'hidden')
			);
			$input = Widget::Input('default_event_values['.$name.'][override]', 'yes', 'checkbox');
			if(isset($values['override']) && $values['override'] == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$xLabel = Widget::Label(
				__('%s Value will override any value posted from the frontend', array($input->generate()))
			);
			$li->appendChild($xLabel);

			// Add to the wrapper
			$wrapper->appendChild($li);
		}

		private function createCustomValueDuplicatorTemplate(XMLElement $wrapper, $name = 'Custom', array $values = null) {
			// Create duplicator template
			$li = new XMLElement('li');
			$li->appendChild(new XMLElement('h4', $name));

			if(is_null($values)) {
				$li->setAttribute('class', 'template');
			}

			$group = new XMLElement('div', null, array('class' => 'group'));

			// Column One
			$col = new XMLElement('div');

			// Custom Key
			$xLabel = Widget::Label(__('Key'));
			$xLabel->appendChild(
				Widget::Input('default_event_values[custom_value][-1][key]', ($name !== 'Custom') ? $name : null)
			);
			$col->appendChild($xLabel);
			$group->appendChild($col);

			// Column Two
			$col = new XMLElement('div');

			// Value
			$xLabel = Widget::Label(__('Value'));
			$xLabel->appendChild(
				Widget::Input('default_event_values[custom_value][-1][value]', !is_null($values) ? $values['value'] : null)
			);
			$col->appendChild($xLabel);

			// Will this value override?
			$li->appendChild(
				Widget::Input('default_event_values[custom_value][-1][override]', 'no', 'hidden')
			);
			$input = Widget::Input('default_event_values[custom_value][-1][override]', 'yes', 'checkbox');
			if(isset($values['override']) && $values['override'] == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$xLabel = Widget::Label(
				__('%s Value will override any value posted from the frontend', array($input->generate()))
			);
			$col->appendChild($xLabel);

			$group->appendChild($col);
			$li->appendChild($group);

			// Add to the wrapper
			$wrapper->appendChild($li);
		}

	}

	/**
	 * One hundred thousand man hugs and thanks to Gregor for this function
	 * @link http://www.php.net/manual/en/function.array-replace-recursive.php#92574
	 */
	if(!function_exists('array_replace_recursive')) {
		function array_replace_recursive($array, $array1) {
			function recurse($array, $array1) {
				foreach ($array1 as $key => $value) {
					// create new key in $array, if it is empty or not an array
					if (!isset($array[$key]) || (isset($array[$key]) && !is_array($array[$key]))) {
						$array[$key] = array();
					}

					// overwrite the value in the base array
					if (is_array($value)) {
						$value = recurse($array[$key], $value);
					}
					$array[$key] = $value;
				}
				return $array;
			}

			// handle the arguments, merge one by one
			$args = func_get_args();
			$array = $args[0];
			if (!is_array($array)) {
			  return $array;
			}
			for ($i = 1; $i < count($args); $i++) {
				if (is_array($args[$i])) {
					$array = recurse($array, $args[$i]);
				}
			}

			return $array;
		}
	}
