<?php

namespace Bnb\Laravel\Sequence\Jobs;

use Bnb\Laravel\Sequence\Exceptions\InvalidModelException;
use Bnb\Laravel\Sequence\HasSequence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Queue\InteractsWithQueue;

class UpdateSequence implements ShouldQueue
{

    use InteractsWithQueue, Queueable, DispatchesJobs;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var mixed
     */
    private $key;


    /**
     * @param string $class the class name to handle (must use HasSequence trait)
     * @param mixed  $key   optional : the primary key of the model instance to handle
     *
     * @throws InvalidModelException
     */
    public function __construct($class, $key = null)
    {

        $this->class = $class;
        $this->key = $key;

        if ( ! $this->validateClass()) {
            throw new InvalidModelException($this->class);
        }
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ( ! $this->validateClass()) {
            $this->fail(new InvalidModelException($this->class));

            return;
        }

        if (empty($this->key)) {
            $this->dispatchModelUpdates();
        } else {
            $model = $this->class;

            if ($model = $model::find($this->key)) {
                $model->generateSequenceNumber();
            }
        }
    }


    private function validateClass()
    {
        return class_exists($this->class) && in_array(HasSequence::class, class_uses($this->class) ?: []);
    }


    private function dispatchModelUpdates()
    {
        $model = new $this->class;

        $connection = config('sequence.queue.connection');
        $queue = config('sequence.queue.name');

        if (method_exists(self::class, $method = 'getSequenceConnection')) {
            $connection = (new self)->{$method}();
        }

        if (method_exists(self::class, $method = 'getSequenceQueue')) {
            $queue = (new self)->{$method}();
        }

        collect($model->sequences)->each(function ($name) use ($model, $connection, $queue) {
            $query = $model::query()->whereNull($name);

            $query->get([$model->getKeyName()])->each(function ($row) use ($connection, $queue) {
                $this->dispatch((new self($this->class, $row->id))->onConnection($connection)->onQueue($queue));
            });
        });
    }
}