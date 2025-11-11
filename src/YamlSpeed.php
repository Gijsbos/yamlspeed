<?php
declare(strict_types=1);

namespace YamlSpeed;

use YamlSpeed\YamlSpeedException;

/**
 * YamlSpeed
 */
class YamlSpeed
{
    // PARSING REGEXP
    const KEY_VALUE_REGEXP = "/^(?=(\"|')[^{].+?\\1(?=: |:$)|[^{\"'].+?(?=: |:$))(.+?)(?=: |:$):(.*)$/";

    // VALUE RESOLVING REGEXP
    const ENV_REGEXP = "/(.*)\\$\_ENV\[('|\")?(.+?)\\2?\](.*)/";
    const FILE_REGEXP = "/\\\$_FILE\[(.+?)\]/";

    // Custom parsers
    public static $globalPreParsers = [];
    public static $globalPostParsers = [];

    // Parse variables
    private bool $debug;
    private null|string $indentationUnit;
    private null|int $indents;
    private array $root;
    private array $current;
    private array $lines;
    private int $offset;
    private array $anchors;

    // Parser vars
    private null|string $quoteType;

    // Parsing options
    private bool $useCache;
    private null|string $cacheFolder;
    private string $cacheFileName;
    private bool $parseConstants;
    private bool $parseEnvVars;
    private bool $parseYamlFiles;
    private array $options;

    // Post/pre parsers
    public array $customFlowMapEscape = [];
    public array $customFlowMapUnEscape = [];
    private array $preParsers;
    private array $postParsers;

    /**
     * __construct
     */
    public function __construct(array $options = [])
    {
        $this->debug = false;
        $this->indentationUnit = null;
        $this->indents = null;
        $this->current = [];
        $this->root = &$this->current;
        $this->lines = [];
        $this->offset = 0;
        $this->anchors = [];

        // Parser vars
        $this->quoteType = null;

        // Options/flags
        $this->useCache = $this->arrayHasOption("useCache", $options, false);
        $this->cacheFolder = $this->arrayOption("cacheFolder", $options, null);
        $this->cacheFileName = $this->arrayOption("cacheFileName", $options, ".yamlspeed.cache");
        $this->parseConstants = $this->arrayHasOption("parseConstants", $options, false);
        $this->parseEnvVars = $this->arrayHasOption("parseEnvVars", $options, false);
        $this->parseYamlFiles = $this->arrayHasOption("parseYamlFiles", $options, false);
        $this->options = $options;

        // Custom settings
        $this->customFlowMapEscape = [];
        $this->customFlowMapUnEscape = [];
        $this->preParsers = [];
        $this->postParsers = [];
    }

    /**
     * arrayHasOption
     */
    private function arrayHasOption(string $key, null|array $array = null, bool $default = false) : bool
    {
        if($array === null)
            return $default;

        if(array_key_exists($key, $array))
        {
            if(is_bool($array[$key]))
                return $array[$key];
            else
                return true;
        }
        else if(in_array($key, $array, true))
            return true;
        else
            return $default;
    }

    /**
     * arrayOption
     */
    private function arrayOption(string $key, null|array $array = null, $defaultValue = false, $throws = null)
    {
        if($array === null)
        {
            if($throws !== null)
                throw new $throws;

            return $defaultValue;
        }

        if(!array_key_exists($key, $array))
        {
            if($throws !== null)
                throw new $throws;

            return $defaultValue;
        }

        return $array[$key];
    }

    /**
     * initIndentationUnit
     */
    private function initIndentationUnit(string $text)
    {
        if(preg_match("/^\s+/m", $text, $matches))
        {
            $this->indentationUnit = $matches[0];
        }
        else
        {
            $this->indentationUnit = "  ";
        }
    }

    /**
     * getCacheFolder
     */
    public function getCacheFolder()
    {
        return $this->cacheFolder;
    }

    /**
     * setCacheFolder
     */
    public function setCacheFolder(string $folderPath) : void
    {
        $this->cacheFolder = $folderPath;
    }

    /**
     * setCacheFileName
     */
    public function setCacheFileName(string $fileName)
    {
        $this->cacheFileName = $fileName;
    }

    /**
     * setUseCache
     */
    public function setUseCache(bool $useCache)
    {
        $this->useCache = $useCache;
    }

    /**
     * setParseConstants
     */
    public function setParseConstants(bool $parseConstants)
    {
        $this->parseConstants = $parseConstants;
    }

    /**
     * setParseEnvVars
     */
    public function setParseEnvVars(bool $parseEnvVars)
    {
        $this->parseEnvVars = $parseEnvVars;
    }

