<?php

namespace BrowscapTest;

use Browscap\Generator\BrowscapIniGenerator;
use Browscap\Generator\BuildGenerator;
use Browscap\Generator\CollectionParser;
use Browscap\Helper\CollectionCreator;
use Browscap\Helper\Generator;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use phpbrowscap\Browscap;

/**
 * Class UserAgentsTest
 *
 * @package BrowscapTest
 */
class UserAgentsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \phpbrowscap\Browscap
     */
    protected static $browscap;

    public static function setUpBeforeClass()
    {
        // First, generate the INI files
        $buildNumber = time();

        $resourceFolder = __DIR__ . '/../../resources/';

        $buildFolder = sys_get_temp_dir() . '/browscap-ua-test-' . $buildNumber;
        mkdir($buildFolder, 0777, true);

        $logger = new Logger('browscap');
        $logger->pushHandler(new NullHandler(Logger::DEBUG));

        $collectionCreator = new CollectionCreator();
        $collectionParser = new CollectionParser();
        $iniGenerator = new BrowscapIniGenerator();

        $iniFile = $buildFolder . '/full_php_browscap.ini';

        $generatorHelper = new Generator();
        $generatorHelper
            ->setVersion('temporary-version')
            ->setLogger($logger)
            ->setResourceFolder($resourceFolder)
            ->setCollectionCreator($collectionCreator)
            ->setCollectionParser($collectionParser)
            ->createCollection()
            ->parseCollection()
            ->setGenerator($iniGenerator)
        ;

        file_put_contents($iniFile, $generatorHelper->create(BuildGenerator::OUTPUT_FORMAT_PHP, BuildGenerator::OUTPUT_TYPE_FULL));

        // Now, load an INI file into phpbrowscap\Browscap for testing the UAs
        $browscap = new Browscap($buildFolder);
        $browscap->localFile = $iniFile;

        self::$browscap = $browscap;
    }

    public function userAgentDataProvider()
    {
        $data = array();
        $uaSourceDirectory = __DIR__ . '/../fixtures/issues/';

        $iterator = new \RecursiveDirectoryIterator($uaSourceDirectory);

        foreach (new \RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if (!$file->isFile() || $file->getExtension() != 'php') {
                continue;
            }

            $tests = require_once $file->getPathname();

            foreach ($tests as $key => $test) {
                if (isset($data[$key])) {
                    throw new \RuntimeException('Test data is duplicated for key "' . $key . '"');
                }

                $data[$key] = $test;
            }
        }

        return $data;
    }

    /**
     * @dataProvider userAgentDataProvider
     * @coversNothing
     */
    public function testUserAgents($ua, $props)
    {
        if (!is_array($props) || !count($props)) {
            $this->markTestSkipped('Could not run test - no properties were defined to test');
        }

        $actualProps = self::$browscap->getBrowser($ua, true);

        foreach ($props as $propName => $propValue) {
            self::assertArrayHasKey(
                $propName,
                $actualProps,
                'Actual properties did not have "' . $propName . '" property'
            );

            self::assertSame(
                $propValue,
                $actualProps[$propName],
                'Expected actual "' . $propName . '" to be "' . $propValue . '" (was "' . $actualProps[$propName] . '")'
            );
        }
    }
}
