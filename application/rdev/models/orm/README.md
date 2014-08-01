# Object-Relational Mapping
**RDev** utilizes the *repository pattern* to encapsulate data retrieval from storage.  *Repositories* have *DataMappers* which actually interact directly with storage, eg cache and/or a relational database.  Repositories use *units of work*, which act as transactions across multiple repositories.  The benefits of using units of work include:

1. Transactions across multiple repositories can be rolled back, giving you "all or nothing" functionality
2. Changes made to entities retrieved by repositories are automatically checked for changes and, if any are found, scheduled for updating when the unit of work is committed
3. Database writes are queued and executed all at once when the unit of work is committed, giving you better performance than executing writes throughout the lifetime of the application
4. Querying for the same object will always give you the same, single instance of that object

## Table of Contents
1. [Unit of Work Change Tracking](#unit-of-work-change-tracking)
2. [Aggregate Roots](#aggregate-roots)
3. [Automatic Caching](#automatic-caching)

## Unit of Work Change Tracking
Let's take a look at how units of work can manage entities retrieved through repositories:
```php
use RDev\Models\Databases\SQL;
use RDev\Models\ORM;
use RDev\Models\ORM\DataMappers;
use RDev\Models\ORM\Repositories;
use RDev\Models\Users;

// Assume $connection was set previously
$unitOfWork = new ORM\UnitOfWork($connection);
$dataMapper = new DataMappers\MyDataMapper();
$users = new Repositories\Repo("RDev\\Models\\Users\\User", $dataMapper, $unitOfWork);

// Let's say we know that there's a user with Id of 123 and username of "foo" in the repository
$someUser = $users->getById(123);
echo $someUser->getUsername(); // "foo"

// Let's change his username
$someUser->setUsername("bar");
// Once we're done with our unit of work, just let it know you're ready to commit
// It'll automatically know what has changed and save those changes back to storage
$unitOfWork->commit();

// To prove that this really worked, let's print the name of the user now
echo $users->getById(123)->getUsername(); // "bar"
```

## Aggregate Roots
Let's say that when creating a user you also create a password object.  This password object has a reference to the user object's Id.  In this case, the user is what we call an *aggregate root* because without it, the password wouldn't exist.  You might be asking yourself "How do I get the Id of the user before storing the password?"  The answer is `registerAggregateRootChild`:
```php
// Let's assume the unit of work has already been setup and that the user and password objects are created
// Order here matters: aggregate roots should be added before their children
$unitOfWork->scheduleForInsertion($user);
$unitOfWork->scheduleForInsertion($password);
// Pass in the aggregate root, the child, and the function that sets the aggregate root Id in the child
// The first argument of the function you pass in should be the aggregate root, and the second should be the child
$unitOfWork->registerAggregateRootChild($user, $password, function($user, $password)
{
    // This will be executed after the user is inserted but before the password is inserted
    $password->setUserId($user->getId());
});
$unitOfWork->commit();
echo $password->getUserId() == $user->getId(); // 1
```

## Automatic Caching
By extending the *CachedSQLDataMapper*, you can take advantage of automatic caching of entities for faster performance.  Entities are searched in cache before defaulting to an SQL database, and they are added to cache on misses.  Writes to cache are automatically queued whenever writing to a *CachedSQLDataMapper*.  To keep cache in sync with SQL, the writes are only performed once a unit of work commits successfully.