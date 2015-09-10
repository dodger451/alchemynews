<?php
namespace Latotzky\Alchemynews\Test;

class IndexTest extends \PHPUnit_Framework_TestCase
{
    protected $classToTest = 'Latotzky\Alchemynews\HelloWorld';
    public function setUp()
    {
    }
    public function tearDown()
    {
    }
    public function testHelloWorld()
    {
        $hello = new $this->classToTest();
        $this->assertEquals('Hello', $hello->hello());
    }
}