    /**
     * setParseYamlFiles
     */
    public function setParseYamlFiles(bool $parseYamlFiles)
    {
        $this->parseYamlFiles = $parseYamlFiles;
    }

    /**
     * setOption
     */
    public function setOption(string $key, $value)
    {
        $this->options[$key] = $value;
    }

    /**
     * setOptions
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * getOptions
     */
    public function getOptions() : array
    {
        return $this->options;
    }

    /**
     * setRoot
     */
    public function setRoot(array $root)
    {
        $this->root = $root;
    }

    /**
     * addPreParser
     */
    public function addPreParser(string $regexp, callable $function) : void
    {
        $this->preParsers[] = [
            "regexp" => $regexp,
            "function" => $function,
        ];
    }

    /**
     * addGlobalPreParser
     */
    public static function addGlobalPreParser(string $regexp, callable $function) : void
    {
        self::$globalPreParsers[] = [
            "regexp" => $regexp,
            "function" => $function,
        ];
    }

    /**
     * getPreParsers
     */
    public function getPreParsers()
    {
        return array_merge(self::$globalPreParsers, $this->preParsers);
    }

    /**
     * addPostParser
     */
    public function addPostParser(string $regexp, callable $function) : void
    {
        $this->postParsers[] = [
            "regexp" => $regexp,
            "function" => $function,
        ];
    }

    /**
     * addGlobalPostParser
     */
    public static function addGlobalPostParser(string $regexp, callable $function) : void
    {
        self::$globalPostParsers[] = [
            "regexp" => $regexp,
            "function" => $function,
        ];
    }

    /**
     * getPostParsers
     */
    public function getPostParsers()
    {
        return array_merge(self::$globalPostParsers, $this->postParsers);
    }

    /**
     * getRoot
     */
    public function getRoot() : array
    {
        return $this->root;
    }

    /**
     * getIndents
     */
    private function getIndents(string|array $indentation) : int
    {
        // Extract indentation from array
        if(is_array($indentation))
            $indentation = $indentation[1];

        // Remove newline
        $indentation = str_starts_with($indentation, "\n") ? substr($indentation, 1) : $indentation;

        // Calculate indents
        return substr_count($indentation, $this->indentationUnit);
    }

    /**
     * Returns the difference as negative or positive value
     */
    public function getNextLineIndentDelta()
    {
        $this->next(); // Peek ahead
        if(!$this->valid())
        {
            $this->prev(); // Restore position
            return false;
        }
        $line = $this->current();
        $nextIndents = $this->getIndents($line);
        $this->prev(); // Restore position
        return $nextIndents-$this->indents;
    }

    /**
     * isTextFoldedSymbol
     */
    private function isTextFoldedSymbol(string $input) : bool
    {
        return str_starts_with(trim($input), ">");
    }

    /**
     * isTextLiteralSymbol
     */
    private function isTextLiteralSymbol(string $input) : bool
    {
        return str_starts_with(trim($input), "|");
    }

    /**
     * isAnchorSymbol
     */
    private function isAnchorSymbol(string $input) : bool
    {
        return str_starts_with(trim($input), "&");
    }

    /**
     * isAnchorReferenceSymbol
     */
    private function isAnchorReferenceSymbol(string $input) : bool
    {
        return str_starts_with(trim($input), "*");
    }

    /**
     * stringHasOpenButNoCloseQuote
     */
    private function stringHasOpenButNoCloseQuote($input) : bool
    {
        $input = trim($input);

        // Get quote type
        $quoteType = str_starts_with($input, '"') ? '"' : (str_starts_with($input, "'") ? "'" : false);

        // Found
        if($quoteType !== false)
        {
            $hasOpenButNoCloseQuote = strlen($input) == 1 ? true : !str_ends_with($input, $quoteType);
            
            // Set quoteType
            if($hasOpenButNoCloseQuote && $this->quoteType === null)
                $this->quoteType = $quoteType;

            // Return result
            return $hasOpenButNoCloseQuote;
        }
        else
            return false;
    }

    /**
     * stringHasCloseQuote
     */
    private function stringHasCloseQuote(string $input) : bool
    {
        if($this->quoteType === null)
            throw new YamlSpeedException("Cannot look for close quote, reading never started");

        $stringHasCloseQuote = str_ends_with($input, $this->quoteType);

        if($stringHasCloseQuote)
            $this->quoteType = null;

        return $stringHasCloseQuote;
    }

    /**
     * parseEnv
     */
    private function parseEnv(string $input)
    {
        return preg_replace_callback("/\\$\{.+?\}/", function($match) use ($input)
        {
            // Extract key from matching string
            $string = $match[0];
            $key = substr($string, 2, strlen($string) - 3);

            // Get value
            $value = env($key);

            // Return
            return $value === false ? "\${$key}" : $value;
        }, $input);
    }

