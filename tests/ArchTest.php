<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('posthog handler extends monolog handler')
    ->expect('RobbieKibler\PosthogLogs\PosthogHandler')
    ->toExtend('Monolog\Handler\AbstractProcessingHandler');

arch('service provider extends package service provider')
    ->expect('RobbieKibler\PosthogLogs\PosthogLogsServiceProvider')
    ->toExtend('Spatie\LaravelPackageTools\PackageServiceProvider');
