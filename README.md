# PHP Config Loader

Gives access to structured configuration variables
stored in JSON files and/or environment variables.

## Example

Let's say ```config/database.json``` looks like:

```json
{
	"driver": "postgres",
	"host": "db.example.org",
	"dbname": "OVERRIDE ME PLZ THX",
	"notes": [
		"This is the greatest database configuration.",
		"My brother Bob said so."
	]
}
```

And we're going to run our PHP script with these environment variables:

```sh
database_dbname=bobco
otherthing_json='{"foo":"bar","baz","quux"}'
otherthing_foo="jk actually not bar"
```

And then we have a script like:

```php
$loader = new EarthIT_ConfigLoader( 'database', $_ENV )
echo "Database: "; var_export($loader->get('database'));
echo "Other thing: "; var_export($loader->get('otherthing'));
```

Would output something like:

```
Database: array (
  driver => 'postgres',
  host => 'db.example.org',
  dbname => 'bobco',
  notes => array (
    'This is the greatest database configuration.',
    'My brother Bob said so.'
  )
)
Other thing: array (
  'foo' => 'jk actually not bar',
  'baz' => 'quux'
)
```

### Overrides

Variables are merged in the following order, with later steps 'overriding'
the values from earlier steps:

- JSON files
- files in subdirectories (e.g. 'foo/bar.json' can override 'bar' from 'foo.json')
- JSON-encoded environment variables
- Leaf environment variables

If an array value overrides another array value, they are merged instead of replacing the old one.

### Environment variable names

- Underscores have special meaning, so if you have a variable ```foo_bar_baz=42```,
  it's going to show up when fetched from the ConfigLoader as ```array('foo'=>array('bar'=>array('baz'=>42)))```

- The postfix ```json``` also has special meaning.
  It means that the value of the variable named by the part before "_json"
  will be determined by JSON-decoding this environment variable's value.
