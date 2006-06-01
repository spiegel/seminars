<?php
/***************************************************************
* Copyright notice
*
* (c) 2005-2006 Oliver Klee (typo3-coding@oliverklee.de)
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Plugin 'Seminar Manager' for the 'seminars' extension.
 *
 * @author	Oliver Klee <typo3-coding@oliverklee.de>
 */

require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_templatehelper.php');
require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_registration.php');
require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_registrationbag.php');
require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_registrationmanager.php');
require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_seminar.php');
require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_seminarbag.php');
require_once(t3lib_extMgm::extPath('frontendformslib').'class.tx_frontendformslib.php');

class tx_seminars_pi1 extends tx_seminars_templatehelper {
	/** Same as class name */
	var $prefixId = 'tx_seminars_pi1';
	/**  Path to this script relative to the extension dir. */
	var $scriptRelPath = 'pi1/class.tx_seminars_pi1.php';

	/** The seminar which we want to list/show or for which the user wants to register. */
	var $seminar;

	/** an instance of registration manager which we want to have around only once (for performance reasons) */
	var $registrationManager;

	/**
	 * Displays the seminar manager HTML.
	 *
	 * @param	string		default content string, ignore
	 * @param	array		TypoScript configuration for the plugin
	 *
	 * @return	string		HTML for the plugin
	 *
	 * @access	public
	 */
	function main($content, $conf) {
		$this->init($conf);
		$this->pi_initPIflexForm();

		$this->getTemplateCode();
		$this->setLabels();
		$this->setCSS();

		// include CSS in header of page
		if ($this->hasConfValueString('cssFile', 's_template_special')) {
			$GLOBALS['TSFE']->additionalHeaderData[] = '<style type="text/css">@import "'.$this->getConfValueString('cssFile', 's_template_special', true).'";</style>';
		}

		/** Name of the registrationManager class in case someone subclasses it. */
		$registrationManagerClassname = t3lib_div::makeInstanceClassName('tx_seminars_registrationmanager');
		$this->registrationManager =& new $registrationManagerClassname();

		$result = '';
		$showOnlyMyEvents = false;

		switch ($this->getConfValueString('what_to_display')) {
			case 'seminar_registration':
				$result = $this->createRegistrationPage();
				break;
			case 'list_registrations':
				$result = $this->createRegistrationsListPage();
				break;
			case 'my_events':
				if ($this->registrationManager->isLoggedIn()) {
					$showOnlyMyEvents = true;
				}
				// The fallthrough is intended.
			case 'seminar_list':
			default:
				// Show the single view if a 'showUid' variable is set.
				if ($this->piVars['showUid']) {
					$result = $this->createSingleView();
				} else {
					$result = $this->createListView($showOnlyMyEvents);
				}
				break;
		}

		return $this->pi_wrapInBaseClass($result);
	}

