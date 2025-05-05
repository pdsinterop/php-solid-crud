<?php

namespace Unit;

use Pdsinterop\Solid\Resources\Exception;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @coversDefaultClass \Pdsinterop\Solid\Resources\Exception
 * @covers ::create
 */
class ExceptionTest extends TestCase
{
    const MOCK_CONTEXT = ['Test'];
    const MOCK_MESSAGE = 'Error: %s';

    /**
     * @testdox Exception should complain when called without a message
     */
    public function testCreateWithoutMessage(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 0 passed/');

        Exception::create();
    }

    /**
     * @testdox Exception should complain when called without a context
     */
    public function testCreateWithoutContext(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 1 passed/');

        Exception::create(self::MOCK_MESSAGE);
    }

    /**
     * @testdox Exception should complain when called with invalid message
     */
    public function testCreateWithInvalidMessage(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/Argument #1 .+ must be of type string/');

        Exception::create(null, self::MOCK_CONTEXT);
    }

    /**
     * @testdox Exception should complain when called with invalid context
     */
    public function testCreateWithInvalidContext(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/Argument #2 .+ must be of type array/');

        Exception::create(self::MOCK_MESSAGE, null);
    }

    /**
     * @testdox Exception should complain when given context does not match provided message format
     */
    public function testCreateWithIncorrectContext(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessageMatches('/The arguments array must contain 1 items?, 0 given/');

        Exception::create(self::MOCK_MESSAGE, []);
    }

    /**
     * @testdox Exception should be created when called with valid message and context
     */
    public function testCreateWithMessageAndContext(): void
    {
        $expected = Exception::class;
        $actual = Exception::create(self::MOCK_MESSAGE, self::MOCK_CONTEXT);

        $this->assertInstanceOf($expected, $actual);
    }

    /**
     * @testdox Created Exception should have the correct message when called with a message and context
     */
    public function testCreateFormatsErrorMessage(): void
    {
        $expected = 'Error: Test';
        $actual = Exception::create(self::MOCK_MESSAGE, self::MOCK_CONTEXT)->getMessage();

        $this->assertSame($expected, $actual);
    }

    /**
     * @testdox Exception should be created when called with a message, context and previous exception
     */
    public function testCreateSetsPreviousException(): void
    {
        $expected = new \Exception('Previous exception');
        $actual = Exception::create(self::MOCK_MESSAGE, self::MOCK_CONTEXT, $expected)->getPrevious();

        $this->assertSame($expected, $actual);
    }
}
