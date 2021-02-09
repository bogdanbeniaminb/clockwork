<?php

namespace BB\Clockwork\Tests;

use PHPUnit\Framework\TestCase;

class TagParserTest extends TestCase
{
  protected TagParser $parser;

  protected function setUp(): void
  {
    $this->parser = new TagParser();
  }

  /**
   * @dataProvider tagsProvider
   */
  public function test_it_parses_tags($input, $expected)
  {
    $result = $this->parser->parse($input);

    $this->assertSame($result, $expected);
  }

  public function tagsProvider()
  {
    return [
      ["personal, money", ["personal", "money"]],
      ["personal | money", ["personal", "money"]],
    ];
  }
}