	/**
	 * Returns the additional query parameters needed to build the list view. This function checks
	 * - the timeframe to display
	 * - whether to show canceled events
	 * The result always starts with " AND" so that it can be directly appended
	 * to a WHERE clause.
	 *
	 * @return	string		the additional query parameters
	 *
	 * @access	protected
	 */
	function getAdditionalQueryParameters() {
		$result = '';
		$now = $GLOBALS['SIM_EXEC_TIME'];
		/** Prefix the column name with the table name so that the query also works with multiple tables. */
		$tablePrefix = $this->tableSeminars.'.';

		// Work out from which timeframe we'll display the event list.
		// We also need to deal with the case that an event has no end date set
		// (ie. it is open-ended).
		switch ($this->getConfValueString('timeframeInList', 's_template_special')) {
			case 'past':
				// As past events, show the following:
				// 1. Generally, only events that have a begin date set, AND:
				// 2. If the event has an end date, does it lie in the past?, OR
				// 2. If the event has *no* end date, does the *begin* date lie in the past?
				$result .= ' AND '.$tablePrefix.'begin_date!=0 AND (('.$tablePrefix.'end_date!=0 AND '.$tablePrefix.'end_date<='.$now.') OR ('.$tablePrefix.'end_date=0 AND '.$tablePrefix.'begin_date<='.$now.'))';
				break;
			case 'pastAndCurrent':
				// As past and current events, show the following:
				// 1. Generally, only events that have a begin date set, AND
				// 2. the begin date lies in the past.
				// (So events without a begin date won't be listed here.)
				$result .= ' AND '.$tablePrefix.'begin_date!=0 AND '.$tablePrefix.'begin_date<='.$now;
				break;
			case 'current':
				// As current events, show the following:
				// 1. Events that have both a begin and end date, AND
				// 2. The begin date lies in the past, AND
				// 3. The end date lies in the future.
				$result .= ' AND '.$tablePrefix.'begin_date!=0 AND '.$tablePrefix.'begin_date<='.$now.' AND '.$tablePrefix.'end_date!=0 AND '.$tablePrefix.'end_date>'.$now;
				break;
			case 'currentAndUpcoming':
				// As current and upcoming events, show the following:
				// 1. Events with an existing end date in the future, OR
				// 2. Events without an end date, but with an existing begin date in the future
				//    (open-ended events that have not started yet), OR
				// 3. Events that have no (begin) date set yet.
				$result .= ' AND (('.$tablePrefix.'end_date!=0 AND '.$tablePrefix.'end_date>'.$now.') OR ('.$tablePrefix.'end_date=0 AND '.$tablePrefix.'begin_date>'.$now.') OR (begin_date=0))';
				break;
			case 'upcoming':
				// As upcoming events, show the following:
				// 1. Events with an existing begin date in the future
				//    (events that have not started yet), OR
				// 3. Events that have no (begin) date set yet.
				$result .= ' AND ('.$tablePrefix.'begin_date>'.$now.' OR '.$tablePrefix.'begin_date=0)';
				break;
			case 'deadlineNotOver':
				// As events for which the registration deadline is not over yet,
				// show the following:
				// 1. Events that have a deadline set that lies in the future, OR
				// 2. Events that have *no* deadline set, but
				//    with an existing begin date in the future
				//    (events that have not started yet), OR
				// 3. Events that have no (begin) date set yet.
				$result .= ' AND (('.$tablePrefix.'deadline_registration!=0 AND '.$tablePrefix.'deadline_registration>'.$now.') OR ('.$tablePrefix.'deadline_registration=0 AND ('.$tablePrefix.'begin_date>'.$now.' OR '.$tablePrefix.'begin_date=0)))';
				break;
			case 'all':
			default:
				// To show all events, we don't need any additional parameters.
				break;
		}

		// Check if canceled events should be hidden.
		if ($this->getConfValueBoolean('hideCanceledEvents', 's_template_special')) {
			$result .= ' AND '.$tablePrefix.'cancelled=0';
		}

		return $result;
	}

	/**
	 * Creates the HTML for the event list view.
	 * This function is used for the normal event list as well as the
	 * "my events" list.
	 *
	 * @param	boolean		whether to limit the list view to events
	 * 						for which the logged-in FE user already is registered
	 *
	 * @return	string		HTML code with the event list
	 *
	 * @access	protected
	 */
	function createListView($showOnlyMyEvents = false) {
		$result = '';
		if ($showOnlyMyEvents) {
			$result = $this->substituteMarkerArrayCached('MESSAGE_MY_EVENTS');
		}

		$seminarBag =& $this->initListView($showOnlyMyEvents);

		if ($this->internal['res_count']) {
			$result .= $this->createListHeader();
			$rowCounter = 0;
			while ($this->seminar =& $seminarBag->getCurrent()) {
				$result .= $this->createListRow($rowCounter);
				$rowCounter++;
				$seminarBag->getNext();
			}
			$result .= $this->createListFooter();
		} else {
			$this->setMarkerContent('error_text', $this->pi_getLL('message_noResults'));
			$result .= $this->substituteMarkerArrayCached('ERROR_VIEW');
		}
		// We display the search box even if the list view does not contain any elements.
		$result .= $this->pi_list_searchBox();

		return $result;
	}

