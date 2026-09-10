<?php

namespace App\Providers;

use App\Domain\Rule\Services\KeywordRuleMatcher;
use App\Domain\Rule\Services\RegexRuleMatcher;
use App\Domain\Rule\Services\RuleMatcherRegistry;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuleMatcherRegistry::class, fn (): RuleMatcherRegistry => new RuleMatcherRegistry([
            new KeywordRuleMatcher,
            new RegexRuleMatcher,
        ]));
    }
}
