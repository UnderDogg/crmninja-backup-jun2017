<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ComposerServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function boot()
    {
        view()->composer(
            ['companies.details', 'relations.edit', 'payments.edit', 'invoices.edit', 'companies.localization'],
            'App\Http\ViewComposers\TranslationComposer'
        );

        view()->composer(
            ['header', 'tasks.edit'],
            'App\Http\ViewComposers\AppLanguageComposer'
        );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }
}