	/**
	 * Initializes the list view and creates a seminar bag,
	 * but does not create any actual HTML output.
	 *
	 * @param	boolean		whether to limit the list view to events
	 * 						for which the logged-in FE user already is registered
	 *
	 * @return	object		a seminar bag containing the seminars for the list view
	 *
	 * @access	protected
	 */
	function &initListView($showOnlyMyEvents = false) {
		if (strstr($this->cObj->currentRecord, 'tt_content')) {
			$this->conf['pidList'] = $this->getConfValueString('pages');
			$this->conf['recursive'] = $this->getConfValueInteger('recursive');
		}

		$this->readSubpartsToHide($this->getConfValueString('hideColumns', 's_template_special'), 'LISTHEADER_WRAPPER');
		$this->readSubpartsToHide($this->getConfValueString('hideColumns', 's_template_special'), 'LISTITEM_WRAPPER');

		// Hide the registration column if no user is logged in
		// or if the "my events" list should be displayed.
		if (!$this->registrationManager->isLoggedIn() || $showOnlyMyEvents) {
			$this->readSubpartsToHide('registration', 'LISTHEADER_WRAPPER');
			$this->readSubpartsToHide('registration', 'LISTITEM_WRAPPER');
		}

		if (!isset($this->piVars['pointer'])) {
			$this->piVars['pointer'] = 0;
		}

		// Read the list view settings from the TS setup and write them to the list view configuration.
		$lConf = $this->conf['listView.'];
		foreach($lConf as $key => $value) {
			$this->internal[$key] = $value;
		}

		// Overwrite the default sort order with values given by the browser.
		// This happens if the user changes the sort order manually.
		if (!empty($this->piVars['sort'])) {
			list($this->internal['orderBy'], $this->internal['descFlag']) = explode(':', $this->piVars['sort']);
		}

		// Number of results to show in a listing.
		$this->internal['results_at_a_time'] = t3lib_div::intInRange($lConf['results_at_a_time'], 0, 1000, 20);
		// The maximum number of 'pages' in the browse-box: 'Page 1', 'Page 2', etc.
		$this->internal['maxPages'] = t3lib_div::intInRange($lConf['maxPages'], 0, 1000, 2);

		$this->internal['searchFieldList'] = 'title,subtitle,description,accreditation_number';
		$this->internal['orderByList'] = 'title,uid,accreditation_number,credit_points,begin_date,price_regular,price_special,organizers';

		$pidList = $this->pi_getPidList($this->getConfValueString('pidList'), $this->getConfValueInteger('recursive'));
		$queryWhere = $this->tableSeminars.'.pid IN ('.$pidList.')'
			.$this->getAdditionalQueryParameters();
		$additionalTables = '';
		if ($showOnlyMyEvents) {
			$additionalTables = $this->tableAttendances;
			$queryWhere .= ' AND '.$this->tableSeminars.'.uid='.$this->tableAttendances.'.seminar'
				.' AND '.$this->tableAttendances.'.user='.$this->registrationManager->getFeUserUid();
		}

		$orderBy = '';
		if ($this->internal['orderBy']) {
			if (t3lib_div::inList($this->internal['orderByList'], $this->internal['orderBy'])) {
				$orderBy = $this->tableSeminars.'.'.$this->internal['orderBy'].($this->internal['descFlag'] ? ' DESC' : '');
			}
		}

		$limit = '';
		$pointer = intval($this->piVars['pointer']);
		$resultsAtATime = t3lib_div::intInRange($this->internal['results_at_a_time'], 1, 1000);
		$limit = ($pointer * $resultsAtATime).','.$resultsAtATime;

		if ($this->piVars['sword'] && $this->internal['searchFieldList']) {
			$queryWhere .= $this->cObj->searchWhere($this->piVars['sword'],  $this->internal['searchFieldList'], $this->tableSeminars);
		}

		$seminarBagClassname = t3lib_div::makeInstanceClassName('tx_seminars_seminarbag');
		$seminarBag =& new $seminarBagClassname(
			$queryWhere,
			$additionalTables,
			'',
			$orderBy,
			$limit
		);

		$this->internal['res_count'] = $seminarBag->getObjectCountWithoutLimit();

		return $seminarBag;
	}

