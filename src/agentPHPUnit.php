<?php
/**
 * Created by PhpStorm.
 * User: Mikalai_Kabzar
 * Date: 1/9/2018
 * Time: 1:47 PM
 */
use PHPUnit\Framework as Framework;
use ReportPortalBasic\Enum\ItemStatusesEnum as ItemStatusesEnum;
use ReportPortalBasic\Enum\ItemTypesEnum as ItemTypesEnum;
use ReportPortalBasic\Enum\LogLevelsEnum as LogLevelsEnum;
use ReportPortalBasic\Service\ReportPortalHTTPService;
use GuzzleHttp\Psr7\Response as Response;

include "enum/PsrLogLevelsEnum.php";

class agentPHPUnit implements Framework\TestListener
{
    protected $tests = array();

    private $UUID;
    private $projectName;
    private $host;
    private $timeZone;
    private $launchName;
    private $launchDescription;
    private $className;
    private $classDescription;
    private $testName;
    private $testDescription;

    private $rootItemID;
    private $classItemID;
    private $testItemID;

    private static $suiteCounter = 0;

    /**
     * @var ReportPortalHTTPService
     */
    protected static $httpService;

    /**
     * agentPHPUnit constructor.
     * @param $UUID
     * @param $host
     * @param $projectName
     * @param $timeZone
     * @param $launchName
     * @param $launchDescription
     */
    public function __construct($UUID, $host, $projectName, $timeZone, $launchName, $launchDescription)
    {
        $this->UUID = $UUID;
        $this->host = $host;
        $this->projectName = $projectName;
        $this->timeZone = $timeZone;
        $this->launchName = $launchName;
        $this->launchDescription = $launchDescription;

        $this->configureClient();
        self::$httpService->launchTestRun($this->launchName, $this->launchDescription, ReportPortalHTTPService::DEFAULT_LAUNCH_MODE, []);
    }

    /**
     * agentPHPUnit destructor.
     */
    public function __destruct()
    {
        $status = self::getStatusByBool(true);
        $HTTPResult = self::$httpService->finishTestRun($status);
        self::$httpService->finishAll($HTTPResult);
    }

    /**
     * @param $test
     * @return null|string
     */
    private function getTestStatus($test)
    {
        $status = $test->getStatus();
        $statusResult = null;
        if ($status === PHPUnit\Runner\BaseTestRunner::STATUS_PASSED) {
            $statusResult = ItemStatusesEnum::PASSED;
        } else if ($status === PHPUnit\Runner\BaseTestRunner::STATUS_FAILURE) {
            $statusResult = ItemStatusesEnum::FAILED;
        } else if ($status === PHPUnit\Runner\BaseTestRunner::STATUS_SKIPPED) {
            $statusResult = ItemStatusesEnum::SKIPPED;
        } else if ($status === PHPUnit\Runner\BaseTestRunner::STATUS_INCOMPLETE) {
            $statusResult = ItemStatusesEnum::STOPPED;
        } else if ($status === PHPUnit\Runner\BaseTestRunner::STATUS_ERROR) {
            $statusResult = ItemStatusesEnum::FAILED;
        } else {
            $statusResult = ItemStatusesEnum::SKIPPED;
        }
        return $statusResult;
    }

    /**
     * Configure http client.
     */
    private function configureClient()
    {
        $isHTTPErrorsAllowed = false;
        $baseURI = sprintf(ReportPortalHTTPService::BASE_URI_TEMPLATE, $this->host);
        ReportPortalHTTPService::configureClient($this->UUID, $baseURI, $this->host, $this->timeZone, $this->projectName, $isHTTPErrorsAllowed);
        self::$httpService = new ReportPortalHTTPService();
    }

