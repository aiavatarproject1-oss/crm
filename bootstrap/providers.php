<?php

use App\Providers\ApplicationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\InfrastructureServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\ServiceServiceProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    ApplicationServiceProvider::class,
    InfrastructureServiceProvider::class,
    RepositoryServiceProvider::class,
    ServiceServiceProvider::class,
    HorizonServiceProvider::class,
];
