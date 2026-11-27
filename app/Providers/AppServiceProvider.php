<?php

namespace App\Providers;

use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(false);

        // أرقام إنجليزية (0-9) في كل الجداول والشاشات لسهولة القراءة المحاسبية
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale('en')->striped());
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultNumberLocale('en'));
    }
}
