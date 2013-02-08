<?php
/***************************************************************
* Copyright notice
*
* (c) 2010-2013 Niels Pardon (mail@niels-pardon.de)
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

require_once(PATH_typo3 . 'classes/class.typo3ajax.php');

/**
 * Testcase for the tx_seminars_BackEndExtJs_Ajax_Dispatcher class in the "seminars"
 * extension.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_BackEndExtJs_Ajax_DispatcherTest extends tx_phpunit_testcase {
	/**
	 * @var tx_seminars_BackEndExtJs_Ajax_Dispatcher
	 */
	private $fixture;

	public function setUp() {
		$this->fixture = new tx_seminars_BackEndExtJs_Ajax_Dispatcher();
	}

	public function tearDown() {
		unset($this->fixture);
	}


	/**
	 * @test
	 */
	public function dispatchSetsContentFormatToJson() {
		$ajaxObject = $this->getMock(
			'TYPO3AJAX',
			array('setContentFormat')
		);
		$ajaxObject->expects($this->atLeastOnce())
			->method('setContentFormat')
			->with('json');

		$this->fixture->dispatch(array(), $ajaxObject);
	}

	/**
	 * @test
	 */
	public function dispatchWithoutAjaxIdSetsResponseContentToSuccessFalse() {
		$ajaxObject = new TYPO3AJAX('');

		$this->fixture->dispatch(array(), $ajaxObject);

		$this->assertEquals(
			array('success' => FALSE),
			$ajaxObject->getContent()
		);
	}
}
?>