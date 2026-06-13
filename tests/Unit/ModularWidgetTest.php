<?php

use Coolsam\Modules\StatsOverviewWidget;
use Coolsam\Modules\TableWidget;
use Modules\Blog\Filament\Widgets\ParentAccessWidget;
use Modules\Blog\Filament\Widgets\TestStatsWidget;
use Modules\Blog\Filament\Widgets\TestTableWidget;

beforeEach(function () {
    if (! class_exists('Modules\Blog\Filament\Widgets\TestStatsWidget', false)) {
        eval(<<<'PHP'
            namespace Modules\Blog\Filament\Widgets;

            class TestStatsWidget extends \Coolsam\Modules\StatsOverviewWidget
            {
                protected function getStats(): array
                {
                    return [];
                }
            }
            PHP);
    }

    if (! class_exists('Modules\Blog\Filament\Widgets\TestTableWidget', false)) {
        eval(<<<'PHP'
            namespace Modules\Blog\Filament\Widgets;

            class TestTableWidget extends \Coolsam\Modules\TableWidget
            {
                public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
                {
                    return $table;
                }
            }
            PHP);
    }

    if (! class_exists('Filament\Widgets\WidgetWithAccess', false)) {
        eval(<<<'PHP'
            namespace Filament\Widgets;

            class WidgetWithAccess extends Widget
            {
                public static function canAccess(): bool
                {
                    return false;
                }
            }
            PHP);
    }

    if (! class_exists('Modules\Blog\Filament\Widgets\ParentAccessWidget', false)) {
        eval(<<<'PHP'
            namespace Modules\Blog\Filament\Widgets;

            class ParentAccessWidget extends \Filament\Widgets\WidgetWithAccess
            {
                use \Coolsam\Modules\Traits\CanAccessTrait;

                public static function canView(): bool
                {
                    return self::canAccess();
                }
            }
            PHP);
    }
});

test('stats overview widget delegates can view to can access', function () {
    $this->createTestModule('Blog', enabled: true);

    expect(TestStatsWidget::canAccess())->toBeTrue();
    expect(TestStatsWidget::canView())->toBeTrue();
});

test('table widget delegates can view to can access', function () {
    $this->createTestModule('Blog', enabled: true);

    expect(TestTableWidget::canAccess())->toBeTrue();
    expect(TestTableWidget::canView())->toBeTrue();
});

test('can access trait respects filament parent access checks', function () {
    $this->createTestModule('Blog', enabled: true);

    expect(ParentAccessWidget::canAccess())->toBeFalse();
    expect(ParentAccessWidget::canView())->toBeFalse();
});

test('base modular widgets extend filament widgets', function () {
    expect(is_subclass_of(StatsOverviewWidget::class, Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
    expect(is_subclass_of(TableWidget::class, Filament\Widgets\TableWidget::class))->toBeTrue();
});
