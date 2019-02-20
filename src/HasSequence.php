<?php

namespace Bnb\Laravel\Sequence;

use Bnb\Laravel\Sequence\Exceptions\InvalidSequenceNumberException;
use Bnb\Laravel\Sequence\Exceptions\SequenceOutOfRangeException;
use Bnb\Laravel\Sequence\Jobs\UpdateSequence;
use DB;
use Illuminate\Database\Eloquent\SoftDeletes;

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

            static::deleted(function ($item) use ($connection, $queue) {
                $changed = collect($item->sequences)->reduce(function ($changed, $name) use ($item) {
                    return $item->handleSoftDeletedSequence($name, false) || $changed;
                }, false);

                if ($changed) {
                    $item->save();
                }
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
     * @return bool
     * @throws InvalidSequenceNumberException
     * @throws SequenceOutOfRangeException
     */
    public function generateSequenceNumber($name = null, $save = true)
    {
        if (empty($name)) {
            $generated = [];

            collect($this->sequences)->each(function ($name) use (&$generated) {
                if ($this->generateSequenceNumber($name, false)) {
                    $generated[] = $name;
                }
            });

            if ($saved = $this->save()) {
                collect($generated)->each(function ($name) {
                    $this->fireModelEvent(sprintf('sequence_%s_generated', studly_case($name)), false);
                });
            }

            return $saved;
        }

        if ($this->handleSoftDeletedSequence($name)) {
            return true;
        }

        if ($this->canGenerateSequenceNumber($name)) {
            $sequence = null;

            if (method_exists($this, $method = 'is' . ucfirst(camel_case($name)) . 'GapFilling') && $this->{$method}()) {
                $start = $this->generateSequenceStartValue($name) - 1;

                $sequence = DB::selectOne(DB::raw(<<<SQL
SELECT 
  z.expected as next_sequence_value, 
  z.expected - 1 as last_sequence_value 
FROM (
  SELECT @rownum := @rownum + 1 AS expected, IF(@rownum = `{$name}`, 0, @rownum := `{$name}`) AS got
  FROM (SELECT @rownum := {$start}) AS a JOIN {$this->getTable()} WHERE `{$name}` IS NOT NULL ORDER BY `{$name}`
) AS z
WHERE z.got != 0
SQL
                ));
            }

            if (empty($sequence)) {
                $sequence = DB::table($this->getTable())
                    ->select([
                        DB::raw(sprintf('(MAX(%s)) as last_sequence_value', $name)),
                        DB::raw(sprintf('(MAX(%s) + 1) as next_sequence_value', $name))
                    ])
                    ->whereNotNull($name)
                    ->get()
                    ->first();
            }

            if ( ! ($next = $sequence->next_sequence_value)) {
                $next = $this->generateSequenceStartValue($name);
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
                if ($this->save()) {
                    $this->fireModelEvent(sprintf('sequence_%s_generated', studly_case($name)), false);
                }
            }

            return true;
        }

        return false;
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


    /**
     * @param string $name the sequence name
     *
     * @return int the default start value
     */
    protected function generateSequenceStartValue($name)
    {
        $defaultStart = config('sequence.start', 1);

        if (method_exists($this, $method = 'get' . ucfirst(camel_case($name)) . 'StartValue')) {
            $defaultStart = $this->{$method}();
        }

        $next = $defaultStart;

        return intval($next);
    }


    protected function handleSoftDeletedSequence($name, $save = true)
    {
        $isSoftDelete = in_array(SoftDeletes::class, class_uses_recursive(
            get_class($this)
        ));

        $isGapFilling = method_exists($this, $method = 'is' . ucfirst(camel_case($name)) . 'GapFilling') && $this->{$method}();

        if ($this->exists() && $isSoftDelete && $isGapFilling && ! empty($this->{$this->getDeletedAtColumn()}) && $this->{$name}) {
            $this->{$name} = null;
            if ($save) {
                $this->save();
            }

            return true;
        }

        return false;
    }
}