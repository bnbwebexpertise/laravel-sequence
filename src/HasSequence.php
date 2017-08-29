<?php

namespace Bnb\Laravel\Sequence;

use Bnb\Laravel\Sequence\Exceptions\InvalidSequenceNumberException;
use Bnb\Laravel\Sequence\Exceptions\SequenceOutOfRangeException;
use Bnb\Laravel\Sequence\Jobs\UpdateSequence;
use DB;

trait HasSequence
{

    public static function bootHasSequence()
    {
        $autoDispatch = filter_var(config('sequence.dispatch'), FILTER_VALIDATE_BOOLEAN);

        if (method_exists(self::class, $method = 'isSequenceAutoDispatch')) {
            $autoDispatch = ! ! (new self)->{$method}();
        }

        if (property_exists(self::class, 'sequences') && $autoDispatch) {
            $connection = config('sequence.queue.connection');
            $queue = config('sequence.queue.name');

            if (method_exists(self::class, $method = 'getSequenceConnection')) {
                $connection = (new self)->{$method}();
            }

            if (method_exists(self::class, $method = 'getSequenceQueue')) {
                $queue = (new self)->{$method}();
            }

            static::created(function ($item) use ($connection, $queue) {
                dispatch((new UpdateSequence(self::class, $item->getKey()))->onConnection($connection)->onQueue($queue));
            });

            static::updated(function ($item) use ($connection, $queue) {
                dispatch((new UpdateSequence(self::class, $item->getKey()))->onConnection($connection)->onQueue($queue));
            });
        }
    }


    /**
     * @param string $name the sequence name
     *
     * @return bool true is the number can be generated
     */
    protected function canGenerateSequenceNumber($name)
    {
        $authorized = true;

        if (method_exists($this, $method = 'canGenerate' . ucfirst(camel_case($name)) . 'Sequence')) {
            $authorized = ! ! $this->{$method}();
        }

        return $this->exists() && empty($this->{$name}) && $authorized;
    }


    /**
     * @param string $name the sequence name
     * @param bool   $save
     *
     * @throws InvalidSequenceNumberException
     * @throws SequenceOutOfRangeException
     */
    public function generateSequenceNumber($name = null, $save = true)
    {
        $saved = false;

        if (empty($name)) {
            collect($this->sequences)->each(function ($name) {
                $this->generateSequenceNumber($name, false);
            });

            $saved = $this->save();
        } elseif ($this->canGenerateSequenceNumber($name)) {
            $sequence = DB::table($this->getTable())
                ->select([
                    DB::raw(sprintf('(MAX(%s)) as last_sequence_value', $name)),
                    DB::raw(sprintf('(MAX(%s) + 1) as next_sequence_value', $name))
                ])
                ->whereNotNull($name)
                ->get()
                ->first();

            if ( ! ($next = $sequence->next_sequence_value)) {
                $defaultStart = config('sequence.start', 1);

                if (method_exists($this, $method = 'get' . ucfirst(camel_case($name)) . 'StartValue')) {
                    $defaultStart = $this->{$method}($next);
                }

                $next = $defaultStart;
            }

            $next = intval($next);

            if (method_exists($this, $method = 'format' . ucfirst(camel_case($name)) . 'Sequence')) {
                $next = $this->{$method}($next, $sequence->last_sequence_value);

                if ( ! preg_match('/^\d{1,10}$/', $next)) {
                    throw new InvalidSequenceNumberException($name, self::class, $next);
                }

                if ($next < 0 || $next > 4294967295 || $next < $sequence->last_sequence_value) {
                    throw new SequenceOutOfRangeException($name, self::class, $next);
                }
            }

            $this->{$name} = $next;


            if ($save) {
                $saved = $this->save();
            }
        }

        if($saved) {
            $this->fireModelEvent(sprintf('sequence_%s_generated', studly_case($name)), false);
        }
    }


    /**
     * Listen to sequence generation event
     *
     * @param string           $name the sequence name
     * @param  \Closure|string $callback
     */
    public static function sequenceGenerated($name, $callback)
    {
        static::registerModelEvent(sprintf('sequence_%s_generated', studly_case($name)), $callback);
    }
}