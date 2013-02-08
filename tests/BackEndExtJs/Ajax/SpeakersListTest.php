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

/**
 * Testcase for the tx_seminars_BackEndExtJs_Ajax_SpeakersList class in the
 * "seminars" extension.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_BackEndExtJs_Ajax_SpeakersListTest extends tx_phpunit_testcase {
	/**
	 * @var tx_seminars_tests_fixtures_BackEndExtJs_Ajax_TestingSpeakersList
	 */
	private $fixture;

	/**
	 * back-up of $GLOBALS['BE_USER']
	 *
	 * @var t3lib_beUserAuth
	 */
	private $backEndUserBackUp;

	public function setUp() {
		$this->fixture = new tx_seminars_tests_fixtures_BackEndExtJs_Ajax_TestingSpeakersList();

		$this->backEndUserBackUp = $GLOBALS['BE_USER'];
	}

	public function tearDown() {
		$GLOBALS['BE_USER'] = $this->backEndUserBackUp;
		tx_oelib_MapperRegistry::purgeInstance();
		unset($this->fixture, $this->backEndUserBackUp);
	}


	/**
	 * @test
	 */
	public function mapperNameIsSetToEventsMapper() {
		$this->assertEquals(
			'tx_seminars_Mapper_Speaker',
			$this->fixture->getMapperName()
		);
	}


	///////////////////////////////////////////
	// Tests regarding getAsArray().
	///////////////////////////////////////////

	/**
	 * @test
	 */
	public function getAsArrayReturnsArrayContainingSpeakerUid() {
		$speaker = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Speaker')
			->getLoadedTestingModel(array());

		$result = $this->fixture->getAsArray($speaker);

		$this->assertEquals(
			$speaker->getUid(),
			$result['uid']
		);
	}

	/**
	 * @test
	 */
	public function getAsArrayReturnsArrayContainingSpeakerPageUid() {
		$speaker = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Speaker')
			->getLoadedTestingModel(array('pid' => 42));

		$result = $this->fixture->getAsArray($speaker);

		$this->assertEquals(
			42,
			$result['pid']
		);
	}

	/**
	 * @test
	 */
	public function getAsArrayReturnsArrayContainingHtmlspecialcharedSpeakerName() {
		$speaker = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Speaker')
			->getLoadedTestingModel(array('title' => 'Ben & Jerry'));

		$result = $this->fixture->getAsArray($speaker);

		$this->assertSame(
			'Ben &amp; Jerry',
			$result['title']
		);
	}


	///////////////////////////////////////////
	// Tests regarding isAllowedToListView().
	///////////////////////////////////////////

	/**
	 * @test
	 */
	public function hasAccessWithBackEndUserIsAllowedReturnsTrue() {
		$GLOBALS['BE_USER'] = $this->getMock(
			't3lib_beUserAuth',
			array('check')
		);
		$GLOBALS['BE_USER']->expects($this->once())
			->method('check')
			->with('tables_select', 'tx_seminars_speakers')
			->will($this->returnValue(TRUE));

		$this->assertTrue(
			$this->fixture->hasAccess()
		);
	}

	/**
	 * @test
	 */
	public function hasAccessWithBackEndUserIsNotAllowedReturnsFalse() {
		$GLOBALS['BE_USER'] = $this->getMock(
			't3lib_beUserAuth',
			array('check')
		);
		$GLOBALS['BE_USER']->expects($this->once())
			->method('check')
			->with('tables_select', 'tx_seminars_speakers')
			->will($this->returnValue(FALSE));

		$this->assertFalse(
			$this->fixture->hasAccess()
		);
	}
}
?>