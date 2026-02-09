<?php

namespace LaravelAIAgent\Tests\Unit;

use LaravelAIAgent\Tests\TestCase;
use LaravelAIAgent\Support\SchemaConverter;

class SchemaConverterTest extends TestCase
{
    protected SchemaConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new SchemaConverter();
    }

    public function test_converts_basic_types(): void
    {
        $params = [
            'name' => ['type' => 'string', 'required' => true, 'rules' => '', 'description' => '', 'example' => null, 'default' => null],
            'age' => ['type' => 'integer', 'required' => true, 'rules' => '', 'description' => '', 'example' => null, 'default' => null],
            'active' => ['type' => 'boolean', 'required' => false, 'rules' => '', 'description' => '', 'example' => null, 'default' => null],
        ];

        $schema = $this->converter->convert($params);

        $this->assertEquals('object', $schema['type']);
        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('integer', $schema['properties']['age']['type']);
        $this->assertEquals('boolean', $schema['properties']['active']['type']);
        $this->assertEquals(['name', 'age'], $schema['required']);
    }

    public function test_converts_min_max_rules(): void
    {
        $params = [
            'username' => ['type' => 'string', 'required' => true, 'rules' => 'min:3|max:20', 'description' => '', 'example' => null, 'default' => null],
        ];

        $schema = $this->converter->convert($params);

        $this->assertEquals(3, $schema['properties']['username']['minLength']);
        $this->assertEquals(20, $schema['properties']['username']['maxLength']);
    }

    public function test_converts_enum_rule(): void
    {
        $params = [
            'status' => ['type' => 'string', 'required' => true, 'rules' => 'in:pending,active,completed', 'description' => '', 'example' => null, 'default' => null],
        ];

        $schema = $this->converter->convert($params);

        $this->assertEquals(['pending', 'active', 'completed'], $schema['properties']['status']['enum']);
    }

    public function test_converts_email_format(): void
    {
        $params = [
            'email' => ['type' => 'string', 'required' => true, 'rules' => 'email', 'description' => '', 'example' => null, 'default' => null],
        ];

        $schema = $this->converter->convert($params);

        $this->assertEquals('email', $schema['properties']['email']['format']);
    }
}
