<?php
/***************************************************************
* Copyright notice
*
* (c) 2007-2008 Niels Pardon (mail@niels-pardon.de)
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
 * Testcase for the registration class in the 'seminars' extensions.
 *
 * @package		TYPO3
 * @subpackage	tx_seminars
 *
 * @author		Niels Pardon <mail@niels-pardon.de>
 */

require_once(t3lib_extMgm::extPath('seminars').'tests/fixtures/class.tx_seminars_registrationchild.php');

require_once(t3lib_extMgm::extPath('oelib').'class.tx_oelib_testingFramework.php');

class tx_seminars_registrationchild_testcase extends tx_phpunit_testcase {
	private $fixture;
	private $testingFramework;

	/** the UID of a seminar to which the fixture relates */
	private $seminarUid;

	protected function setUp() {
		$this->testingFramework
			= new tx_oelib_testingFramework('tx_seminars');

		$this->seminarUid = $this->testingFramework->createRecord(
			SEMINARS_TABLE_SEMINARS
		);

		$registrationUid = $this->testingFramework->createRecord(
			SEMINARS_TABLE_ATTENDANCES,
			array('seminar' => $this->seminarUid)
		);

		$this->fixture = new tx_seminars_registrationchild($registrationUid);
	}

	protected function tearDown() {
		$this->testingFramework->cleanUp();
		unset($this->fixture);
		unset($this->testingFramework);
	}

	public function testIsOk() {
		$this->assertTrue(
			$this->fixture->isOk()
		);
	}


	///////////////////////
	// Utility functions.
	///////////////////////

	/**
	 * Inserts a payment method record into the database and creates a relation
	 * to it from the fixture.
	 *
	 * @param	array		data of the payment method to add, may be empty
	 *
	 * @return	integer		the UID of the created record, will always be > 0
	 */
	private function setPaymentMethodRelation(array $paymentMethodData) {
		$uid = $this->testingFramework->createRecord(
			SEMINARS_TABLE_PAYMENT_METHODS, $paymentMethodData
		);

		$this->fixture->setPaymentMethod($uid);

		return $uid;
	}


	/////////////////////////////////////
	// Tests for the utility functions.
	/////////////////////////////////////

	public function testSetPaymentMethodRelationReturnsUid() {
		$this->assertTrue(
			$this->setPaymentMethodRelation(array()) > 0
		);
	}

	public function testSetPaymentMethodRelationCreatesNewUid() {
		$this->assertNotEquals(
			$this->setPaymentMethodRelation(array()),
			$this->setPaymentMethodRelation(array())
		);
	}


	////////////////////////////////////////////
	// Tests regarding the registration queue.
	////////////////////////////////////////////

	public function testIsOnRegistrationQueue() {
		$this->assertFalse(
			$this->fixture->isOnRegistrationQueue()
		);

		$this->fixture->setIsOnRegistrationQueue(1);
		$this->assertTrue(
			$this->fixture->isOnRegistrationQueue()
		);
	}

	public function testStatusIsInitiallyRegular() {
		$this->assertEquals(
			'regular',
			$this->fixture->getStatus()
		);
	}

	public function testStatusIsRegularIfNotOnQueue() {
		$this->fixture->setIsOnRegistrationQueue(false);

		$this->assertEquals(
			'regular',
			$this->fixture->getStatus()
		);
	}

	public function testStatusIsWaitingListIfOnQueue() {
		$this->fixture->setIsOnRegistrationQueue(true);

		$this->assertEquals(
			'waiting list',
			$this->fixture->getStatus()
		);
	}


	///////////////////////////////////////////////////
	// Tests regarding getting the registration data.
	///////////////////////////////////////////////////

	public function testGetRegistrationDataIsEmptyForEmptyKey() {
		$this->assertEquals(
			'',
			$this->fixture->getRegistrationData('')
		);
	}

	public function testGetRegistrationDataCanGetUid() {
		$this->assertEquals(
			$this->fixture->getUid(),
			$this->fixture->getRegistrationData('uid')
		);
	}

	public function testDumpAttendanceValueCanContainUid() {
		$this->assertContains(
			(string) $this->fixture->getUid(),
			$this->fixture->dumpAttendanceValues('uid')
		);
	}

	public function testGetRegistrationDataWithKeyMethodOfPaymentReturnsMethodOfPayment() {
		$title = 'Test payment method';
		$this->setPaymentMethodRelation(array('title' => $title));

		$this->assertContains(
			$title,
			$this->fixture->getRegistrationData('method_of_payment')
		);
	}


	///////////////////////////////////////////
	// Tests regarding getting the user data.
	///////////////////////////////////////////

