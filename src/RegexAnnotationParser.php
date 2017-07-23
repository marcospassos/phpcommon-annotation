<?php

namespace PhpCommon\Annotation;

class RegexAnnotationParser
{
    private const ANNOTATION_REGEX =
        '/
            (?P<annotation>
                @(?<name>%s)
                (?:
                    [\s\*]* (
                        \((?<args>(?:[^()]|(?3)|\()*)\) |
                        (?P<malformed>[^@]*\()
                    ) |
                    # Unsupported annotation
                    (?P<unsupported>([^\S\r\n]*\S+[^\S\r\n]*)+\n) |
                    # Match argument absence 
                    (?=[^\S\r\n]*\n)
                )
            )
        /imx';

    private const CLASS_PATTERN =
        '(?:[a-z_\x7f-\xff][a-z0-9_\x7f-\xff]*\\\\)*' .
        '[a-z_\x7f-\xff][a-z0-9_\x7f-\xff]*';

    private const VALUE_PATTERN =
        '(?P<array>\{([^{}]|(?3)|{)*\}) |
        (?P<string>"(?:[^"\\\\]|\\.)*") |
        (?P<number>[0-9]+\.[0-9]+|[1-9][0-9]*) |
        (?P<boolean>true|false) |
        (?P<annotation>
            @(?<name>' .self::CLASS_PATTERN. ')
            (?:[\s\*]*(\((?<args>(?:[^()]|(?11)|\()*)\)))?
        )';

    private const ENTRIES_REGEX =
        '/
            [\s\*]*(?!,)
            (?:"(?P<key>(?:[^"\\\\]|\\.)*)"[\s\*]*=[\s\*]*)?
            (?P<value>' . self::VALUE_PATTERN. ')
            [\s\*]*(?:,|,?$)
        /Axi';

    private const MEMBERS_REGEX =
        '/
            [\s\*]*
            (
                (?!,)(?:(?P<member>[a-z]+)[\s\*]*=[\s\*]*)?
                (?P<value>' . self::VALUE_PATTERN. ')
                [\s\*]*(?:,|,?$)
            )
        /Axi';


    private $factory;
    private $context;
    private $position;
    private $docBlock;

    public function __construct(AnnotationFactory $factory, Context $context)
    {
        $this->factory = $factory;
        $this->context = $context;
    }

    public function parse(string $docBlock, string $annotation = null) : array
    {
        $this->position = 0;
        $this->docBlock = $docBlock;

        $pattern = self::CLASS_PATTERN;

        if ($annotation !== null) {
            $pattern = $this->getAnnotationPattern($annotation);
        }

        return $this->parseAnnotations($docBlock, $pattern);
    }

    private function match(string $pattern, $subject) : array
    {
        preg_match_all($pattern, $subject, $matches, PREG_OFFSET_CAPTURE);

        if ($matches === null) {
            return [];
        }

        $result = [];

        for ($i = 0, $count = count($matches[0]); $i < $count; $i++) {
            $match = [];

            foreach ($matches as $key => $values) {
                if (is_integer($key) && $key > 0) {
                    // Exclude positional matches
                    continue;
                }

                // Exclude empty matches
                if($values[$i] === '' || $values[$i][1] == -1) {
                    continue;
                }

                $match[$key] = $values[$i];
            }

            $result[] = $match;
        }

        return $result;
    }

    private function parseAnnotations(string $docBlock, string $pattern) : array
    {
        $regex = sprintf(self::ANNOTATION_REGEX, $pattern);
        $matches = $this->match($regex, $docBlock);

        $annotations = [];
        foreach ($matches as $match) {
            $name = $match['name'][0];

            if ($this->context->shouldIgnore($name)) {
                continue;
            }

            if (isset($match['malformed']) || isset($match['unsupported'])) {
                list($line, $column) = $this->getPosition($match['annotation'][1]);

                throw new MalformedAnnotationException(sprintf(
                    'Malformed annotation @%s found in %s, '.
                    'at line %d, column %d.',
                    $name,
                    $this->context->getSource(),
                    $line,
                    $column
                ));
            }

            $this->position = 0;

            $annotations[] = $this->parseValue($match);
        }

        return $annotations;
    }