    /**
     * @param Framework\Test $test
     * @param Framework\Exception $e
     * @param $logLevelsEnum
     * @param $testItemID
     */
    private function addSetOfLogMessages(PHPUnit\Framework\Test $test, PHPUnit\Framework\Exception $e, $logLevelsEnum, $testItemID)
    {
        if ($test->loggedMessages) {
            $test->loggedMessages = $this->sendLoggedMessages($test->loggedMessages, $testItemID);
        }    
        
        $errorMessage = $e->__toString();
        if (isset($test->screenShotFilePath)) {
            $screenshotContent = $this->getScreenShot($test->screenShotFilePath);
            self::$httpService->addLogMessageWithPicture($testItemID, $errorMessage, $logLevelsEnum, $screenshotContent, "png");
        } else {
            self::$httpService->addLogMessage($testItemID, $errorMessage, $logLevelsEnum);
        }

        $this->AddLogMessages($test, $e, $logLevelsEnum, $testItemID);

        $trace = $e->getTraceAsString();
        self::$httpService->addLogMessage($testItemID, $trace, $logLevelsEnum);
    }

    /**
     * Get screenshot binary data
     *
     * @param string $screenShotFilePath
     * @return string
     */
    private function getScreenShot(string $screenShotFilePath): string
    {
        $handle = fopen($screenShotFilePath, "rb");
        $contents = fread($handle, filesize($screenShotFilePath));
        fclose($handle);
        return $contents;
    }

    /**
     * Send additional logger messages from TestCase
     *
     * @param array $loggedMessages
     * @param string $testItemID
     * @return array
     */
    private function sendLoggedMessages(array $loggedMessages, string $testItemID): array
    {
        foreach ($loggedMessages as &$message) {
            if ($message["sent"] === false) {
                $logLevel = PsrLogLevelsEnum::covertLevelFromPsrToReportPortal($message["level_name"]);
                $date = date_format($message["datetime"], "Y/m/d H:i:s");
                $context = $message["context"] ? json_encode($message["context"]) : "";
                self::$httpService->addLogMessage($testItemID, $date . " " . $message["message"] . " " . $context, $logLevel);
                $message["sent"] = true;
            }
        }
        return $loggedMessages;
    }

    /**
     * @param Framework\Test $test
     * @param Framework\Exception $e
     * @param $logLevelsEnum
     * @param $testItemID
     */
    private function AddLogMessages(PHPUnit\Framework\Test $test, PHPUnit\Framework\Exception $e, $logLevelsEnum, $testItemID)
    {
        $className = get_class($test);
        $traceArray = $e->getTrace();
        $arraySize = sizeof($traceArray);
        $foundedFirstMatch = false;
        $counter = 0;
        while (!$foundedFirstMatch and $counter < $arraySize) {
            if (strpos($traceArray[$counter]["file"], $className) != false) {
                $fileName = $traceArray[$counter]["file"];
                $fileLine = $traceArray[$counter]["line"];
                $function = $traceArray[$counter]["function"];
                $assertClass = $traceArray[$counter]["class"];
                $type = $traceArray[$counter]["type"];
                $args = implode(',', $traceArray[$counter]["args"]);
                self::$httpService->addLogMessage($testItemID, $assertClass . $type . $function . '(' . $args . ')', $logLevelsEnum);
                self::$httpService->addLogMessage($testItemID, $fileName . ':' . $fileLine, $logLevelsEnum);
                $foundedFirstMatch = true;
            }
            $counter++;
        }
    }

    /**
     * @param bool $isFailedItem
     * @return string
     */
    private static function getStatusByBool(bool $isFailedItem)
    {
        if ($isFailedItem) {
            $stringItemStatus = ItemStatusesEnum::FAILED;
        } else {
            $stringItemStatus = ItemStatusesEnum::PASSED;
        }
        return $stringItemStatus;
    }

    /**
     * Get ID from response
     *
     * @param Response $HTTPResponse
     * @return string
     */
    private static function getID(Response $HTTPResponse)
    {
        return json_decode($HTTPResponse->getBody(), true)['id'];
    }

    /**
     * Check that response ended with 200 state
     *
     * @param Response $HTTPResponse
     */
    private static function checkResponse(Response $HTTPResponse)
    {
        $statusCode = $HTTPResponse->getStatusCode();
        if ($statusCode !== 200 && $statusCode !== 201) {
            throw new Exception("Connection to ReportPortal failed with: [" . $statusCode . "] " . $HTTPResponse->getReasonPhrase());
        }
    }

    /**
     * Is a suite without name
     *
     * @param Framework\TestSuite $suite
     * @return bool
     */
    private static function isNoNameSuite(\PHPUnit\Framework\TestSuite $suite):bool
    {
        return $suite->getName() !== "";
    }

