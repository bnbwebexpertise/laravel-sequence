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

class InvalidModelException extends Exception
{

    public function __construct($class)
    {
        parent::__construct(sprintf('Class "%s" must use "%s" trait', $class, HasSequence::class));
    }
}