    /**
     * extractAnchor
     */
    private function extractAnchorDetails(string $input)
    {
        $input = trim($input);
        
        if(is_string($input) && preg_match("/^(&|\*)([\w\_\-]+)(.*)/", $input, $matches) == 1)
        {
            return [
                "type" => $matches[1],
                "key" => $matches[2],
                "value" => trim($matches[3]),
            ];
        }

        return false;
    }

    /**
     * getAnchor
     */
    private function getAnchor(string $input)
    {
        $extract = $this->extractAnchorDetails($input);

        if($extract !== false)
        {
            extract($extract);

            if(array_key_exists($key, $this->anchors))
                return $this->anchors[$key];
        }

        return false;
    }

    /**
     * storeAnchor
     */
    private function storeAnchor(string $input, &$value = null)
    {
        if($this->isAnchorSymbol($input) && ($extract = $this->extractAnchorDetails($input)) !== false)
        {
            $anchorType = $extract["type"];
            $anchorName = $extract["key"];
            $anchorValue = $extract["value"];

            // Store anchor
            // If the anchorValue contains a string, we assume the anchor is used for text
            if(is_string($anchorValue) && strlen($anchorValue))
            {
                $this->anchors[$anchorName] = $value = $anchorValue;
            }
            else
            {
                $this->anchors[$anchorName] = &$value;
            }
        }

        // Return value
        return $value;
    }

    /**
     * isSequence
     */
    private function isSequence(string $input)
    {
        return is_string($input) && strlen($input) >= 2 && $input[0] == "[" && $input[strlen($input) - 1] == "]";
    }

    /**
     * escapeSequenceString
     */
    private function escapeSequenceString(string $input)
    {
        $input = replace_enclosed_quotes($input, ',', 'U+002C');
        $input = replace_enclosed("{", "}", $input, ',', 'U+002C');
        return $input;
    }

    /**
     * unEscapeSequenceString
     */
    private function unEscapeSequenceString(string $input)
    {
        $input = str_replace('U+002C', ',', $input);
        return $input;
    }

    /**
     * parseSequenceString
     */
    private function parseSequenceString(string $input)
    {
        $result = [];

        // Replace quotes for unicode code for quote
        $input = $this->escapeSequenceString($input);
        
        // Need to add trailing comma for pattern
        $explode = explode(',', $input);

        // Iterate over explode
        foreach($explode as $i => $fragment)
        {
            $fragment = $this->unEscapeSequenceString(trim($fragment));

            // Parse value
            $result[$i] = $this->parseYamlValue($fragment);
        }

        //
        return $result;
    }

    /**
     * parseSequence
     */
    public function parseSequence(string &$input) : array
    {
        // Unescape colons here
        $inlineArray = trim(substr($input, 1, strlen($input) - 2));
        
        // Check if there is content in the literal array
        if(strlen($inlineArray) === 0)
            return array();
        else
            return $this->parseSequenceString($inlineArray);
    }

    /**
     * isFlowMapping
     */
    private function isFlowMapping(string $input) : bool
    {
        return preg_match("/^(?!\{{2})\{.*?(?!\}{2})\}$/s", $input) == 1;
    }

    /**
     * addFlowMapEscape
     */
    public function addFlowMapEscape($function)
    {
        $this->customFlowMapEscape[] = $function;
    }

    /**
     * escapeFlowMappingString
     */
    private function escapeFlowMappingString(string $input)
    {
        $input = replace_enclosed_quotes($input, ',', 'U+002C');
        $input = replace_enclosed_quotes($input, ':', 'U+003A');
        $input = replace_enclosed("[", "]", $input, ',', 'U+002C');
        $input = replace_enclosed("[", "]", $input, ':', 'U+003A');
        $input = replace_enclosed("{", "}", $input, ',', 'U+002C');
        $input = replace_enclosed("{", "}", $input, ':', 'U+003A');

        foreach($this->customFlowMapEscape as $function)
        {
            $input = $function($input);
        }

        return $input;
    }

    /**
     * addFlowMapUnEscape
     */
    public function addFlowMapUnEscape($function)
    {
        $this->customFlowMapUnEscape[] = $function;
    }

    /**
     * unEscapeFlowValue
     */
    private function unEscapeFlowValue(string $input)
    {
        $input = str_replace('U+002C', ',', $input);
        $input = str_replace('U+003A', ':', $input);

        foreach($this->customFlowMapUnEscape as $function)
        {
            $input = $function($input);
        }

        return $input;
    }

