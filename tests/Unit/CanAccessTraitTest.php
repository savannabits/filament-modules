<?php

use Coolsam\Modules\ChartWidget;
use Modules\Blog\Filament\Widgets\TestChartWidget;

beforeEach(function () {
    if (! class_exists('Modules\Blog\Filament\Widgets\TestChartWidget', false)) {
        eval(<<<'PHP'
            namespace Modules\Blog\Filament\Widgets;

            class TestChartWidget extends \Coolsam\Modules\ChartWidget
            {
                protected function getType(): string
                {
                    return 'line';
                }
            }
            PHP);
    }
});

test('can access trait allows widget access when module is enabled', function () {
    $this->createTestModule('Blog', enabled: true);

    expect(TestChartWidget::canAccess())->toBeTrue();
    expect(TestChartWidget::canView())->toBeTrue();
});

test('can access trait denies widget access when module is disabled', function () {
    $this->createTestModule('Blog', enabled: false);

    expect(TestChartWidget::canAccess())->toBeFalse();
    expect(TestChartWidget::canView())->toBeFalse();
});

test('can access trait does not call parent can access when parent lacks the method', function () {
    $this->createTestModule('Blog', enabled: true);

    expect(method_exists(ChartWidget::class, 'canAccess'))->toBeTrue();
    expect(method_exists(Filament\Widgets\ChartWidget::class, 'canAccess'))->toBeFalse();
});

test('can access trait resolves the module name from the class namespace', function () {
    expect(TestChartWidget::getCurrentModuleName())->toBe('blog');
});