	/**
	 * Displays detailed data for a seminar.
	 * Fields listed in $this->subpartsToHide are hidden (ie. not displayed).
	 *
	 * @return	string		HTML for the plugin
	 *
	 * @access	protected
	 */
	function createSingleView() {
		$this->internal['currentTable'] = $this->tableSeminars;
		$this->internal['currentRow'] = $this->pi_getRecord($this->tableSeminars, $this->piVars['showUid']);

		$this->readSubpartsToHide($this->getConfValueString('hideFields', 's_template_special'), 'FIELD_WRAPPER');

		if ($this->createSeminar($this->internal['currentRow']['uid'])) {
			// This sets the title of the page for use in indexed search results:
			$GLOBALS['TSFE']->indexedDocTitle = $this->seminar->getTitle();

			$this->setMarkerContent('type', $this->seminar->getType());
			$this->setMarkerContent('title', $this->seminar->getTitle());
			$this->setMarkerContent('uid', $this->seminar->getUid());

			if ($this->seminar->hasSubtitle()) {
				$this->setMarkerContent('subtitle', $this->seminar->getSubtitle());
			} else {
				$this->readSubpartsToHide('subtitle', 'field_wrapper');
			}

			if ($this->seminar->hasDescription()) {
				$this->setMarkerContent('description', $this->seminar->getDescription($this));
			} else {
				$this->readSubpartsToHide('description', 'field_wrapper');
			}

			if ($this->seminar->hasAccreditationNumber()) {
				$this->setMarkerContent('accreditation_number', $this->seminar->getAccreditationNumber());
			} else {
				$this->readSubpartsToHide('accreditation_number', 'field_wrapper');
			}

			if ($this->seminar->hasCreditPoints()) {
				$this->setMarkerContent('credit_points', $this->seminar->getCreditPoints());
			} else {
				$this->readSubpartsToHide('credit_points', 'field_wrapper');
			}

			$this->setMarkerContent('date', $this->seminar->getDate());
			$this->setMarkerContent('time', $this->seminar->getTime());

			if ($this->getConfValueBoolean('showSiteDetails', 's_template_special')) {
				$this->setMarkerContent('place', $this->seminar->getPlaceWithDetails($this));
			} else {
				$this->setMarkerContent('place', $this->seminar->getPlaceShort());
			}

			if ($this->seminar->hasRoom()) {
				$this->setMarkerContent('room', $this->seminar->getRoom());
			} else {
				$this->readSubpartsToHide('room', 'field_wrapper');
			}

			if ($this->seminar->hasSpeakers()) {
				if ($this->getConfValueBoolean('showSpeakerDetails', 's_template_special')) {
					$this->setMarkerContent('speakers', $this->seminar->getSpeakersWithDescription($this));
				} else {
					$this->setMarkerContent('speakers', $this->seminar->getSpeakersShort());
				}
			} else {
				$this->readSubpartsToHide('speakers', 'field_wrapper');
			}

			if ($this->getConfValueBoolean('generalPriceInSingle', 's_template_special')) {
				$this->setMarkerContent('label_price_regular', $this->pi_getLL('label_price_general'));
			}
			$this->setMarkerContent('price_regular', $this->seminar->getPriceRegular());

			if ($this->seminar->hasPriceSpecial()) {
				$this->setMarkerContent('price_special', $this->seminar->getPriceSpecial());
			} else {
				$this->readSubpartsToHide('price_special', 'field_wrapper');
			}

			if ($this->seminar->hasPaymentMethods()) {
				$this->setMarkerContent('paymentmethods', $this->seminar->getPaymentMethods($this));
			} else {
				$this->readSubpartsToHide('paymentmethods', 'field_wrapper');
			}

			$this->setMarkerContent('organizers', $this->seminar->getOrganizers($this));

			if ($this->seminar->needsRegistration() && !$this->seminar->isCanceled()) {
				$this->setMarkerContent('vacancies', $this->seminar->getVacanciesString());
			} else {
				$this->readSubpartsToHide('vacancies', 'field_wrapper');
			}

			if ($this->seminar->hasRegistrationDeadline()) {
				$this->setMarkerContent('deadline_registration', $this->seminar->getRegistrationDeadline());
			} else {
				$this->readSubpartsToHide('deadline_registration', 'field_wrapper');
			}

			$this->setMarkerContent('registration',
				$this->registrationManager->canRegisterIfLoggedIn($this->seminar) ?
					$this->registrationManager->getLinkToRegistrationOrLoginPage($this, $this->seminar) :
					$this->registrationManager->canRegisterIfLoggedInMessage($this->seminar)
			);

			if ($this->seminar->needsRegistration()
				&& $this->registrationManager->isLoggedIn()
				&& $this->hasConfValueInteger('registrationsListPID')) {
				$link = $this->cObj->getTypoLink(
					$this->pi_getLL('label_listRegistrationsLink'),
					$this->getConfValueInteger('registrationsListPID'),
					array('tx_seminars_pi1[seminar]' => $this->seminar->getUid())
				);
				$this->setMarkerContent('list_registrations', $link);
			} else {
				$this->readSubpartsToHide('list_registrations', 'field_wrapper');
			}

			$this->setMarkerContent('backlink', $this->pi_list_linkSingle($this->pi_getLL('label_back', 'Back'), 0));

			$result = $this->substituteMarkerArrayCached('SINGLE_VIEW');
		} else {
			$this->setMarkerContent('error_text', $this->pi_getLL('message_wrongSeminarNumber'));
			$result = $this->substituteMarkerArrayCached('ERROR_VIEW');
			header('Status: 404 Not Found');
		}

		return $result;
	}