    /**
     * parseFlowMapString
     */
    private function parseFlowMapString(string $input)
    {
        $result = [];

        // Replace quotes for unicode code for quote
        $input = $this->escapeFlowMappingString($input);
        
        // Need to add trailing comma for pattern
        $explode = explode(',', $input);

        // Iterate over explode
        foreach($explode as $fragment)
        {
            $explode = explode(":", trim($fragment));
            $key = trim($explode[0]);
            $value = @$explode[1];

            // Value not set
            if(!is_string($value))
                throw new YamlSpeedException("Parse error near line ".$this->offset." for key '$key' with value of type " . get_type($value));

            // Un escape
            $value = $this->unEscapeFlowValue(trim($value));
            
            // Parse value
            $result[$key] = $this->parseYamlValue($value);
        }

        //
        return $result;
    }

    /**
     * parseFlowMap
     */
    public function parseFlowMap(string &$input) : array
    {
        // Unescape colons here
        $inlineArray = trim(substr($input, 1, strlen($input) - 2));

        // Check if there is content in the literal array
        if(strlen($inlineArray) === 0)
            return array();
        else
            return $this->parseFlowMapString($inlineArray);
    }

    /**
     * applyCustomPreParsers
     */
    private function applyCustomPreParsers($input)
    {
        foreach($this->getPreParsers() as $parser)
        {
            $regexp = $parser["regexp"];
            $function = $parser["function"];

            if(preg_match($regexp, $input, $matches))
            {
                return $function($matches, $this->root, $this);
            }
        }

        return false;
    }

    /**
     * parseYamlFiles
     */
    private function parseYamlFiles(string $filePath)
    {
        // Check file
        if(!is_file($filePath))
            throw new YamlSpeedException("Import yaml file '$filePath' failed, file not found");

        // Check file
        if(!str_ends_with($filePath, ".yaml"))
            throw new YamlSpeedException("Import yaml failed, file '$filePath' is not a yaml file");

        // Called class
        $calledClass = get_called_class();

        // Parse yaml
        $parser = new $calledClass();

        // Parse
        $importData = $parser->parseFile($filePath);
        
        // Return data
        return $importData;
    }

    /**
     * parseYamlValue
     * @param array $parent - Used for anchors
     */
    public function parseYamlValue($input, &$parent = null)
    {
        $input = trim($input);

        // Apply custom parsing
        $customResult = $this->applyCustomPreParsers($input);

        // Return custom result
        if($customResult !== false)
            return $customResult;

        // Flags
        $isWrappedInQuotes = is_wrapped_in_quotes($input);

        // Use default parsers
        switch(true)
        {
            // True
            case !$isWrappedInQuotes && strtolower($input) == 'true':
                return true;

            // False
            case !$isWrappedInQuotes && strtolower($input) == 'false':
                return false;

            // Null
            case !$isWrappedInQuotes && strtolower($input) == 'null':
                return null;

            // Numeric
            case !$isWrappedInQuotes && is_numeric($input):
                return typecast($input);

            // Constant
            case !$isWrappedInQuotes && $this->parseConstants && defined($input):
                return constant($input);

            case !$isWrappedInQuotes && $this->parseEnvVars && str_starts_ends_with($input, '${', '}'):
                return $this->parseEnv($input);

            // Anchor '&'
            case $this->isAnchorSymbol($input):
                return $this->storeAnchor($input, $parent);

            // Anchor reference '*'
            case $this->isAnchorReferenceSymbol($input) !== false:
                return $this->getAnchor($input);

            // Array [item1, item2]
            case $this->isSequence($input):
                return $this->parseSequence($input);

            // Map {key: value, key2: value2}
            case $this->isFlowMapping($input):
                return $this->parseFlowMap($input);

            // Default
            default:

                // Unwrap and rebuild newlines
                if($isWrappedInQuotes)
                {
                    // Unescape quotes
                    $input = str_replace("''", "'", $input);
                    $input = str_replace('\"', '"', $input);

                    // Remove quotes
                    $input = unwrap_quotes($input);
                }

                // Proceed
                switch(true)
                {
                    // FILE
                    case preg_match(self::FILE_REGEXP, $input, $matches) == 1:
                        
                        // Get filePath
                        $filePath = is_wrapped_in_quotes($matches[1]) ? unwrap_quotes($matches[1]) : $matches[1];

                        // Process file
                        switch(true)
                        {
                            case str_ends_with($filePath, ".yaml") && $this->parseYamlFiles:
                                return $this->parseYamlFiles($filePath);
                        }
                    
                    // Return input as is
                    default:
                        return $input;
                }
        }
    }

