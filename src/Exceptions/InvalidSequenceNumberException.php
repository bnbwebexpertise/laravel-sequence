<?php
/**
 * laravel
 *
 * @author    Jérémy GAULIN <jeremy@bnb.re>
 * @copyright 2017 - B&B Web Expertise
 */

namespace Bnb\Laravel\Sequence\Exceptions;

use Bnb\Laravel\Sequence\HasSequence;
use Exception;

class InvalidSequenceNumberException extends Exception
{

    public function __construct($name, $class, $value)
    {
        parent::__construct(sprintf('Sequence value "%s" for "%s" in class "%s" must use a 10 digit integer format.', $value, $name, $class,
            HasSequence::class));
    }
}