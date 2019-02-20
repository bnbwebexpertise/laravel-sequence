# Laravel 5.4+ integer sequence helpers

This package provides helpers to work with no-gap and ordered integer sequences in Laravel models.

It has been built to help the generation of "administrative sequences" where there should be no missing value between records (invoices, etc).

**WARNING** This is *beta* version (POC). This is not yet production ready, so you should use at your own risk.

## Installation

Add the service provider to your configuration :

```php
'providers' => [
        // ...

        Bnb\Laravel\Sequence\SequenceServiceProvider::class,

        // ...
],
```


## Configuration

You can customize this package behavior by publishing the configuration file :

    php artisan vendor:publish --provider='Bnb\Laravel\Sequence\SequenceServiceProvider'

You can customize values without publishing by specifying those keys in your `.env` file :

```
SEQUENCE_START=123                      # defaults to 1
SEQUENCE_QUEUE_CONNECTION=database      # defaults to default
SEQUENCE_QUEUE_NAME=sequence            # defaults to default
SEQUENCE_AUTO_DISPATCH=false            # defaults to true
```

> To avoid concurrency issues when generating sequence number, the queue worker number should be set to one.
 It is recommended to use a dedicated queue (and worker).


## Adding a sequence to a model

Sequence columns should be generated with the following configuration in your migration :

    $table->unsignedInteger('sequence_name')->nullable()->unique();

To work with sequence you must enhance your model class with the `Bnb\Laravel\Sequence\HasSequence` trait.

The `sequences` array property of your model must contain the list of the sequences names :

```
    public $sequences = ['my_sequence'];
```

Some sequence properties can be overridden by specifying a method in the model class (where `MySequence` is the name your sequence in PascalCase) :
- sequence start value (per-sequence) with the `getMySequenceStartValue() : int`
- sequence format (per-sequence) with the `formatMySequenceSequence($next, $last) : int`
- sequence generation authorization (per-sequence) with the `canGenerateMySequenceSequence() : bool`
- sequence gap filling mode (per-sequence) `isMySequenceGapFilling() : bool`
- sequence generation queue connection (for the model class) `getSequenceConnection() : string`
- sequence generation queue name (for the model class) `getSequenceQueue() : string`
- sequence auto-dispatch activation (for the model class) `isSequenceAutoDispatch() : bool`

Example :

```
use Bnb\Laravel\Sequence\HasSequence;
use Illuminate\Database\Eloquent\Model;

/**
 * MyModel model uses a sequence named
 */
class MyModel extends Model
{
    use HasSequence;

    const SEQ_INVOICE_NUMBER_START = 0;

    public $timestamps = false;

    protected $fillable = ['active'];

    protected $sequences = ['invoice_number'];

    /**
     * Assume the sequence can only be generated if active is true
     */
    protected function canGenerateReadOnlySequence()
    {
        return $this->active;
    }

    /**
     * The sequence default value must match the format
     */
    protected function getInvoiceNumberStartValue()
    {
        return sprintf('%1$04d%2$06d', date('Y'), static::SEQ_INVOICE_NUMBER_START);
    }


    /**
     * Format the sequence number with current Year that resets its counter to 0 on year change
     */
    protected function formatInvoiceNumberSequence($next, $last)
    {
        $newYear = date('Y');
        $newCounter = substr($next, 4);

        if ($last) {
            $lastYear = substr($last, 0, 4);

            if ($lastYear < $newYear) {
                $newCounter = static::SEQ_INVOICE_NUMBER_START;
            }
        }

        return sprintf('%1$04d%2$06d', $newYear, $newCounter);
    }

    /**
     * If `true`, the first number available is used, ie. the lowest number available, greater or equal to start number, including gaps caused by deletion (soft-delete included)
     * If `false` (default value), the next number will always be the last number used + 1 or the first number of the sequence when nothing pre-exists
     */
    protected function isSequenceGapFilling()
    {
        return false;
    }
}
```

### Sequence generation event

When a sequence number is generated a model event is thrown for running custom tasks.
You can listen to the sequence event using the `sequenceGenerated($sequenceName, $callback)` method (in a service provider boot method for example) :

```
    MyModel::sequenceGenerated('invoice_number', function ($model) {
        Mail::to($model->recipient_email)->send(new InvoiceGenerated($model));
    });
```


## Schedule generation

You can schedule inside your console kernel the `Bnb\Laravel\Sequence\Console\Commands\UpdateSequence` at the required frequency to generate the missing sequence numbers asynchronously.

```
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('sequence:update')
                 ->hourly();
    }
```

This may not be required when using auto dispatch mode but can be used as a security fallback.