    private function parseAnnotation(string $class, string $arguments)
    {
        $arguments = $this->parseMembers($arguments);

        return $this->factory->create($class,$arguments, $this->context);
    }

    private function parseMembers(string $verbatim) : array
    {
        $matches = $this->matchList($verbatim, self::MEMBERS_REGEX);

        if (!isset($matches[0]['member']) && count($matches) === 1) {
            return ['value' => $this->parseValue($matches[0])];
        }

        $members = [];
        $position = $this->position;
        foreach ($matches as $match) {
            if (!isset($match['member'])) {
                $index = $position + $match['value'][1];
                list($line, $column) = $this->getPosition($index);

                throw new MalformedAnnotationException(sprintf(
                    'Missing member name in %s at line %d, column %d.',
                    $this->context->getSource(),
                    $line,
                    $column
                ));
            }

            // Reset position as the match offset is relative to the beginning
            // of the subject string
            $this->position = $position;

            $member = $match['member'][0];
            $value = $this->parseValue($match);

            $members[$member] = $value;
        }

        return $members;
    }

    private function parseArray(string $verbatim) : array
    {
        $matches = $this->matchList($verbatim, self::ENTRIES_REGEX);
        $position = $this->position;

        $array = [];
        foreach ($matches as $match) {
            // Reset position as the match offset is relative to the beginning
            // of the subject string
            $this->position = $position;

            $key = $match['key'][0];
            $value = $this->parseValue($match);

            $array[$key] = $value;
        }

        return $array;
    }

    private function matchList(string $arguments, string $pattern) : array
    {
        if ($arguments === '') {
            return [];
        }

        $matches = $this->match($pattern, $arguments);
        $length = strlen($arguments);
        $consumed = 0;

        foreach ($matches as $match) {
            $consumed += strlen($match[0][0]);
        }

        if ($length === 0 || $length !== $consumed) {
            list($line, $column) = $this->getPosition();

            throw new MalformedAnnotationException(sprintf(
                'Malformed list or value found in %s, starting at line %d, ' .
                'column %d.',
                $this->context->getSource(),
                $line,
                $column
            ));
        }

        return $matches;
    }

    private function parseValue(array $match)
    {
        switch(true) {
            case isset($match['boolean']):
                return strtolower($match['boolean'][0]) === 'true';

            case isset($match['number']):
                return $match['number'][0] + 0;

            case isset($match['string']):
                return substr($match['string'][0], 1, -1);

            case isset($match['array']):
                $slice = substr($match['array'][0], 1, -1);
                $offset = $match[0][1] + strpos($match[0][0], '{') + 1;

                $this->position += $offset;

                return $this->parseArray($slice);

            case isset($match['annotation']):
                $class = $match['name'][0];
                $arguments = $match['args'][0] ?? '';
                $offset = $match[0][1] + strpos($match[0][0], '(') + 1;

                $this->position += $offset;

                return $this->parseAnnotation($class, $arguments);
        }

        throw new \InvalidArgumentException('Unrecognized value type');
    }

    private function getAnnotationPattern(string $annotation) : string
    {
        $quote = function($value) {
            return preg_quote($value, '/');
        };

        $aliases = $this->context->getAliases($annotation);

        return implode('|', array_map($quote, $aliases));
    }

    private function getPosition(int $index = null) : array
    {
        if ($index === null) {
            $index = $this->position;
        }

        $line = $this->context->getLine();
        $column = $this->context->getColumn();

        for ($i = 0; $i < $index; $i++) {
            if ($this->docBlock[$i] === "\n") {
                $line++;
                $column = 0;

                continue;
            }

            $column++;
        }

        return [$line, $column];
    }
}
