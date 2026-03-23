<?php
namespace Test;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase {
    public function testNoAssertions() {
        // This test has no assertions
    }
    
    public function testMagicNumber() {
        $result = 5 + 3;
        $this->assertEquals(8, $result);
        $this->assertTrue($result === 42);
    }
}