    /**
     * valid
     */
    private function valid()
    {
        return array_key_exists($this->offset, $this->lines);
    }

    /**
     * current
     */
    private function current()
    {
        return @$this->lines[$this->offset];
    }

    /**
     * key
     */
    private function key()
    {
        return $this->offset;
    }

    /**
     * next
     */
    private function next()
    {
        $this->offset += 1;
    }

    /**
     * prev
     */
    private function prev()
    {
        $this->offset -= 1;
    }

    /**
     * add
     */
    private function add(int $offset, $value)
    {
        $pre = array_slice($this->lines, 0, $offset);
        $post = array_slice($this->lines, $offset);

        array_push($pre, $value);
        array_push($pre, ...$post);

        // Update lines
        $this->lines = $pre;
    }

    /**
     * delete
     */
    private function delete(int $offset)
    {
        $pre = array_slice($this->lines, 0, $offset);
        $post = array_slice($this->lines, $offset+1);

        array_push($pre, ...$post);

        $this->lines = $pre;
    }

    /**
     * readTextOverMultipleLines
     *  Reads text that has been spread over multiple lines because of the newline character
     */
    private function readTextOverMultipleLines(string $value)
    {
        $value = $value;

        // Get next line
        $this->next();

        // No close quote, we go looking for it
        while($this->valid())
        {
            $textLine = $this->current();

            // Add line
            $value .= "$textLine[1]$textLine[2] ";

            // Stop
            if($this->stringHasCloseQuote(rtrim($textLine[2])))
                break;

            // Get next line
            $this->next();
        }

        // Remove quotes
        return unwrap_quotes($value);
    }

    /**
     * readFoldedText
     */
    private function readFoldedText()
    {
        // Consume inside
        $value = "";

        // Get next line
        $this->next();

        // Continue while lines
        while($this->valid())
        {
            $textLine = $this->current();

            // Keep track of lineIndent, when it equals the current line indent, stop
            $lineIndent = $this->getIndents($textLine);

            // Stop when line indents come back
            if($lineIndent <= $this->indents)
            {
                $this->prev(); // Go back one line as we delved into a non-text block line
                break;
            }
            
            // Add line
            if(strlen($value))
                $value .= " $textLine[2]";
            else
                $value = "$textLine[2]";

            // Get next line
            $this->next();
        }

        return $value;
    }

    /**
     * readLiteralText
     */
    private function readLiteralText()
    {
        // Consume inside
        $value = "";

        // Get next line
        $this->next();
        
        // Continue while lines
        while($this->valid())
        {
            $textLine = $this->current();

            // Keep track of lineIndent, when it equals the current line indent, stop
            $lineIndent = $this->getIndents($textLine);

            // Stop when line indents come back
            if($lineIndent <= $this->indents)
            {
                $this->prev(); // Go back one line as we delved into a non-text block line
                break;
            }
            
            // Add line
            $value .= "$textLine[2]\n";

            // Get next line
            $this->next();
        }

        return $value;
    }

    /**
     * nextLineStartsWith
     */
    private function nextLineStartsWith(string $symbol)
    {
        $this->next();
        if(!$this->valid())
        {
            $this->prev();
            return false;
        }
        $current = $this->current();
        $this->prev();
        return str_starts_with(ltrim($current[2]), $symbol);
    }

    /**
     * nextLineIsHyphenArray
     */
    private function nextLineIsHyphenArray()
    {
        return $this->nextLineStartsWith("-");
    }

    /**
     * nextLineIsComment
     */
    private function nextLineIsComment()
    {
        return $this->nextLineStartsWith("#");
    }

    /**
     * increaseIndentAtOffset
     */
    private function increaseIndentAtOffset(int $offset)
    {
        $this->lines[$offset][0] = $this->indentationUnit.$this->lines[$offset][0];
        $this->lines[$offset][1] = $this->indentationUnit.$this->lines[$offset][1];
    }