	/**
	 * Creates a seminar in $this->seminar.
	 * $this->registrationManager must have been initialized before this method may be called.
	 *
	 * @param	int			a seminar UID
	 *
	 * @return	boolean		true if the seminar UID is valid and the object has been created, false otherwise
	 *
	 * @access	protected
	 */
	function createSeminar($seminarUid) {
		$result = false;

		if (tx_seminars_seminar::existsSeminar($seminarUid)) {
			/** Name of the seminar class in case someone subclasses it. */
			$seminarClassname = t3lib_div::makeInstanceClassName('tx_seminars_seminar');
			$this->seminar =& new $seminarClassname($seminarUid);
			$result = true;
		}

		return $result;
	}

	/**
	 * Returns the list view header: Start of table, header row, start of table body.
	 * Columns listed in $this->subpartsToHide are hidden (ie. not displayed).
	 *
	 * @return	string		HTML output, the table header
	 *
	 * @access	protected
	 */
	function createListHeader() {
		$this->setMarkerContent('header_title', $this->getFieldHeader_sortLink('title'));
		$this->setMarkerContent('header_uid', $this->getFieldHeader_sortLink('uid'));
		$this->setMarkerContent('header_accreditation_number', $this->getFieldHeader_sortLink('accreditation_number'));
		$this->setMarkerContent('header_credit_points', $this->getFieldHeader_sortLink('credit_points'));
		$this->setMarkerContent('header_speakers', $this->getFieldHeader('speakers'));
		$this->setMarkerContent('header_date', $this->getFieldHeader_sortLink('date'));
		$this->setMarkerContent('header_price_regular', $this->getFieldHeader_sortLink('price_regular'));
		$this->setMarkerContent('header_price_special', $this->getFieldHeader_sortLink('price_special'));
		$this->setMarkerContent('header_organizers', $this->getFieldHeader_sortLink('organizers'));
		$this->setMarkerContent('header_vacancies', $this->getFieldHeader('vacancies'));
		$this->setMarkerContent('header_registration', $this->getFieldHeader('registration'));

		return $this->substituteMarkerArrayCached('LIST_HEADER');
	}

