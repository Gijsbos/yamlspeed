<?php
declare(strict_types=1);

namespace WDS;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use YamlSpeed\YamlSpeed;

/**
 * YamlSpeedTest
 */
final class YamlSpeedTest extends TestCase
{
    public static $globalPreParsers = [];
    public static $globalPostParsers = [];

    public static function setUpBeforeClass() : void
    {
        self::$globalPreParsers = YamlSpeed::$globalPreParsers;
        self::$globalPostParsers = YamlSpeed::$globalPostParsers;
    }

    /**
     * Restore methods every test
     */
    private function restore()
    {
        YamlSpeed::$globalPreParsers = self::$globalPreParsers;
        YamlSpeed::$globalPostParsers = self::$globalPostParsers;
    }

    protected function setUp() : void
    {
        $this->restore();
    }

    public function testIndentation()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                property:
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "property" => null,
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testIndentation2()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                property:
                    key: value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "property" => [
                "key" => "value"
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testIndentation3()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                property:
                    property2:
                        key: value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "property" => [
                "property2" => [
                    "key" => "value"
                ]
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testIndentation4()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                property:
                    sub1: value1
                    sub2:
                        key: value2
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "property" => [
                "sub1" => "value1",
                "sub2" => [
                    "key" => "value2"
                ]
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testIndentation5()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                property:
                    sub1: value1
                    sub2:
                        keya: value2
                    sub3:
                        keyb: value3
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "property" => [
                "sub1" => "value1",
                "sub2" => [
                    "keya" => "value2"
                ],
                "sub3" => [
                    "keyb" => "value3",
                ]
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testIndentation6()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                property:
                    sub1: value1
                    sub2:
                        key1: value2
                property2:
                    item1: value3
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "property" => [
                "sub1" => "value1",
                "sub2" => [
                    "key1" => "value2"
                ],  
            ],
            "property2" => [
                "item1" => "value3",
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testIlligalIndentationSkipLevel()
    {
        $this->expectExceptionMessage("Invalid indentation near '            invalid: value1'");

        $yaml = new YamlSpeed();
        $text = <<<YAML
                property:
                    sub1: value1
                            invalid: value1
                YAML;
        $result = $yaml->parse($text);
    }

    public function testKeyValue()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => "value",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueColonInKey()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key:key: value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key:key" => "value",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueDoubleQuoteKey()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                "key": value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => "value",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueDoubleQuoteEmpty()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                "key": ""
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => "",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueSingleQuoteKey()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                'key': value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => "value",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueSingleQuoteEmpty()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                'key': ''
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => "",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueDoubleQuote()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key:key: "value"
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key:key" => "value",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueSingleQuote()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key:key: 'value'
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key:key" => "value",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueDoubleQuotes()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: "value=\"foo\""
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => "value=\"foo\"",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Bug: did not parse $_FLAGS: value
     */
    public function testKeyValueValueDollarSign()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                \$_FLAGS: value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            '$_FLAGS' => "value"
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testParseFlowMap()
    {
        $input = <<< EOD
                { value1: "<p>\nLine 1.</p>", value2: hi}
                EOD;

        $yaml = new YamlSpeed();
        $result = $yaml->parseFlowMap($input);
        $expectedResult = [
            "value1" => "<p>\nLine 1.</p>",
            "value2" => "hi",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testParseFlowMapInline()
    {
        $input =    <<< EOD
                    key: ['value', { force: true, verbose: true }]
                    EOD;

        $yaml = new YamlSpeed();
        $result = $yaml->parse($input);
        $expectedResult = [
            "key" => [
                "value",
                [
                    "force" => true,
                    "verbose" => true,
                ]
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testParseFlowMapInlineWithInlineArray()
    {
        $input =    <<< EOD
                    key: { select: '*', whereIn: [{ property: subjectId, in: WDS\API\OAUTH2\Client, as: clientId }] }
                    EOD;

        $yaml = new YamlSpeed();
        $result = $yaml->parse($input);
        $expectedResult = [
            "key" => [
                "select" => '*',
                "whereIn" => [
                    [
                        "property" => "subjectId",
                        "in" => "WDS\API\OAUTH2\Client",
                        "as" => "clientId",
                    ]
                ]
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueStringWithNewlines()
    {
        $input = "tests/newline.yaml";
        $yaml = new YamlSpeed();
        $result = $yaml->parseFile($input);
        $expectedResult = [
            "DATA" => [
                [
                    "value1" => "<p>\nLine 1.</p>",
                    "value2" => "hi"
                ],
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueStringWithNewlines2()
    {
        $yaml = new YamlSpeed();
        $result = $yaml->parseFile("tests/newline2.yaml");
        $expectedResult = array(
            "data" => [
                "nginx.conf" => "user nginx;
worker_processes auto;"
            ],
        );
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueStringWithTabs()
    {
        $input =    <<<YAML
                    key: "<html>\n\n<head>\n\t\t<title>"
                    YAML;
        $yaml = new YamlSpeed();
        $result = $yaml->parse($input);
        $expectedResult = [
            "key" => "<html>\n<head> \t\t<title>"
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueStringWithTabs2()
    {
        $file = "./tests/tabs.yaml";
        $yaml = new YamlSpeed();
        $result = $yaml->parseFile($file);
        $expectedResult = [
            "data" => [
                [
                    "html" => "<html>\n\n<head>\n\t<title>%{subject}</title>"
                ]
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueBooleanTrue()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: true
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => true,
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueBooleanFalse()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: false
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => false,
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueBooleanNull()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: null
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => null,
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueInt()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: 1
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => 1,
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueFloat()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: 1.1
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => 1.1,
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueConstant()
    {
        $yaml = new YamlSpeed(["parseConstants" => true]);
        $text = <<<YAML
                key: FILE_APPEND
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => FILE_APPEND,
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueConstant2()
    {
        $yaml = new YamlSpeed(["parseConstants" => true]);
        $text = <<<YAML
                \$_FLAGS: FILE_APPEND
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            '$_FLAGS' => FILE_APPEND,
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testKeyValueValueEnv()
    {
        $yaml = new YamlSpeed(["parseEnvVars" => true]);
        putenv("FOO=BAR");
        $text = <<<YAML
                key: \${FOO}
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => "BAR",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @bug
     * 
     * Input
     *          pre: <--- space here causes key:autoloader to jump to the pre key
     *              value: 'success'
     *          key: 
     *              autoloader: 'path.php'
     * 
     * Error Result
     *      ["pre"]=>
     *      &array(2) {
     *          ["value"]=>
     *          string(7) "success"
     *          ["autoloader"]=>
     *          string(8) "path.php"
     *      }
     *      ["key"]=>
     *          string(0) ""
     *      }
     */
    public function testKeyWithTrailingSpace()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                pre: 
                    value: 'success'
                key: 
                    autoloader: 'path.php'
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "pre" => [
                "value" => "success",
            ],
            "key" => [
                "autoloader" => "path.php"
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testHyphenArray1()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key:
                    - item 1
                    - item 2
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => [
                "item 1",
                "item 2",
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testHyphenArray2()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key:
                - item 1
                - item 2
                key2:
                    - item a
                    - item b
                key3:
                - itemI: value I
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => [
                "item 1",
                "item 2",
            ],
            "key2" => [
                "item a",
                "item b",
            ],
            "key3" => [
                [
                    "itemI" => "value I"
                ]
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testHyphenArray3()
    {
        $yaml = new YamlSpeed();
        $text =     <<< YAML
                    shoppingListA:
                        - 'Pear Apple'
                    shoppingListB:
                        - "Sneakers Cap"
                    shoppingListC: 
                    YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "shoppingListA" => [
                "Pear Apple",
            ],
            "shoppingListB" => [
                "Sneakers Cap",
            ],
            "shoppingListC" => ""
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testHyphenArray4()
    {
        $yaml = new YamlSpeed();
        $text =     <<< YAML
                    containers:
                    - name: nginx
                      image: nginx:1.25.2
                    - name: php
                      image: php-fpm:8.0
                    YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "containers" => [
                [
                    "name" => "nginx",
                    "image" => "nginx:1.25.2",
                ],
                [
                    "name" => "php",
                    "image" => "php-fpm:8.0",
                ],
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testHyphenAssocArray()
    {
        $yaml = new YamlSpeed();
        $text =     <<< YAML
                    test:
                        -   key: value
                    YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "test" => [
                [
                    "key" => "value"
                ]
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testHyphenNotAssocArray()
    {
        $yaml = new YamlSpeed();
        $text =     <<< YAML
                    test:
                        - "key: value"
                    YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "test" => [
                "key: value"
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testHyphenNestedArray()
    {
        $yaml = new YamlSpeed();
        $text =     <<< YAML
                    test:
                        -   key:
                            - applejuice
                    YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "test" => [
                [
                    "key" => [
                        "applejuice"
                    ]
                ]
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testHyphenBug()
    {
        $yaml = new YamlSpeed();
        $text =     <<< YAML
                    apiVersion: 1
                    clients:
                      - name: auth-service
                        clientId: AUTH_SERVICE_CLIENT_ID
                        clientSecret: AUTH_SERVICE_CLIENT_SECRET
                    YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "apiVersion" => 1,
            "clients" => [
                [
                    "name" => "auth-service",
                    "clientId" => "AUTH_SERVICE_CLIENT_ID",
                    "clientSecret" => "AUTH_SERVICE_CLIENT_SECRET"
                ]
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testAnchor()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key:
                    - &anchor1 item 1
                    - item 2
                    - *anchor1
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => [
                "item 1",
                "item 2",
                "item 1",
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testAnchorArray()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: &values
                    - item 1
                    - item 2
                copy: *values
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => [
                "item 1",
                "item 2",
            ],
            "copy" => [
                "item 1",
                "item 2",
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testSequence()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                values: [apple,pear,juice]
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "values" => [
                "apple",
                "pear",
                "juice"
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testSequenceEmpty()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                values: []
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "values" => []
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testSequenceWithComma()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                values: ['here a , should also parse','like a boss']
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "values" => [
                "here a , should also parse",
                "like a boss",
            ]
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testFoldedMapping()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                values: {key1: true, key2: false}
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "values" => [
                "key1" => true,
                "key2" => false,
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testFoldedMappingEmpty()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                values: { }
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "values" => [],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testFoldedMappingWithCommaOrColon()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                values: {key1: 'a string with comma, should also parse', key2: "or a : colon"}
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "values" => [
                "key1" => "a string with comma, should also parse",
                "key2" => "or a : colon",
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testFoldedMappingCustomEscape()
    {
        $yaml = new YamlSpeed();
        $yaml->addFlowMapEscape(function($input)
        {
            return replace_enclosed("{{","}}", $input, ":", "U+003A");
        });
        $text = <<<YAML
                values: {key1: {{value:that:should:parse}}}
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "values" => [
                "key1" => "{{value:that:should:parse}}",
            ],
        ];
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * testBlockTextLiteral
     *  indicated by right angle bracket '>'
     */
    public function testBlockTextFolded()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: >
                    this is line 1
                    this is line 2
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => "this is line 1 this is line 2",
        ];
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * testBlockTextLiteral
     *  indicated by pipe '|'
     */
    public function testBlockTextLiteral()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                key: |
                    this is line 1
                    this is line 2
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = [
            "key" => "this is line 1\nthis is line 2\n"
        ];
        $this->assertEquals($expectedResult, $result);
    }

    // TODO: FIX
    // public function testBlockTextReplaceNewlineWithSpaces()
    // {
    //     $yaml = new YamlSpeed();
    //     $text = <<<YAML
    //             PROPERTY: "this is line 1\nthis is line 2"
    //             YAML;
    //     $result = $yaml->parse($text);
    //     $expectedResult = array(
    //         "PROPERTY" => "this is line 1 this is line 2" // Must be the correct ouput, returns: "this is line 1this is line" 2 instead
    //     );
    //     $this->assertEquals($expectedResult, $result);
    // }

    public function testIgnoreComment()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                # This is a comment
                PROPERTY: <token;uuid4>
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = array(
            "PROPERTY" => "<token;uuid4>"
        );
        $this->assertEquals($expectedResult, $result);
    }

    public function testIgnoreCommentInArrayIndented()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                PROPERTY:
                    # This is a comment
                    - value 1
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = array(
            "PROPERTY" => 
            [
                "value 1"
            ]
        );
        $this->assertEquals($expectedResult, $result);
    }

    public function testIgnoreCommentInArraySameLevelIndent()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                PROPERTY:
                # This is a comment
                - value 1
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = array(
            "PROPERTY" => 
            [
                "value 1"
            ]
        );
        $this->assertEquals($expectedResult, $result);
    }

    public function testIgnoreCommentInSecondArrayItem()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                onStepFailureRetry:
                    script:
                        - orm create
                        # - orm import app-data.bundle -v
                    onStepFailure:
                        - exit
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = array(
            "onStepFailureRetry" => 
            [
                "script" => [
                    "orm create"
                ],
                "onStepFailure" => [
                    "exit"
                ]
            ]
        );
        $this->assertEquals($expectedResult, $result);
    }

    public function testCustomPreParser()
    {
        // Reset parsers
        YamlSpeed::$globalPreParsers = [];

        // Add parser
        YamlSpeed::addGlobalPreParser("/{{(.+?)}}(.*)/", function($matches, $result)
        {
            $key = $matches[1];
            $string = $matches[2] ? $matches[2] : "";
            return array_get_key_value($key, $result) . $string;
        });

        $yaml = new YamlSpeed();
        $text = <<<YAML
                parent:
                    url: http://www.example.com
                copy: {{parent.url}}/test
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = array(
            "parent" => [
                "url" => "http://www.example.com",
            ],
            "copy" => "http://www.example.com/test"
        );
        $this->assertEquals($expectedResult, $result);
    }

    public function testCustomPreParserNotFound()
    {
        // Reset parsers
        YamlSpeed::$globalPreParsers = [];

        // Add parser
        YamlSpeed::addGlobalPreParser("/{{(.+?)}}(.*)/", function($matches, $result)
        {
            $key = $matches[1];
            $string = $matches[2] ? $matches[2] : "";
            return array_get_key_value($key, $result, ":", false);
        });
        
        $yaml = new YamlSpeed();
        $text = <<<YAML
                parent:
                    url: http://www.example.com
                copy: {{outbound}}
                outbound: value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = array(
            "parent" => [
                "url" => "http://www.example.com",
            ],
            "copy" => null,
            "outbound" => "value"
        );
        $this->assertEquals($expectedResult, $result);
    }

    public function testCustomPostParser()
    {
        // Reset parsers
        YamlSpeed::$globalPostParsers = [];

        // Add parser
        YamlSpeed::addGlobalPostParser("/{{(.+?)}}(.*)/", function($matches, $result)
        {
            $key = $matches[1];
            return array_get_key_value($key, $result);
        });

        $yaml = new YamlSpeed();
        $text = <<<YAML
                parent:
                    url: http://www.example.com
                copy: {{outbound}}
                outbound: value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = array(
            "parent" => [
                "url" => "http://www.example.com",
            ],
            "copy" => "value",
            "outbound" => "value"
        );
        $this->assertEquals($expectedResult, $result);
    }

    public function testCustomPostParserNoWriteout()
    {
        // Reset parsers
        YamlSpeed::$globalPostParsers = [];

        // Add parser
        YamlSpeed::addGlobalPostParser("/{{(.+?)}}(.*)/", function($matches, $result, $obj)
        {
            extract($obj->getOptions());

            // 
            $key = $matches[1];
            $string = @$matches[2];
            $writeout = $obj->getOptions()["writeout"];

            if($writeout)
                return array_get_key_value($key, $result);
            else
                return sprintf("{{%s}}%s", $key, $string);
        });

        $yaml = new YamlSpeed();
        $yaml->setOption("writeout", false);
        $text = <<<YAML
                parent:
                    url: http://www.example.com
                copy: {{outbound}}/test
                outbound: value
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = array(
            "parent" => [
                "url" => "http://www.example.com",
            ],
            "copy" => "{{outbound}}/test",
            "outbound" => "value"
        );
        $this->assertEquals($expectedResult, $result);
    }

    public function testExampleYaml()
    {
        $yaml = new YamlSpeed();
        $text = <<<YAML
                invoice: 34843
                date   : 2001-01-23
                bill-to: &id001
                    given  : Chris
                    family : Dumars
                    address:
                        lines: |
                                458 Walkman Dr.
                                Suite #292
                        city    : Royal Oak
                        state   : MI
                        postal  : 48046
                ship-to: *id001
                tax  : 251.42
                total: 4443.52
                comments: >
                    Late afternoon is best.
                    Backup contact is Nancy
                    Billsmer @ 338-4338.
                YAML;
        $result = $yaml->parse($text);
        $expectedResult = array(
            "invoice" => 34843,
            "date" => "2001-01-23",
            "bill-to" => [
                "given" => "Chris",
                "family" => "Dumars",
                "address" => [
                    "lines" => "458 Walkman Dr.\nSuite #292\n",
                    "city" => "Royal Oak",
                    "state" => "MI",
                    "postal" => 48046,
                ],
            ],
            "ship-to" => [
                "given" => "Chris",
                "family" => "Dumars",
                "address" => [
                    "lines" => "458 Walkman Dr.\nSuite #292\n",
                    "city" => "Royal Oak",
                    "state" => "MI",
                    "postal" => 48046,
                ],
            ],
            "tax" => 251.42,
            "total" => 4443.52,
            "comments" => "Late afternoon is best. Backup contact is Nancy Billsmer @ 338-4338.",
        );
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * getBenchMarkTime
     */
    private function getBenchMarkTime(string $text, callable $method)
    {
        $i = 0;
        $max = 500;
        $time = 0;
        while($i < $max)
        {
            $start = microtime(true);
            $method($text);
            $end = microtime(true);
            $time += ($end-$start);
            $i++;
        }
        return $time / $max;
    }

    protected function tearDown() : void
    {
        $this->restore();
    }
}