    /**
     * lookAheadAndCorrectSameLevelHyphenItemIndentation
     * 
     *  Only hyphen inline arrays are allowed to have 'same level' indentation with its parent:
     * 
     *  key:
     *      - value 1
     *      - value 2
     * 
     *  equals 'same level with parent' indentation =>
     * 
     *  key:
     *  - value 1
     *  - value 2
     *  
     *  The YamlSpeed works really well because it follows the increase with one indent logic.
     *  That is why creating 'exception' logic to handle this situation is tedious and requires a lot of adjustments in code.
     *  It is far more easy to look ahead in the upcoming yaml to see if next lines are 'same level with parent' indentations.
     *  This method looks ahead in lines to see if next lines are hyphen array items and if they are on the same level of indentation.
     *  Lines will be changed and receive an additional indent.
     */
    private function lookAheadAndCorrectSameLevelHyphenItemIndentation(int $baseOffset)
    {
        $baseIndent = $this->getIndents($this->current());

        $this->debug && printf("\n=> Look ahead and correct hyphen item indentation using base indentation: %s", cli_color("$baseIndent", "light_cyan"));

        $increaseIndentationAtIndents = null;

        // Scan ahead in lines from the current line until lines stop (null) or the indentation is smaller than the baseIndentation
        while(($current = $this->current()) !== null && ($indents = $this->getIndents($current)) >= $baseIndent)
        {
            $this->debug && printf("\n - Evaluating line: %s", cli_color($current[2], "light_cyan"));

            // Get current offset
            $offset = $this->key();

            // Delete when comment
            if($current[2][0] == "#")
            {
                $this->delete($offset);
                continue;
            }

            // Found hypen line
            $indentDelta = $baseIndent - $indents;

            // At base indentation
            if($indentDelta == 0)
            {
                if($current[2][0] == "-") // Indent delta must be 0, meaning we only increase hyphens that are at the same level as the parent
                {
                    $this->debug && printf("\n + Found hyphen! Start increasing indentation.");
                    $increaseIndentationAtIndents = $indents;
                }
                else
                {
                    $this->debug && printf("\n + Non-hyphen line, stop increasing indents.");
                    $increaseIndentationAtIndents = null;
                }
            }

            // Is increasing indents and the current line is equal or larger than the increaseIndentationAtIndents
            if($increaseIndentationAtIndents !== null && $indents >= $increaseIndentationAtIndents)
            {
                $this->debug && printf("\n - Increasing line %s, at offset: %s", cli_color($current[2], "light_cyan"), cli_color("$offset", "light_cyan"));
                $this->increaseIndentAtOffset($offset);
            }

            // Start next iteration
            $this->next();
        }

        // Reset to base
        $this->offset = $baseOffset;
    }