	/**
	 * Returns the list view footer: end of table body, end of table,
	 * result browser.
	 * Columns listed in $this->subpartsToHide are hidden (ie. not displayed).
	 *
	 * @return	string		HTML output, the table header
	 *
	 * @access	protected
	 */
	function createListFooter() {
		$result = $this->substituteMarkerArrayCached('LIST_FOOTER');
		$result .= $this->pi_list_browseresults();

		return $result;
	}

	/**
	 * Returns a list row as a TR. Gets data from $this->seminar.
	 * Columns listed in $this->subpartsToHide are hidden (ie. not displayed).
	 * If $this->seminar is invalid, an empty string is returned.
	 *
	 * @param	integer		Row counter. Starts at 0 (zero). Used for alternating class values in the output rows.
	 *
	 * @return	string		HTML output, a table row with a class attribute set (alternative based on odd/even rows)
	 *
	 * @access	protected
	 */
	function createListRow($rowCounter = 0) {
		$result = '';

		if ($this->seminar->isOk()) {
			$rowClass = ($rowCounter % 2) ? 'listrow-odd' : '';
			$canceledClass = ($this->seminar->isCanceled()) ? $this->pi_getClassName('canceled') : '';
			// If we have two classes, we need a space as a separator.
			$classSeparator = (!empty($rowClass) && !empty($canceledClass)) ? ' ' : '';
			// Only use the class construct if we actually have a class.
			$completeClass = (!empty($rowClass) || !empty($canceledClass)) ?
				' class="'.$rowClass.$classSeparator.$canceledClass.'"' :
				'';

			$this->setMarkerContent('class_itemrow', $completeClass);

			$this->setMarkerContent('title_link', $this->seminar->getLinkedTitle($this));
			$this->setMarkerContent('uid', $this->seminar->getUid($this));
			$this->setMarkerContent('accreditation_number', $this->seminar->getAccreditationNumber());
			$this->setMarkerContent('credit_points', $this->seminar->getCreditPoints());
			$this->setMarkerContent('speakers', $this->seminar->getSpeakersShort());
			$this->setMarkerContent('date', $this->seminar->getDate());
			$this->setMarkerContent('price_regular', $this->seminar->getPriceRegular());
			$this->setMarkerContent('price_special', $this->seminar->getPriceSpecial());
			$this->setMarkerContent('organizers', $this->seminar->getOrganizers($this));
			$this->setMarkerContent('vacancies', $this->seminar->getVacanciesString());
			$this->setMarkerContent('class_listvacancies', $this->getVacanciesClasses($this->seminar));
			$this->setMarkerContent('registration', $this->registrationManager->canRegisterIfLoggedIn($this->seminar) ?
				$this->registrationManager->getLinkToRegistrationOrLoginPage($this, $this->seminar) : ''
			);

			$result = $this->substituteMarkerArrayCached('LIST_ITEM');
		}

		return $result;
	}

	/**
	 * Gets the heading for a field type.
	 *
	 * @param	string		key of the field type for which the heading should be retrieved.
	 *
	 * @return	string		the heading
	 *
	 * @access	protected
	 */
	function getFieldHeader($fN) {
		$result = '';

		switch($fN) {
		case 'title':
			$result = $this->pi_getLL('label_title', '<em>title</em>');
			break;
		case 'price_regular':
			if ($this->getConfValueBoolean('generalPriceInList', 's_template_special')) {
				$fN = 'price_general';
			}
			// fall-through is intended here
		default:
			$result = $this->pi_getLL('label_'.$fN, '['.$fN.']');
			break;
		}

		return $result;
	}

