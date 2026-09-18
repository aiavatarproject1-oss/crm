<?php

use App\Providers\AdminServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\InfrastructureServiceProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    InfrastructureServiceProvider::class,
    AdminServiceProvider::class,
    HorizonServiceProvider::class,
];
