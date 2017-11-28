<?php

namespace f;

function append($a, $b) {
    $a[] = $b;
    return $a;
};

function appendList($l1, $l2) {
    return fold("f\append", $l1)($l2);
}

function partial(callable $callable, ...$args)
{
    $arity = (new \ReflectionFunction($callable))->getNumberOfRequiredParameters();

    return $args[$arity - 1] ?? false
        ? $callable(...$args)
        : function (...$passedArgs) use ($callable, $args) {
            return partial($callable, ...array_merge($args, $passedArgs));
        };
}

function patternMatch (array $config) {
    $callOrReturn = function($candidate, $param) {
        if (is_callable($candidate))
            return call_user_func($candidate, $param);
        else
            return $candidate;
    };

    return function($x) use ($config, $callOrReturn) {
        foreach($config as $key => $whatToDo)  {
            if ($x == $key || $key === '_' || ($key === '[]' && $x === [])) {
                return $callOrReturn($whatToDo, $x);
            } elseif ($key === '(x:xs)') {
                return call_user_func($whatToDo, head($x), tail($x));
            }

        }
        throw new \Exception('No match found');
    };
}
// [a] -> a
function head($xs) {
    $result = reset($xs);
    return $result;
}

// [a] -> [a]
function tail($xs) : array {
    return array_slice($xs, 1);
}

function seq($init, $step) : \Generator {
    for ($i =$init;;$i= $i + $step) {
        yield $i;
    }
}

function infinity() : \Generator {
    yield from seq(0, 1);
};


function takeFrom(\Generator $range, int $num) {
    $result = [];
    foreach($range as $entry) {
        $result[] = $entry;
        if (count($result) >= $num) {
            break;
        }
    }

    return $result;
}


// f -> a -> [a] -> a
function fold($callable, $init) {
    $fold = function($list) use ($init, $callable, &$fold) {
        if (empty($list)) {
            return $init;
        }

        $last = array_pop($list);
        return $callable($fold($list), $last);
    };
    return $fold;
};


// f -> [] -> []
function map ($func, $list)  {
    $applyAndAppend = function($func, $list, $b){
        return append($list, $func($b));
    };
    $applyAndAppendPartial = partial($applyAndAppend);
    return fold($applyAndAppendPartial($func), null)($list);
}

// f -> f -> a
function memoize($function) {
    static $results = [];
    return function () use ($function, &$results) {
        $args = func_get_args();
        $key = serialize($args);

        if (empty($results[$key]))  {
            $results[$key] = call_user_func_array($function, $args);
            return $results[$key];
        }

        return $results[$key];
    };
}

function last(array $a) {
    $cpy = $a;
    $result = array_pop($cpy);
    return $result;
}

function lastKey(array $a) {
    $cpy = $a;
    end($cpy);
    $key = key($cpy);
    return $key;
}


function allbutlast(array $a) {
    $cpy = $a;
    array_pop($cpy);
    return $cpy;
}

function foldTree($f, $g, $initial, $tree) {
    if ($tree == null || empty($tree)) {
        return $initial;
    }

    if (is_scalar($tree)) {
        return $f($initial, $tree);
    }

    $last= \f\last($tree);
    $lastKey = \f\lastKey($tree);


    if (is_string($lastKey)) {
        $last = $f(foldTree($f,$g,$initial, $last), $lastKey);
        return $f(
            foldTree($f,$g,$initial, \f\allbutlast($tree)),
            $last
        );
    }


    return $g(
        foldTree($f, $g, $initial, \f\allbutlast($tree)),
        foldTree($f, $g, $initial, $last)
    );

}


function mapTree($f, $tree) {
    $runAndAppend = function($a, $b) use ($f) {
        if (is_array($b)) {
            if (is_int(\f\last($b))) {
                $a["0".\f\last($b)] =\f\allbutlast($b);
            } else {
                $a[\f\last($b)] =\f\allbutlast($b);
            }

        } else {
            $a[] = $f($b);
        }
        return $a;
    };
    $runAndIndice= function($a, $b) use ($f) {
        return array_merge($a, $b);
    };
    return foldTree($runAndAppend, $runAndIndice, [], $tree);
}

