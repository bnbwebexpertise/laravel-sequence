<?php

namespace Bnb\Laravel\Sequence\Console\Commands;

use Bnb\Laravel\Sequence\Jobs\UpdateSequence as UpdateSequenceJob;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Lang;
use Symfony\Component\Console\Input\InputArgument;

class UpdateSequence extends Command
{

    use DispatchesJobs;

    protected $signature = 'sequence:update';


    public function __construct()
    {
        $this->description = Lang::get('sequence::messages.console.update_description');

        parent::__construct();

        $this->getDefinition()->addArgument(new InputArgument('class', InputArgument::REQUIRED,
            Lang::get('sequence::messages.console.update_argument_class')));
    }


    public function handle()
    {
        $class = $this->argument('class');

        if ( ! class_exists($class)) {
            $this->error(Lang::get('sequence::messages.console.update_error_class_not_found', ['class' => $class]));

            return;
        }

        $connection = config('sequence.queue.connection');
        $queue = config('sequence.queue.name');

        if (method_exists(self::class, $method = 'getSequenceConnection')) {
            $connection = (new self)->{$method}();
        }

        if (method_exists(self::class, $method = 'getSequenceQueue')) {
            $queue = (new self)->{$method}();
        }

        $this->dispatch((new UpdateSequenceJob($class))->onConnection($connection)->onQueue($queue));
    }
}