	public function testGetUserDataIsEmptyForEmptyKey() {
		$this->assertEquals(
			'',
			$this->fixture->getUserData('')
		);
	}


	/////////////////////////////////////////////////////////////
	// Tests regarding commiting registrations to the database.
	/////////////////////////////////////////////////////////////

	public function testCommitToDbCanCreateNewRecord() {
		$seminar = new tx_seminars_seminar($this->seminarUid);
		$registration = new tx_seminars_registrationchild(0);
		$registration->setRegistrationData($seminar, 0, array());
		$registration->enableTestMode();
		$this->testingFramework->markTableAsDirty(SEMINARS_TABLE_ATTENDANCES);

		$this->assertTrue(
			$registration->isOk()
		);
		$this->assertTrue(
			$registration->commitToDb()
		);
		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				SEMINARS_TABLE_ATTENDANCES,
				'uid='.$registration->getUid()
			),
			'The registration record cannot be found in the DB.'
		);
	}

	public function testCommitToDbCanCreateLodgingsRelation() {
		$seminar = new tx_seminars_seminar($this->seminarUid);
		$lodgingsUid = $this->testingFramework->createRecord(
			SEMINARS_TABLE_LODGINGS
		);

		$registration = new tx_seminars_registrationchild(0);
		$registration->setRegistrationData(
			$seminar, 0, array('lodgings' => array($lodgingsUid))
		);
		$registration->enableTestMode();
		$this->testingFramework->markTableAsDirty(SEMINARS_TABLE_ATTENDANCES);
		$this->testingFramework->markTableAsDirty(
			SEMINARS_TABLE_ATTENDANCES_LODGINGS_MM
		);

		$this->assertTrue(
			$registration->isOk()
		);
		$this->assertTrue(
			$registration->commitToDb()
		);
		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				SEMINARS_TABLE_ATTENDANCES,
				'uid='.$registration->getUid()
			),
			'The registration record cannot be found in the DB.'
		);
		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				SEMINARS_TABLE_ATTENDANCES_LODGINGS_MM,
				'uid_local='.$registration->getUid()
					.' AND uid_foreign='.$lodgingsUid
			),
			'The relation record cannot be found in the DB.'
		);
	}

	public function testCommitToDbCanCreateFoodsRelation() {
		$seminar = new tx_seminars_seminar($this->seminarUid);
		$foodsUid = $this->testingFramework->createRecord(
			SEMINARS_TABLE_FOODS
		);

		$registration = new tx_seminars_registrationchild(0);
		$registration->setRegistrationData(
			$seminar, 0, array('foods' => array($foodsUid))
		);
		$registration->enableTestMode();
		$this->testingFramework->markTableAsDirty(SEMINARS_TABLE_ATTENDANCES);
		$this->testingFramework->markTableAsDirty(
			SEMINARS_TABLE_ATTENDANCES_FOODS_MM
		);

		$this->assertTrue(
			$registration->isOk()
		);
		$this->assertTrue(
			$registration->commitToDb()
		);
		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				SEMINARS_TABLE_ATTENDANCES,
				'uid='.$registration->getUid()
			),
			'The registration record cannot be found in the DB.'
		);
		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				SEMINARS_TABLE_ATTENDANCES_FOODS_MM,
				'uid_local='.$registration->getUid()
					.' AND uid_foreign='.$foodsUid
			),
			'The relation record cannot be found in the DB.'
		);
	}

	public function testCommitToDbCanCreateCheckboxesRelation() {
		$seminar = new tx_seminars_seminar($this->seminarUid);
		$checkboxesUid = $this->testingFramework->createRecord(
			SEMINARS_TABLE_CHECKBOXES
		);

		$registration = new tx_seminars_registrationchild(0);
		$registration->setRegistrationData(
			$seminar, 0, array('checkboxes' => array($checkboxesUid))
		);
		$registration->enableTestMode();
		$this->testingFramework->markTableAsDirty(SEMINARS_TABLE_ATTENDANCES);
		$this->testingFramework->markTableAsDirty(
			SEMINARS_TABLE_ATTENDANCES_CHECKBOXES_MM
		);

		$this->assertTrue(
			$registration->isOk()
		);
		$this->assertTrue(
			$registration->commitToDb()
		);
		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				SEMINARS_TABLE_ATTENDANCES,
				'uid='.$registration->getUid()
			),
			'The registration record cannot be found in the DB.'
		);
		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				SEMINARS_TABLE_ATTENDANCES_CHECKBOXES_MM,
				'uid_local='.$registration->getUid()
					.' AND uid_foreign='.$checkboxesUid
			),
			'The relation record cannot be found in the DB.'
		);
	}
}
?>