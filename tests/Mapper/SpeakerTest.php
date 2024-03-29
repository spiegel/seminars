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
 */
class tx_seminars_Mapper_SpeakerTest extends tx_phpunit_testcase {
	/**
	 * @var tx_oelib_testingFramework
	 */
	private $testingFramework;

	/**
	 * @var tx_seminars_Mapper_Speaker
	 */
	private $fixture;

	protected function setUp() {
		$this->testingFramework = new tx_oelib_testingFramework('tx_seminars');

		$this->fixture = new tx_seminars_Mapper_Speaker();
	}

	protected function tearDown() {
		$this->testingFramework->cleanUp();
	}


	//////////////////////////
	// Tests concerning find
	//////////////////////////

	/**
	 * @test
	 */
	public function findWithUidOfExistingRecordReturnsOrganizerInstance() {
		$uid = $this->testingFramework->createRecord('tx_seminars_speakers');

		$this->assertTrue(
			$this->fixture->find($uid) instanceof tx_seminars_Model_Speaker
		);
	}

	/**
	 * @test
	 */
	public function findWithUidOfExistingRecordReturnsRecordAsModel() {
		$uid = $this->testingFramework->createRecord(
			'tx_seminars_speakers', array('title' => 'John Doe')
		);

		/** @var tx_seminars_Model_Speaker $model */
		$model = $this->fixture->find($uid);
		$this->assertEquals(
			'John Doe',
			$model->getName()
		);
	}


	////////////////////////////////
	// Tests regarding the skills.
	////////////////////////////////

	/**
	 * @test
	 */
	public function getSkillsReturnsListInstance() {
		$uid = $this->testingFramework->createRecord('tx_seminars_speakers');

		/** @var tx_seminars_Model_Speaker $model */
		$model = $this->fixture->find($uid);
		$this->assertTrue(
			$model->getSkills() instanceof tx_oelib_List
		);
	}

	/**
	 * @test
	 */
	public function getSkillsWithoutSkillsReturnsEmptyList() {
		$uid = $this->testingFramework->createRecord('tx_seminars_speakers');

		/** @var tx_seminars_Model_Speaker $model */
		$model = $this->fixture->find($uid);
		$this->assertTrue(
			$model->getSkills()->isEmpty()
		);
	}

	/**
	 * @test
	 */
	public function getSkillsWithOneSkillReturnsNonEmptyList() {
		$speakerUid = $this->testingFramework->createRecord('tx_seminars_speakers');
		$skill = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Skill')
			->getNewGhost();
		$this->testingFramework->createRelationAndUpdateCounter(
			'tx_seminars_speakers', $speakerUid, $skill->getUid(), 'skills'
		);

		/** @var tx_seminars_Model_Speaker $model */
		$model = $this->fixture->find($speakerUid);
		$this->assertFalse(
			$model->getSkills()->isEmpty()
		);
	}

	/**
	 * @test
	 */
	public function getSkillsWithOneSkillReturnsOneSkill() {
		$speakerUid = $this->testingFramework->createRecord('tx_seminars_speakers');
		$skill = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Skill')
			->getNewGhost();
		$this->testingFramework->createRelationAndUpdateCounter(
			'tx_seminars_speakers', $speakerUid, $skill->getUid(), 'skills'
		);

		/** @var tx_seminars_Model_Speaker $model */
		$model = $this->fixture->find($speakerUid);
		$this->assertEquals(
			$skill->getUid(),
			$model->getSkills()->getUids()
		);
	}


	///////////////////////////////
	// Tests regarding the owner.
	///////////////////////////////

	/**
	 * @test
	 */
	public function getOwnerWithoutOwnerReturnsNull() {
		$this->assertNull(
			$this->fixture->getLoadedTestingModel(array())->getOwner()
		);
	}

	/**
	 * @test
	 */
	public function getOwnerWithOwnerReturnsOwnerInstance() {
		$frontEndUser = tx_oelib_MapperRegistry::
			get('tx_seminars_Mapper_FrontEndUser')->getLoadedTestingModel(array());

		$this->assertTrue(
			$this->fixture->getLoadedTestingModel(
				array('owner' => $frontEndUser->getUid())
			)->getOwner() instanceof
				tx_seminars_Model_FrontEndUser
		);
	}
}