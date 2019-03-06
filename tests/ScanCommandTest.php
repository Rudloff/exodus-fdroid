<?php
/**
 * ScanCommandTest class.
 */

namespace ExodusFdroid\Test;

use ExodusFdroid\ScanCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the ScanCommand class.
 */
class ScanCommandTest extends TestCase
{
    /**
     * Prepare tests.
     */
    protected function setUp()
    {
        $this->commandTester = new CommandTester(
            new ScanCommand(
                sys_get_temp_dir().'/'.uniqid('fdroid-test').'/'
            )
        );
    }

    /**
     * Test the constructor withtout any arguments.
     * @return void
     */
    public function testConstructorWithoutArguments()
    {
        $scanCommand = new ScanCommand();
        $this->assertInstanceOf(ScanCommand::class, $scanCommand);
    }

    /**
     * Test the execute() function.
     * @return void
     */
    public function testExecute()
    {
        $this->commandTester->execute(['id' => 'pro.rudloff.openvegemap']);
        $this->assertContains('OpenVegeMap', $this->commandTester->getDisplay());

        // We need an app with only one release to test the case where $app->package is not an array.
        $this->commandTester->execute(['id' => 'com.android.talkback']);
        $this->assertContains('TalkBack', $this->commandTester->getDisplay());
    }

    /**
     * Test the execute() function with an invalid app ID.
     * @return void
     */
    public function testExecuteWithInvalidId()
    {
        $this->commandTester->execute(['id' => 'invalid_id']);
        $this->assertContains('ERROR', $this->commandTester->getDisplay());
    }
}
