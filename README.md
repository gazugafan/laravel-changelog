# Laravel Changelog
### Database record changelogs for Laravel 5

Automatically log who did what, when, and from where. Pairs super well with [laravel-temporal](https://github.com/gazugafan/laravel-temporal)!

## Requirements

- This has only been tested on Laravel 5.4 with PHP 7.1. Let me know if you find it works on older versions!
- Also only tested with MySQL/MariaDB. Let me know if you find it works with other databases!
- No unit tests yet.

## Installation

Install via Composer...

```bash
composer require gazugafan/laravel-changelog
```

Add the service provider to the ```providers``` array in ```config/app.php```...
```php
'providers' => [
	...
	\Gazugafan\Changelog\ServiceProvider::class
];
```

And add an alias to the ```aliases``` array in ```config/app.php```...
```php
'aliases' => [
	...
	'Change' => Gazugafan\Changelog\Facades\Change::class
];
```

## Overview

Changelogged tables get a new ```change_id``` column, which relates to a new ```changes``` table to save details about the latest change to a record. Wrapping changes in database transactions allows multiple tables to be affected by the same change. The ID of the authenticated user is automatically logged with each change (if possible), and you can include details like notes and the interface the change took place from.

When paired with [laravel-temporal](https://github.com/gazugafan/laravel-temporal), this will give you the history of every change made to a record, including who made each change and exactly what was changed.

## Schema Migration

Run the following migration to create the necessary ```changes``` table...
```php
Schema::create('changes', function (Blueprint $table) {
	$table->increments('id');
	$table->timestamps();
	$table->unsignedInteger('user_id')->nullable();
	$table->string('interface', 127)->nullable();
	$table->string('notes', 255)->nullable();
	$table->enum('status', ['pending', 'complete', 'failed'])->default('pending');
	$table->index('status');
});
```

You'll also need to add a ```change_id``` column to any table you want to log changes in...
```php
Schema::table('widgets', function (Blueprint $table) {
	$table->unsignedInteger('change_id')->nullable();
});
```

## Model Setup

To make your model support changelogging, just add the ```Gazugafan\Changelog\Changelog``` trait to the model's class...
```php
class Widget extends Model
{
	use Changelog; //add all the changelog features
}
```

You can also set some model-specific options...
```php
class Widget extends Model
{
	use Changelog; //add all the changelog features

	protected $forceChangelogging = true; //set to false to allow saving outside of changes
	protected $changeIDColumn = 'change_id'; //in case you want to use a different column for some reason
}
```

## Usage

#### Logging changes

To start logging a change, call ```Change::begin()```. You can optionally specify the interface and notes to log along with the change...
```php
Change::begin('Web API', 'Painting a widget red');
```

This will insert a new change into the ```changes``` table with a status of ```pending```, and automatically fill in the authenticated user's ID if one is available. Next, start doing everything related to your change...
```php
$widget = \App\Widget::find(123);
$widget->color = 'red';
$widget->save();

$redPaint = \App\Paint::where('color', 'red')->first();
$redPaint->available -= 1;
$redPaint->save();
```

Whenever you ```save()``` a model with the ```Changelog``` trait, the ID of the change in progress will automatically be filled into the the record's ```change_id```. 

When you're finished with your change, call ```Change::commit()```. This will finalize the change by updating its status to ```complete```. If the change failed for some reason, you can call ```Change::rollBack()``` to abandon the change and set its status to ```failed```. Here's what the whole thing might look like...
```php
try {
	Change::begin('Web API', 'Painting a widget red');
	
	$widget = \App\Widget::find(123);
	$widget->color = 'red';
	$widget->save();
	
	$redPaint = \App\Paint::where('color', 'red')->first();
	$redPaint->available -= 1;
	$redPaint->save();
	
	Change::commit();
} catch (Exception $e) {
	Change::rollBack();
}
```

If this looks similar to how database transactions are handled in Laravel, that's no coincidence! By default, the ```Change``` methods above will also wrap your change in a transaction. This means if something goes wrong during your change, the entire thing will automatically be rolled back. In other words: either the entire change happens successfully, or the entire thing fails. There's no way for just part of the change to complete. Also just like Laravel transactions, you can use the ```transaction``` method with a closure...
```php
Change::transaction(function (){
	$widget = \App\Widget::find(123);
	$widget->color = 'red';
	$widget->save();
	
	$redPaint = \App\Paint::where('color', 'red')->first();
	$redPaint->available -= 1;
	$redPaint->save();
}, 'Web API', 'Painting a widget red', 5); //make 5 attempts in case of deadlock
```

If you'd like to disable the use of transactions for some reason, pass ```false``` as the third parameter to ```Change::begin()```...
```php
try {
	Change::begin('Web API', 'Painting a widget red', FALSE); //start a change without transactions
	
	$widget = \App\Widget::find(123);
	$widget->color = 'red';
	$widget->save();
	
	$redPaint = \App\Paint::where('color', 'derek')->first();
	$redPaint->available -= 1; //there's no "derek" color, dummy! This is gonna blow up!
	$redPaint->save();
	
	Change::commit();
} catch (Exception $e) {
	//great, now we've somehow painted a widget red without using any red paint.
	//if only we had used transactions, we'd rollback painting the widget red here...
	Change::rollBack(); //but this will still log the change's status as "failed", at least
}
```

The authenticated user's ID is retrieved via ```Auth::id()```. If you'd like to override this with your own behavior, you can pass a closure to ```Change::authID()```...
```php
Change::authID(function(){
	return getMySpecialLoggedInUsersID();
});
Change::begin();
...
```

## Pitfalls

- Unlike normal Laravel database transactions, nested changes are not supported. Attempting to start a change while another change is already in progress will result in an error.