	/**
	 * Gets the heading for a field type, wrapped in a hyperlink that sorts by that column.
	 *
	 * @param	string		key of the field type for which the heading should be retrieved.
	 *
	 * @return	string		the heading completely wrapped in a hyperlink
	 *
	 * @access	protected
	 */
	function getFieldHeader_sortLink($fN) {
		$sortField = $fN;
		switch($fN) {
			case 'date':
				$sortField = 'begin_date';
				break;
			default:
				break;
		}
		return $this->pi_linkTP_keepPIvars($this->getFieldHeader($fN), array('sort' => $sortField.':'.($this->internal['descFlag'] ? 0 : 1)));
	}

	/**
	 * Gets the CSS classes (space-separated) for the Vacancies TD.
	 *
	 * @param	object		the current Seminar object
	 *
	 * @return	string		class attribute filled with a list a space-separated CSS classes, plus a leading space
	 *
	 * @access	protected
	 */
	function getVacanciesClasses(&$seminar) {
		$result = $this->pi_getClassName('vacancies');

		if ($seminar->needsRegistration()) {
			if ($seminar->hasVacancies()) {
				$result .= ' '.$this->pi_getClassName('vacancies-available').' '
					.$this->pi_getClassName('vacancies-'.$seminar->getVacancies());
			} else {
				$result .= ' '.$this->pi_getClassName('vacancies-0');
			}
		}

		return ' class="'.$result.'"';
	}

	/**
	 * Creates the HTML for the registration page.
	 *
	 * @return	string		HTML code for the registration page
	 *
	 * @acces	protected
	 */
	function createRegistrationPage() {
		$this->feuser = $GLOBALS['TSFE']->fe_user;

		$errorMessage = '';
		$registrationForm = '';
		$registationThankyou = '';
		$isOkay = false;

		if ($this->createSeminar($this->piVars['seminar'])) {
			if (!$this->registrationManager->canRegisterIfLoggedIn($this->seminar)) {
				$errorMessage = $this->registrationManager->canRegisterIfLoggedInMessage($this->seminar);
			} else {
				if ($this->registrationManager->isLoggedIn()) {
					$isOkay = true;
				} else {
					$errorMessage = $this->registrationManager->getLinkToRegistrationOrLoginPage($this, $this->seminar);
				}
			}
		} else {
			$errorMessage = $this->registrationManager->existsSeminarMessage($this->piVars['seminar']);
		}

		if ($isOkay) {
			// create the frontend form object
			$formsLibClassName = t3lib_div::makeInstanceClassName('tx_frontendformslib');
			$formObj = new $formsLibClassName($this);

			// generate configuration for a single step
			$formObj->steps[1] = $formObj->createStepConf($this->getConfValueString('showRegistrationFields', 's_template_special'), $this->tableAttendances, $this->pi_getLL('label_registrationForm'), '<p>'.$this->pi_getLL('message_registrationForm').'</p>');
			$formObj->init();

			if ($formObj->submitType == 'submit') {
				if ($this->registrationManager->canCreateRegistration(
						$this->seminar,
						$formObj->sessionData['data'][$this->tableAttendances])) {
					$this->registrationManager->createRegistration($this->seminar, $formObj->sessionData['data'][$this->tableAttendances], $this);
					$registationThankyou = $this->substituteMarkerArrayCached('REGISTRATION_THANKYOU');

					// destroy session data for our submitted form
					$formObj->destroySessionData();
				} else {
					$errorMessage = $this->registrationManager->canCreateRegistrationMessage(
						$this->seminar,
						$formObj->sessionData['data'][$this->tableAttendances]);
					$registrationForm = $this->createRegistrationForm($formObj);
				}
			} else {
				$registrationForm = $this->createRegistrationForm($formObj);
			}
		}

		$result = $this->createRegistrationHeading($errorMessage);
		$result .= $registrationForm;
		$result .= $registationThankyou;

		return $result;
	}

