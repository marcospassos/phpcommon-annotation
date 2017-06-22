<?php

namespace PhpCommon\Annotation;

class AnnotationParser
{
    private const ANNOTATION_REGEX =
        '/
            ^[\s\*]*
            (?P<annotation>
                @(?<name>%s)
                (?:[\s\*]*(\((?<args>(?:[^()]|(?3))*)\)|(?!\()))
            )
        /imx';

    private const MALFORMED_ANNOTATION_REGEX ='/^[\s\*]*(@(%s))/im';

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
            (?:[\s\*]*(\((?<args>(?:[^()]|(?10))*)\)|(!=\()))
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
            [\s\*]*(?!,)
            (?:(?P<member>[a-z]+)[\s\*]*=[\s\*]*)?
            (?P<value>' . self::VALUE_PATTERN. ')
            [\s\*]*(?:,|,?$)
        /Axi';


    private $annotationFactory;
    private $nameResolver;
    private $position;
    private $blacklist;

    public function __construct(AnnotationFactory $factory, NameResolver $nameResolver, array $blacklist = [])
    {
        $this->annotationFactory = $factory;
        $this->nameResolver = $nameResolver;
        $this->blacklist = array_map('strtolower', $blacklist);
    }

    public function parse(string $docBlock, string $annotation = null) : array
    {
        $this->position = 0;

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

        if (empty($matches)) {
            $malformedRegex = sprintf(
                self::MALFORMED_ANNOTATION_REGEX,
                $pattern
            );

            preg_match($malformedRegex, $docBlock, $match, PREG_OFFSET_CAPTURE);

            if (!empty($match)) {
                throw MalformedAnnotationException::atPosition($match[1][1]);
            }

            return $matches;
        }

        $annotations = [];
        foreach ($matches as $match) {
            $name = strtolower($match['name'][0]);

            if (in_array($name, $this->blacklist, true)) {
                continue;
            }

            $match['value'] = $match['annotation'];

            $annotations[] = $this->parseValue($match);
        }

        return $annotations;
    }

    private function parseAnnotation(string $class, string $arguments) : Annotation
    {
        $class = $this->nameResolver->resolve($class);
        $arguments = $this->parseMembers($arguments);

        return $this->annotationFactory->create($class, $arguments);
    }

    private function parseMembers(string $arguments) : array
    {
        return $this->parseList($arguments, self::MEMBERS_REGEX, 'member');
    }

    private function parseEntries(string $arguments) : array
    {
        return $this->parseList($arguments, self::ENTRIES_REGEX, 'key');
    }

    private function parseList(string $arguments, string $pattern, string $index) : array
    {
        if ($arguments === '') {
            return [];
        }

        $matches = $this->match($pattern, $arguments);
        $length = strlen($arguments);

        foreach ($matches as $match) {
            $length -= strlen($match[0][0]);
        }

        if (empty($matches) || $length > 0) {
            throw MalformedAnnotationException::atPosition($this->position);
        }

        $result = [];
        $position = $this->position;
        foreach ($matches as $match) {
            // Reset position as the match offset is relative to the beginning
            // of the subject string
            $this->position = $position;

            $value = $this->parseValue($match);

            if (isset($match[$index])) {
                $result[$match[$index][0]] = $value;

                continue;
            }

            $result[] = $value;
        }

        return $result;
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

                return $this->parseEntries($slice);

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

        $aliases = $this->nameResolver->getAliases($annotation);

        return implode('|', array_map($quote, $aliases));
    }
}