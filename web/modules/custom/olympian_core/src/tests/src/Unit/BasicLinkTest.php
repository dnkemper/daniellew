<?php

namespace Drupal\Tests\olympian_core\Unit;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "olympian_core" implementation.
 *
 * @coversDefaultClass \Drupal\olympian_core\Plugin\DateAugmenter\AddToCal
 * @group olympian_core
 *
 * @see \Drupal\olympian_core\Plugin\DateAugmenter\AddToCal
 */
class BasicLinkTest extends UnitTestCase {

  /**
   * A mocked version of the AddToCal plugin.
   *
   * @var \Drupal\olympian_core\Plugin\DateAugmenter\AddToCal
   */
  protected $addtocal;

  /**
   * Before a test method is run, setUp() is invoked.
   */
  public function setUp(): void {
    parent::setUp();

    $config_factory = $this->getConfigFactoryStub(
      [
        'system.date' => [
          'timezone' => ['America/Chicago'],
        ],
        'system.site' => [
          'name' => 'My awesome test site',
        ],
      ]
    );
    $this->addtocal = new TestAddToCal([], 'smart_date', [], $config_factory);
  }

  /**
   * Test AddToCal::generateLinks with a data provider method.
   *
   * Uses the data provider method to test with a wide range of words/stems.
   */
  public function testCal() {
    foreach ($this->getData() as $data) {
      $actual = $this->addtocal->buildLinks([], $data['input']['start'], $data['input']['end'], $data['input']);
      $this->assertEquals($data['expected']['google'], $actual['google']);
      $this->assertEquals($data['expected']['ical'], array_values($actual['ical']));
    }
  }

  /**
   * Data provider for testCal().
   *
   * @return array
   *   Nested arrays of values to check:
   *   - $word
   *   - $stem
   */
  public function getData() {

    // Send a Node mock, because NodeInterface cannot be mocked.
    $mock_node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();
    $mock_node->expects($this->any())
      ->method('uuid')
      ->willReturn('uuid12345');

    $cdt = new \DateTimeZone('America/Chicago');
    $jpn = new \DateTimeZone('Asia/Tokyo');
    $settings = ['langcode' => 'en'];
    $data = [];
    $data['A single event spanning one hour'] = [
      'expected' => [
        'ical' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'BEGIN:VTIMEZONE',
          'TZID:America/Chicago',
          'BEGIN:STANDARD',
          'TZOFFSETFROM:-0500',
          'TZOFFSETTO:-0500',
          'END:STANDARD',
          'END:VTIMEZONE',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:A single event spanning one hour',
          'DTSTAMP:20211027T050000Z',
          'DTSTART;TZID=America/Chicago:20211029T150000',
          'DTEND;TZID=America/Chicago:20211029T160000',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'google' => [
          'ctz' => 'America/Chicago',
          'text' => 'A single event spanning one hour',
          'dates' => '20211029T150000/20211029T160000',
        ],
      ],
      'input' => [
        'entity' => $mock_node,
        'start' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 15:00:00', $cdt, $settings),
        'end' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 16:00:00', $cdt, $settings),
        'settings' => [
          'title' => 'A single event spanning one hour',
        ],
      ],
    ];
    $data['A recurring event, in Tokyo'] = [
      'expected' => [
        'ical' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'BEGIN:VTIMEZONE',
          'TZID:Asia/Tokyo',
          'BEGIN:STANDARD',
          'TZOFFSETFROM:+0900',
          'TZOFFSETTO:+0900',
          'END:STANDARD',
          'END:VTIMEZONE',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:Test title here',
          'DTSTAMP:20211027T050000Z',
          'DTSTART;TZID=Asia/Tokyo:20211029T150000',
          'DTEND;TZID=Asia/Tokyo:20211029T160000',
          'FREQ=DAILY;BYDAY=MO;COUNT=2',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'google' => [
          'ctz' => 'Asia/Tokyo',
          'text' => 'Test title here',
          'dates' => '20211029T150000/20211029T160000',
          'recur' => 'FREQ=DAILY;BYDAY=MO;COUNT=2',
        ],
      ],
      'input' => [
        'entity' => $mock_node,
        'repeats' => 'FREQ=DAILY;BYDAY=MO;COUNT=2',
        'start' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 15:00:00', $jpn, $settings),
        'end' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 16:00:00', $jpn, $settings),
        'settings' => [
          'title' => 'Test title here',
        ],
      ],
    ];
    $data['An all-day event'] = [
      'expected' => [
        'ical' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'BEGIN:VTIMEZONE',
          'TZID:America/Chicago',
          'BEGIN:STANDARD',
          'TZOFFSETFROM:-0500',
          'TZOFFSETTO:-0500',
          'END:STANDARD',
          'END:VTIMEZONE',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:An all-day event',
          'DTSTAMP:20211027T050000Z',
          'DTSTART:20211029',
          'DTEND:20211031',
          'DESCRIPTION:Here is a description...',
          'LOCATION:Zoom',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'google' => [
          'ctz' => 'America/Chicago',
          'text' => 'An all-day event',
          'dates' => '20211029/20211031',
          'details' => 'Here is a description...',
          'location' => 'Zoom',
        ],
      ],
      'input' => [
        'entity' => $mock_node,
        'allday' => TRUE,
        'start' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 00:00:00', $cdt, $settings),
        'end' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-30 00:00:00', $cdt, $settings),
        'settings' => [
          'title' => 'An all-day event',
          'description' => 'Here is a description',
          'location' => 'Zoom',
        ],
      ],
    ];
    return $data;
  }

}