	/**
	 * Creates the registration page title and (if applicable) any error messages.
	 *
	 * @param	string	error message to be displayed (may be empty if there is no error)
	 *
	 * @return	string	HTML code including the title and error message
	 *
	 * @access	protected
	 */
	function createRegistrationHeading($errorMessage) {
		$this->setMarkerContent('registration', $this->pi_getLL('label_registration'));
		$this->setMarkerContent('title',        ($this->seminar) ? $this->seminar->getTitleAndDate() : '');

		if (empty($errorMessage)) {
			$this->readSubpartsToHide('error', 'wrapper');
		} else {
			$this->setMarkerContent('error_text', $errorMessage);
		}

		return $this->substituteMarkerArrayCached('REGISTRATION_HEAD');
	}

	/**
	 * Creates the registration form.
	 *
	 * @param	object		a frontendformslib object
	 *
	 * @return	string		HTML code for the form
	 *
	 * @access	protected
	 */
	function createRegistrationForm(&$formObj) {
		if ($this->getConfValueBoolean('generalPriceInSingle', 's_template_special')) {
			$this->setMarkerContent('label_price_regular', $this->pi_getLL('label_price_general'));
		}
		$this->setMarkerContent('price_regular', $this->seminar->getPriceRegular());
		if ($this->seminar->hasPriceSpecial()) {
			$this->setMarkerContent('price_special', $this->seminar->getPriceSpecial());
		} else {
			$this->readSubpartsToHide('price_special', 'registration_wrapper');
		}
		$this->setMarkerContent('vacancies', $this->seminar->getVacancies());
		$output = $this->substituteMarkerArrayCached('REGISTRATION_DETAILS');
		// Form has not yet been submitted, so render the form:
		$output .= $formObj->renderWholeForm();
		$output .= $this->substituteMarkerArrayCached('REGISTRATION_BOTTOM');

		return $output;
	}

	/**
	 * Creates a list of registered participants for an event.
	 * If there are no registrations yet, a localized message is displayed instead.
	 *
	 * @return	string		HTML code for the list
	 *
	 * @access	protected
	 */
	function createRegistrationsListPage() {
		if ($this->createSeminar($this->piVars['seminar'])) {
			$this->setMarkerContent('title', $this->seminar->getTitleAndDate());
			$this->readSubpartsToHide('error', 'wrapper');
			$this->createRegistrationsList();
		} else {
			$this->setMarkerContent('title', '');
			$this->setMarkerContent('error_text', $this->registrationManager->existsSeminarMessage($this->piVars['seminar']));
			// hide stuff we don't need
			$this->readSubpartsToHide('registrations_list_message', 'wrapper');
			$this->readSubpartsToHide('registrations_list_body', 'wrapper');

			header('Status: 404 Not Found');
		}

		$result = $this->substituteMarkerArrayCached('REGISTRATIONS_LIST_VIEW');

		return $result;
	}

	/**
	 * Creates the registration list (sorted by creation date) and fills in the
	 * corresponding subparts.
	 * If there are no registrations, a localized message is filled in instead.
	 *
	 * Before this function can be called, it must be ensured that $this->seminar
	 * is a valid seminar object.
	 *
	 * @access	protected
	 */
	function createRegistrationsList() {
		$registrationBagClassname = t3lib_div::makeInstanceClassName('tx_seminars_registrationbag');
		$registrationBag =& new $registrationBagClassname($this->tableAttendances.'.seminar='.$this->seminar->getUid(), '', '', 'crdate');

		if ($registrationBag->getCurrent()) {
			$result = '';
			while ($currentRegistration =& $registrationBag->getCurrent()) {
				$this->setMarkerContent('registrations_list_inneritem', $currentRegistration->getUserName());
				$result .= $this->substituteMarkerArrayCached('REGISTRATIONS_LIST_ITEM');
				$registrationBag->getNext();
			}
			$this->readSubpartsToHide('registrations_list_message', 'wrapper');
			$this->setMarkerContent('registrations_list_body', $result);
		} else {
			$this->readSubpartsToHide('registrations_list_body', 'wrapper');
			$this->setMarkerContent('message_no_registrations', $this->pi_getLL('message_noRegistrations'));
		}

		return;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/pi1/class.tx_seminars_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/pi1/class.tx_seminars_pi1.php']);
}

?>