    /**
     * A warning occurred.
     * @param Framework\Test $test
     * @param Framework\Warning $e
     * @param float $time
     */
    public function addWarning(\PHPUnit\Framework\Test $test, \PHPUnit\Framework\Warning $e, float $time): void
    {
        // TODO: Implement addWarning() method.
    }

    /**
     * Risky test.
     * @param Framework\Test $test
     * @param Throwable $t
     * @param float $time
     */
    public function addRiskyTest(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        // TODO: Implement addRiskyTest() method.
    }

    /**
     * An error occurred.
     * @param Framework\Test $test
     * @param Throwable $t
     * @param float $time
     */
    public function addError(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        $this->addSetOfLogMessages($test, $t, LogLevelsEnum::FATAL, $this->testItemID);
    }

    /**
     * A test ended.
     * @param Framework\Test $test
     * @param float $time
     */
    public function endTest(\PHPUnit\Framework\Test $test, float $time): void
    {
        //sending logged messages from Passed tests 
        if ($test->loggedMessages) {
            $test->loggedMessages = $this->sendLoggedMessages($test->loggedMessages, $this->testItemID);
        }    
        $testStatus = $this->getTestStatus($test);
        self::$httpService->finishItem($this->testItemID, $testStatus, $time . ' seconds');
    }

    /**
     * A test started.
     * @param Framework\Test $test
     */
    public function startTest(\PHPUnit\Framework\Test $test): void
    {
        $this->testName = $test->getName();
        $this->testDescription = '';
        $response = self::$httpService->startChildItem($this->classItemID, $this->testDescription, $this->testName, ItemTypesEnum::TEST, []);
        $this->checkResponse($response);
        $this->testItemID = self::getID($response);
    }

    /**
     * A test suite started.
     * @param Framework\TestSuite $suite
     */
    public function startTestSuite(\PHPUnit\Framework\TestSuite $suite): void
    {
        if (self::isNoNameSuite($suite)) {
            self::$suiteCounter++;

            if (self::$suiteCounter == 1) {
                $suiteName = $suite->getName();
                $response = self::$httpService->createRootItem($suiteName, '', []);
                $this->checkResponse($response);
                $this->rootItemID = self::getID($response);
            } elseif (self::$suiteCounter >1) {
                $className = $suite->getName();
                $this->className = $className;
                $this->classDescription = '';
                if (self::$suiteCounter == 2){
                    $response = self::$httpService->startChildItem($this->rootItemID, $this->classDescription, $this->className, ItemTypesEnum::SUITE, []);
                    $this->checkResponse($response);
                    $this->classItemID = self::getID($response);
                    self::$httpService->setStepItemID($this->classItemID);
                }
            }
        }
    }

    /**
     * A test suite ended.
     * @param Framework\TestSuite $suite
     */
    public function endTestSuite(\PHPUnit\Framework\TestSuite $suite): void
    {
        if (self::isNoNameSuite($suite)) {
            self::$suiteCounter--;
            if (self::$suiteCounter == 0) {
                self::$httpService->finishRootItem();
            } elseif (self::$suiteCounter == 1) {
                self::$httpService->finishItem($this->classItemID, ItemStatusesEnum::FAILED, $this->classDescription);
            }
        }
    }

    /**
     * A failure occurred.
     * @param Framework\Test $test
     * @param Framework\AssertionFailedError $e
     * @param float $time
     */
    public function addFailure(\PHPUnit\Framework\Test $test, \PHPUnit\Framework\AssertionFailedError $e, float $time): void
    {
        $this->addSetOfLogMessages($test, $e, LogLevelsEnum::ERROR, $this->testItemID);
    }

    /**
     * Skipped test.
     * @param Framework\Test $test
     * @param Throwable $t
     * @param float $time
     */
    public function addSkippedTest(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        $this->addSetOfLogMessages($test, $t, LogLevelsEnum::WARN, $this->testItemID);
    }

    /**
     * Incomplete test.
     * @param Framework\Test $test
     * @param Throwable $t
     * @param float $time
     */
    public function addIncompleteTest(\PHPUnit\Framework\Test $test, \Throwable $t, float $time): void
    {
        $this->addSetOfLogMessages($test, $t, LogLevelsEnum::WARN, $this->testItemID);
    }
}