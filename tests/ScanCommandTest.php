<?php
/**
 * ScanCommandTest class.
 */

namespace ExodusFdroid\Test;

use ExodusFdroid\ScanCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the ScanCommand class.
 */
class ScanCommandTest extends TestCase
{
    /**
     * Object used to call commands in tests.
     *
     * @var CommandTester
     */
    private $commandTester;

    /**
     * Prepare tests.
     */
    protected function setUp(): void
    {
        $this->commandTester = new CommandTester(
            new ScanCommand(
                sys_get_temp_dir().'/'.uniqid('fdroid-test').'/'
            )
        );
    }

    /**
     * Test the constructor withtout any arguments.
     *
     * @return void
     */
    public function testConstructorWithoutArguments()
    {
        $scanCommand = new ScanCommand();
        $this->assertInstanceOf(ScanCommand::class, $scanCommand);
    }

    /**
     * Test the execute() function.
     *
     * @return void
     */
    public function testExecute()
    {
        $this->commandTester->execute(
            ['id' => 'pro.rudloff.openvegemap'],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );
        $this->assertStringContainsString('OpenVegeMap', $this->commandTester->getDisplay());

        // We need an app with only one release to test the case where $app->package is not an array.
        $this->commandTester->execute(['id' => 'com.android.talkback']);
        $this->assertStringContainsString('TalkBack', $this->commandTester->getDisplay());

        // We need an app with some trackers.
        $this->commandTester->execute(['id' => 'org.wikipedia']);
        $this->assertStringContainsString('Wikipedia', $this->commandTester->getDisplay());
    }

    /**
     * Test the execute() function with an invalid app ID.
     *
     * @return void
     */
    public function testExecuteWithInvalidId()
    {
        $this->commandTester->execute(['id' => 'invalid_id']);
        $this->assertStringContainsString('ERROR', $this->commandTester->getDisplay());
    }
}
