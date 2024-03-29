<?php
/***************************************************************
* Copyright notice
*
* (c) 2009-2013 Niels Pardon (mail@niels-pardon.de)
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
 * Test case.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class tx_seminars_BagBuilder_OrganizerTest extends tx_phpunit_testcase {
	/**
	 * @var tx_seminars_BagBuilder_Organizer
	 */
	private $fixture;
	/**
	 * @var tx_oelib_testingFramework
	 */
	private $testingFramework;

	protected function setUp() {
		$this->testingFramework = new tx_oelib_testingFramework('tx_seminars');

		$this->fixture = new tx_seminars_BagBuilder_Organizer();
		$this->fixture->setTestMode();
	}

	protected function tearDown() {
		$this->testingFramework->cleanUp();
	}


	///////////////////////////////////////////
	// Tests for the basic builder functions.
	///////////////////////////////////////////

	public function testBuilderBuildsABag() {
		$this->assertTrue(
			$this->fixture->build() instanceof tx_seminars_Bag_Abstract
		);
	}


	/////////////////////////////
	// Tests for limitToEvent()
	/////////////////////////////

	public function testLimitToEventWithNegativeEventUidThrowsException() {
		$this->setExpectedException(
			'InvalidArgumentException',
			'The parameter $eventUid must be > 0.'
		);

		$this->fixture->limitToEvent(-1);
	}

	public function testLimitToEventWithZeroEventUidThrowsException() {
		$this->setExpectedException(
			'InvalidArgumentException',
			'The parameter $eventUid must be > 0.'
		);

		$this->fixture->limitToEvent(0);
	}

	public function testLimitToEventFindsOneOrganizerOfEvent() {
		$organizerUid = $this->testingFramework->createRecord(
			'tx_seminars_organizers'
		);
		$eventUid = $this->testingFramework->createRecord(
			'tx_seminars_seminars',
			array('organizers' => 1)
		);
		$this->testingFramework->createRelation(
			'tx_seminars_seminars_organizers_mm',
			$eventUid,
			$organizerUid
		);

		$this->fixture->limitToEvent($eventUid);
		$bag = $this->fixture->build();

		$this->assertEquals(
			1,
			$bag->countWithoutLimit()
		);
	}

	public function testLimitToEventFindsTwoOrganizersOfEvent() {
		$eventUid = $this->testingFramework->createRecord(
			'tx_seminars_seminars',
			array('organizers' => 2)
		);
		$organizerUid1 = $this->testingFramework->createRecord(
			'tx_seminars_organizers'
		);
		$this->testingFramework->createRelation(
			'tx_seminars_seminars_organizers_mm',
			$eventUid,
			$organizerUid1
		);
		$organizerUid2 = $this->testingFramework->createRecord(
			'tx_seminars_organizers'
		);
		$this->testingFramework->createRelation(
			'tx_seminars_seminars_organizers_mm',
			$eventUid,
			$organizerUid2
		);

		$this->fixture->limitToEvent($eventUid);
		$bag = $this->fixture->build();

		$this->assertEquals(
			2,
			$bag->countWithoutLimit()
		);
	}

	public function testLimitToEventIgnoresOrganizerOfOtherEvent() {
		$eventUid1 = $this->testingFramework->createRecord(
			'tx_seminars_seminars',
			array('organizers' => 1)
		);
		$organizerUid = $this->testingFramework->createRecord(
			'tx_seminars_organizers'
		);
		$this->testingFramework->createRelation(
			'tx_seminars_seminars_organizers_mm',
			$eventUid1,
			$organizerUid
		);
		$eventUid2 = $this->testingFramework->createRecord(
			'tx_seminars_seminars'
		);

		$this->fixture->limitToEvent($eventUid2);
		$bag = $this->fixture->build();

		$this->assertTrue(
			$bag->isEmpty()
		);
	}

	/**
	 * @test
	 */
	public function limitToEventSortsByRelationSorting() {
		$eventUid = $this->testingFramework->createRecord(
			'tx_seminars_seminars',
			array('organizers' => 2)
		);
		$organizerUid1 = $this->testingFramework->createRecord(
			'tx_seminars_organizers'
		);
		$organizerUid2 = $this->testingFramework->createRecord(
			'tx_seminars_organizers'
		);

		$this->testingFramework->createRelation(
			'tx_seminars_seminars_organizers_mm',
			$eventUid,
			$organizerUid2
		);
		$this->testingFramework->createRelation(
			'tx_seminars_seminars_organizers_mm',
			$eventUid,
			$organizerUid1
		);

		$this->fixture->limitToEvent($eventUid);
		$bag = $this->fixture->build();
		$bag->rewind();

		$this->assertEquals(
			$organizerUid2,
			$bag->current()->getUid()
		);
	}
}