    /**
     * recursive
     * 
     *  Walk through the yaml recursively as if it were a 'tree' that we can traverse.
     *  The SplDoublyLinkedList allows us to create a lineary list that we can walk over and create the tree looking ahead to the next line.
     *  When indents increase we call the recursion with the new line, passing down the previous tuple as parent.
     *  This makes sure that every 'depth' of the tree has a parent it can 'append' values to.
     *  If the indents go back again, we need to make sure to 'break' out of the current 'depth' by returning a value.
     *  The value returned allows the recusive function to signal its parent recursive function whether we need to traverse further down in the tree for the next line.
     *  The return value thus can be any negative number (jump back, stops the parent recursive function proceeding to its parent until it reaches depth 0).
     * 
     */
    public function recursive(null|array &$parent = null)
    {
        $localIteration = 0;

        // Iterate over list
        while($this->valid())
        {
            // Get line info
            $line = $this->current();
            $offset = $this->key();
            $input = $line[2];

            // Get current indents
            $this->indents = $this->getIndents($line);

            // Print
            $this->debug && printf("\nLine %d - Indent: %d, Iteration: %d, Input -> %s", $offset, $this->indents, $localIteration, cli_color($input, 'light_cyan'));

            /**
             * FIRST PARSE CURRENT LINE VALUE
             *  Results in a key/value and suggestion for how to proceed
             */
            $key = null;
            $value = null;
            $skip = false;

            /**
             * COMMENTS
             */
            if(str_starts_with($input, "#"))
            {
                $skip = true;
            }

            /**
             * LINE ARRAY
             */
            else if(str_starts_with($input, "- "))
            {
                $this->debug && printf("\n- parse as: %s", cli_color("line array", "light_cyan"));

                // Get value
                $value = ltrim(substr($input, 2));

                // If a value has been set e.g. - key: value, we want to parse its value.
                // We do so by 'inserting' an imaginary line that will be parsed next
                if(strlen($value) > 0)
                {
                    // Value is key/value, we add an imaginary line (MAGIC!!! >:-)
                    if(preg_match(self::KEY_VALUE_REGEXP, $value))
                    {
                        // Create the imaginary line
                        $imaginaryLine = [
                            $line[1].$this->indentationUnit.$value, // Set the value
                            $this->indentationUnit.$line[1], // Add another level off indentation to the current indentation
                            $value, // Set value
                        ];

                        // Add to lines
                        $this->add($offset+1, $imaginaryLine);
                    }
                    else
                    {
                        $value = $this->parseYamlValue($value);
                    }
                }
            }

            /**
             * KEY VALUE PAIR
             */
            else if(preg_match(self::KEY_VALUE_REGEXP, $input, $matches))
            {   
                $key = trim(is_wrapped_in_quotes($matches[2]) ? unwrap_quotes($matches[2]) : $matches[2]);
                $value = ltrim($matches[3]);

                // Root!
                if(strlen($value) == 0)
                {
                    $this->debug && printf("\n- parse as: %s", cli_color("key root", "light_cyan"));

                    // Value inits at NULL
                    $value = null;

                    // We look ahead for hyphen array items on the same level as the key root
                    // We correct these hyphens by increasing their indents with one level.
                    $this->lookAheadAndCorrectSameLevelHyphenItemIndentation($offset);
                }

                // Key value
                else
                {
                    $this->debug && printf("\n- parse as: %s", cli_color("key value", "light_cyan"));

                    // When a property value only has an opening quote and not a closing quote
                    // newlines have been used in the definition
                    // We consume lines until we find the closing quote
                    if($this->stringHasOpenButNoCloseQuote($value))
                        $value = $this->readTextOverMultipleLines($value);

                    // Start text folded
                    else if($this->isTextFoldedSymbol($value))
                        $value = $this->readFoldedText();

                    // Start text literal
                    else if($this->isTextLiteralSymbol($value))
                        $value = $this->readLiteralText($value);
                    
                    // Default values
                    else
                        $value = $this->parseYamlValue($value, $parent[$key]);
                }
            }

            /**
             * NOW DO STUFF WITH THE INFORMATION PROVIDED!
             */
            $nextLineIndentDelta = $this->getNextLineIndentDelta();

            // If the next line indent exceeds 1, it is illigal
            if($nextLineIndentDelta > 1)
            {
                $this->next();
                $nextLine = $this->current();
                throw new YamlSpeedException("Invalid indentation near '$nextLine[0]'");
            }
            
            /**
             * We set the key and value here.
             * If we go up with indentations, we naturally need to create a new tuple that will become the new parent
             */
            if(!$skip)
            {
                if($nextLineIndentDelta == 1)
                {
                    // Create new parent
                    $array = [];
                                    
                    // Get a key
                    $key = $key !== null ? $key : (is_array($parent) ? count($parent) : 0);
    
                    // Key set, apply it
                    $parent[$key] = $array;
    
                    // Create a new parent
                    $newParent = &$parent[$key];
                }
                else
                {
                    // Proceed as default
                    if($key !== null)
                    {
                        $parent[$key] = $value;
                    }
                    else
                    {
                        $parent[] = $value;   
                    }
                }
            }
            
            /**
             * Manage the recursion by starting a new one, remaining or quiting the current recursion
             */
            $this->next();

            // EOD - False means there are no more lines to parse
            if($nextLineIndentDelta === false)
            {
                // Continue will reach the while loop and cancel at lines->valid()
                continue;
            }

            // Up: Call new recursion with new parent thus create a new array
            if($nextLineIndentDelta == 1)
            {
                $this->debug && printf("\n- Next line is %d - %s", $nextLineIndentDelta, cli_color("UP", "light_yellow"));

                // Start recursion, and when it finishes, proceed on the same indentation level when signal equals 0, if it is negative we stop
                $depthSignalFromChildRecursion = $this->recursive($newParent);

                // Stop recursion
                if($depthSignalFromChildRecursion < 0)
                    return $depthSignalFromChildRecursion+1;
            }

            // Remain: Start next iteration on the current execution depth
            else if($nextLineIndentDelta == 0)
            {
                $this->debug && printf("\n- Next line is %d - %s", $nextLineIndentDelta, cli_color("REMAIN", "light_green"));
            }

            // Down: Quit current recursion
            else
            {
                $this->debug && printf("\n- Next line is %d - %s", $nextLineIndentDelta, cli_color("DOWN", "light_purple"));
                
                // Here we signal the parent recursion function whether it should stop executing if the value is negative.
                return $nextLineIndentDelta+1;
            }

            $localIteration += 1;
        }

        // No more iterations left, quit this recursion at current depth!
        return 0;
    }

    /**
     * applyCustomPostParsers
     */
    public function applyCustomPostParsers()
    {
        $copy = $this->root;

        foreach($this->getPostParsers() as $parser)
        {
            array_walk_recursive($copy, function(&$value, $key) use ($parser, &$copy)
            {
                if(is_string($value))
                {
                    $regexp = $parser["regexp"];
                    $function = $parser["function"];

                    if(preg_match($regexp, $value, $matches))
                    {
                        $value = $function($matches, $copy, $this);
                    }
                }
            });
        }

        return $copy;
    }

