# Как использовать

```php


require_once __DIR__ . '/vendor/autoload.php';

$dbh = new AJUR\DBWrapper\DBWrapper([
    'driver'    =>  'pdo',
    'username'  =>  'root',
    'password'  =>  'password',
    'database'  =>  'test',
    'slow_query_threshold'  => 1
]);

// exposes normal 

$sth = $dbh->prepare("/* insert data */ INSERT INTO test (setting, value) VALUES (:s, :v)");
$sth->execute([
    's' =>  'option',
    'v' =>  mt_rand(100, 10000)
]);
var_dump($dbh->getLastState());

var_dump( $dbh->query("/* select query */ SELECT * FROM test ORDER BY RAND() LIMIT 1")->fetchAll() );
var_dump($dbh->getLastState());


$sth = $dbh->query('SELECT id, title FROM articles ORDER BY id DESC LIMIT 10000');
var_dump($sth->fetchAll());
var_dump($dbh->getLastState());

var_dump($dbh->getStats());



```