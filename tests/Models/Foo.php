<?php

namespace Bnb\Laravel\Sequence\Tests\Models;

use Bnb\Laravel\Sequence\HasSequence;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property int sequence
 *
 * @package Bnb\Laravel\Sequence\Tests\Models
 */
class Foo extends Model
{

    use HasSequence;

    const SEQ_SEQUENCE_START = 999998;

    public $timestamps = false;

    protected $fillable = ['bar'];

    protected $sequences = [
        'sequence',
        'read_only',
    ];


    protected function canGenerateReadOnlySequence()
    {
        return $this->bar === 'yes';
    }


    protected function getSequenceStartValue()
    {
        return sprintf('%1$04d%2$06d', date('Y'), static::SEQ_SEQUENCE_START);
    }


    protected function formatSequenceSequence($next, $last)
    {
        $newYear = date('Y');
        $newCounter = substr($next, 4);

        if ($last) {
            $lastYear = substr($last, 0, 4);

            if ($lastYear < $newYear) {
                $newCounter = static::SEQ_SEQUENCE_START;
            }
        }

        return sprintf('%1$04d%2$06d', $newYear, $newCounter);
    }
}