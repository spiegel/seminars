<?php
/***************************************************************
 * Copyright notice
*
* (c) 2012 Niels Pardon (mail@niels-pardon.de)
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
 * Testcase for the tx_seminars_ViewHelper_DateRange class.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_ViewHelper_DateRangeTest extends tx_phpunit_testcase {
	/**
	 * @var tx_seminars_ViewHelper_DateRange
	 */
	private $fixture;

	/**
	 * @var tx_oelib_testingFramework
	 */
	private $testingFramework;

	/**
	 * @var tx_oelib_Configuration
	 */
	private $configuration;

	/**
	 * @var tx_oelib_Translator
	 */
	private $translator;

	/**
	 * @var int some random date (2001-01-01 00:00:00)
	 */
	const BEGIN_DATE = 978303600;

	/**
	 * @var string
	 */
	const DATE_FORMAT_YMD = '%d.%m.%Y',
		DATE_FORMAT_Y = '%Y',
		DATE_FORMAT_M = '%m.',
		DATE_FORMAT_MD = '%d.%m.',
		DATE_FORMAT_D = '%d.';

	protected function setUp() {
		$this->testingFramework	= new tx_oelib_testingFramework('tx_seminars');

		$this->configuration = new tx_oelib_Configuration();
		$this->configuration->setAsString('dateFormatYMD', self::DATE_FORMAT_YMD);
		$this->configuration->setAsString('dateFormatY', self::DATE_FORMAT_Y);
		$this->configuration->setAsString('dateFormatM', self::DATE_FORMAT_M);
		$this->configuration->setAsString('dateFormatMD', self::DATE_FORMAT_MD);
		$this->configuration->setAsString('dateFormatD', self::DATE_FORMAT_D);

		tx_oelib_ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', $this->configuration);

		$this->translator = tx_oelib_TranslatorRegistry::getInstance()->get('seminars');

		$this->fixture = new tx_seminars_ViewHelper_DateRange();
	}

	protected function tearDown() {
		$this->testingFramework->cleanUp();
	}

	/**
	 * @test
	 */
	public function renderWithTimeSpanWithNoDatesReturnMessageWillBeAnnounced() {
		$timeSpan = new tx_seminars_tests_fixtures_TestingTimeSpan();
		$timeSpan->setData(array());

		$this->assertSame(
			$this->translator->translate('message_willBeAnnounced'),
			$this->fixture->render($timeSpan)
		);
	}

	/**
	 * @test
	 */
	public function renderWithTimeSpanWithBeginDateOnlyRendersOnlyBeginDate() {
		$timeSpan = new tx_seminars_tests_fixtures_TestingTimeSpan();
		$timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);

		$this->assertSame(
			strftime(self::DATE_FORMAT_YMD, self::BEGIN_DATE),
			$this->fixture->render($timeSpan)
		);
	}

	/**
	 * @test
	 */
	public function renderWithTimeSpanWithEqualBeginAndEndDateReturnsOnlyBeginDate() {
		$timeSpan = new tx_seminars_tests_fixtures_TestingTimeSpan();
		$timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
		$timeSpan->setEndDateAsUnixTimeStamp(self::BEGIN_DATE);

		$this->assertSame(
			strftime(self::DATE_FORMAT_YMD, self::BEGIN_DATE),
			$this->fixture->render($timeSpan)
		);
	}

	/**
	 * @test
	 */
	public function renderWithTimeSpanWithBeginAndEndDateOnSameDayReturnsOnlyBeginDate() {
		$timeSpan = new tx_seminars_tests_fixtures_TestingTimeSpan();
		$timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
		$timeSpan->setEndDateAsUnixTimeStamp(self::BEGIN_DATE + 3600);

		$this->assertEquals(
			strftime(self::DATE_FORMAT_YMD, self::BEGIN_DATE),
			$this->fixture->render($timeSpan)
		);
	}

	/**
	 * @test
	 */
	public function renderWithTimeSpanWithBeginAndEndDateOnDifferentDaysWithAbbreviateDateRangeFalseReturnsBothFullDatesSeparatedByDash() {
		$this->configuration->setAsBoolean('abbreviateDateRanges', FALSE);

		$timeSpan = new tx_seminars_tests_fixtures_TestingTimeSpan();
		$timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
		$endDate = self::BEGIN_DATE + (2 * 86400);
		$timeSpan->setEndDateAsUnixTimeStamp($endDate);

		$this->assertEquals(
			strftime(self::DATE_FORMAT_YMD, self::BEGIN_DATE) . '&#8211;' . strftime(self::DATE_FORMAT_YMD, $endDate),
			$this->fixture->render($timeSpan)
		);
	}

	/**
	 * @test
	 */
	public function renderWithTimeSpanWithBeginAndEndDateOnDifferentDaysButSameMonthWithAbbreviateDateRangeTrueReturnsOnlyDayOfBeginDateAndFullEndDateSeparatedByDash() {
		$this->configuration->setAsBoolean('abbreviateDateRanges', TRUE);

		$timeSpan = new tx_seminars_tests_fixtures_TestingTimeSpan();
		$timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
		$endDate = self::BEGIN_DATE + (2 * 86400);
		$timeSpan->setEndDateAsUnixTimeStamp($endDate);

		$this->assertEquals(
			strftime(self::DATE_FORMAT_D, self::BEGIN_DATE) . '&#8211;' . strftime(self::DATE_FORMAT_YMD, $endDate),
			$this->fixture->render($timeSpan)
		);
	}

	/**
	 * @test
	 */
	public function renderWithTimeSpanWithBeginAndEndDateOnDifferentMonthWithAbbreviateDateRangeTrueReturnsDayAndMonthOfBeginDateAndFullEndDateSeparatedByDash() {
		$this->configuration->setAsBoolean('abbreviateDateRanges', TRUE);

		$timeSpan = new tx_seminars_tests_fixtures_TestingTimeSpan();
		$timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
		$endDate = self::BEGIN_DATE + (32 * 86400);
		$timeSpan->setEndDateAsUnixTimeStamp($endDate);

		$this->assertEquals(
			strftime(self::DATE_FORMAT_MD, self::BEGIN_DATE) . '&#8211;' . strftime(self::DATE_FORMAT_YMD, $endDate),
			$this->fixture->render($timeSpan)
		);
	}

	/**
	 * @test
	 */
	public function renderWithTimeSpanWithBeginAndEndDateOnDifferentYearsWithAbbreviateDateRangeTrueReturnsFullBeginDateAndFullEndDateSeparatedByDash() {
		$this->configuration->setAsBoolean('abbreviateDateRanges', TRUE);

		$timeSpan = new tx_seminars_tests_fixtures_TestingTimeSpan();
		$timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
		$endDate = self::BEGIN_DATE + (366 * 86400);
		$timeSpan->setEndDateAsUnixTimeStamp($endDate);

		$this->assertEquals(
			strftime(self::DATE_FORMAT_YMD, self::BEGIN_DATE) . '&#8211;' . strftime(self::DATE_FORMAT_YMD, $endDate),
			$this->fixture->render($timeSpan)
		);
	}

	/**
	 * @test
	 */
	public function renderWithTimeSpanWithBeginAndEndDateOnDifferentDaysWithAbbreviateDateRangeFalseReturnsBothFullDatesSeparatedBySpecifiedDash() {
		$this->configuration->setAsBoolean('abbreviateDateRanges', FALSE);
		$dash = '#DASH#';

		$timeSpan = new tx_seminars_tests_fixtures_TestingTimeSpan();
		$timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
		$endDate = self::BEGIN_DATE + (2 * 86400);
		$timeSpan->setEndDateAsUnixTimeStamp($endDate);

		$this->assertEquals(
			strftime(self::DATE_FORMAT_YMD, self::BEGIN_DATE) . $dash . strftime(self::DATE_FORMAT_YMD, $endDate),
			$this->fixture->render($timeSpan, $dash)
		);
	}
}