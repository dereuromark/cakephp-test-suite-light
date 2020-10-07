# cakephp-test-suite-light
A fast test suite for CakePHP applications

####For CakePHP 3.x
composer require --dev vierge-noire/cakephp-test-suite-light "^1.0"

#### For CakePHP 4.x
composer require --dev vierge-noire/cakephp-test-suite-light "^2.0"

## Installation

### Listeners

Make sure you *replace* the native CakePHP listener by the following one inside your `phpunit.xml` (or `phpunit.xml.dist`) config file, per default located in the root folder of your application:

```
<!-- Setup a listener for fixtures -->
     <listeners>
         <listener class="CakephpTestSuiteLight\FixtureInjector">
             <arguments>
                 <object class="CakephpFixtureFactories\FixtureManager" />
             </arguments>
         </listener>
     </listeners>
``` 

Between each test, the package will truncate all the test tables that have been used during the previous test.

The fixtures will be created in the test database(s) defined in your [configuration](https://book.cakephp.org/4/en/development/testing.html#test-database-setup).

### Ignoring connections

The package will empty the tables found in all test databases. If you wish to ignore a given connection, you may create a 
`config/test_suite_light.php` file and provide the connections that should be ignored:

```$xslt
<?php

return [   
    'TestSuiteLightIgnoredConnections' => [
        'test_foo_connection_to_be_ignored',
        'test_bar_connection_to_be_ignored',
        ...
    ],
];
```

This can be useful for example if you have connections to a third party server in the cloud that should be ignored.