    /**
     * parse
     */
    public function parse(string $text)
    {
        // Set indentation unit
        $this->initIndentationUnit($text);

        // Match lines
        preg_match_all("/^([\s\t]*)(.*)/m", $text, $this->lines, PREG_SET_ORDER);

        // Replace spacing characters for newline symbols
        $this->lines = array_map(function($line) {
            $line = str_replace('\n', "\n", $line);
            $line = str_replace('\t', "\t", $line);
            return $line;
        }, $this->lines);

        // Do recursion
        $this->recursive($this->current);

        // Clear references
        unset($this->anchors);

        // Apply post parsers
        $this->root = $this->applyCustomPostParsers($this->root);

        // Return
        return $this->root;
    }

    /**
     * getExistingCacheFiles
     */
    private function getExistingCacheFiles(string $fileName)
    {
        if(!is_dir($this->cacheFolder))
            return [];

        return preg_grep("/^$fileName/", scandir($this->cacheFolder));
    }

    /**
     * removeOldCacheFiles
     */
    private function removeCacheFiles(string $fileName)
    {
        $files = $this->getExistingCacheFiles($fileName);

        foreach($files as $file)
            unlink($this->cacheFolder."/".$file);
    }

    /**
     * readFromCache
     */
    private function readFromCache(string $filePath)
    {
        // Get path hash
        $filePathHash = hash('xxh3', $filePath);

        // Get file hash
        $hash = hash_file('xxh3', $filePath);

        // Get cache file
        $cacheFileName = $this->cacheFileName . ".$filePathHash";

        // Get  path
        $cacheFilePath = $this->cacheFolder . "/$cacheFileName";

        // Look if file is present
        $cacheFilePathWithHash = "$cacheFilePath.$hash";

        // Get dir name
        $dirname = dirname($cacheFilePathWithHash);

        // Verify dir
        if(!is_dir($dirname))
            $hasDirectory = mkdir($dirname, 0777, true);
        else
            $hasDirectory = true;

        // Directory missing
        if(!$hasDirectory)
            throw new YamlSpeedException("Could not read cache directory $dirname");
        
        // Load from cache
        if(is_file($cacheFilePathWithHash))
        {
            return unserialize(file_get_contents($cacheFilePathWithHash));
        }
        else
        {
            // Remove old cache files
            $this->removeCacheFiles($cacheFileName);

            // Parse file
            $data = $this->parse(file_get_contents($filePath));

            // Store in cache file
            file_put_contents($cacheFilePathWithHash, serialize($data));

            // Return data
            return $data;
        }
    }

    /**
     * file
     */
    public function file(string $filePath) : array
    {
        if(!is_file($filePath))
            throw new YamlSpeedException("Yaml parser failed, could not locate file '$filePath'");

        // See if cache is enabled
        if($this->useCache && $this->cacheFolder !== null)
            return $this->readFromCache($filePath);
        else
            return $this->parse(file_get_contents($filePath));
    }

    /**
     * parseText
     */
    public static function parseText(string $input, array $options = []) : array
    {
        $calledClass = get_called_class();

        // Call called class, allowing inheritance
        return (new $calledClass($options))->parse($input);
    }

    /**
     * parseFile
     */
    public static function parseFile(string $filePath, array $options = []) : array
    {
        $calledClass = get_called_class();

        // Call called class, allowing inheritance
        return (new $calledClass($options))->file($filePath);
    }

    /**
     * extractYamls
     */
    private static function extractYamls(string $content)
    {
        // Get lines
        $lines = explode("\n", $content);

        // Create vars
        $yamls = [];
        $current = [];

        // Start creating yamls
        foreach($lines as $line)
        {
            if(str_starts_with($line, "---"))
            {
                $yamls[] = implode("\n", $current);
                $current = [];
            }
            else
            {
                $current[] = $line;
            }
        }

        // Add last
        $yamls[] = implode("\n", $current);

        // Return yamls
        return $yamls;
    }

    /**
     * parseMultiFile
     */
    public static function parseMultiFile(string $filePath, array $options = []) : array
    {
        $calledClass = get_called_class();

        // Verify file
        if(!is_file($filePath))
            throw new YamlSpeedException("Yaml parser failed, could not locate file '$filePath'");

        // Get content
        $fileContent = file_get_contents($filePath);

        // Get yamls
        $yamls = self::extractYamls($fileContent);

        // Split
        foreach($yamls as $fragment)
        {
            $results[] = (new $calledClass($options))->parse($fragment);
        }
        
        // Return all
        return $results;
    }
}