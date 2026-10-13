<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Admin;

use App\Core\Request;
use App\Http\Controllers\Admin\FundraiserController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionMethod;

final class FundraiserControllerRouteBindingTest extends TestCase
{
    public static function moderationActions(): iterable
    {
        foreach ([
            'approve',
            'reject',
            'requestChanges',
            'pause',
            'publish',
            'unpublish',
            'archive',
            'destroy',
            'feature',
        ] as $action) {
            yield $action => [$action];
        }
    }

    #[DataProvider('moderationActions')]
    public function testModerationActionsReadRouteParametersFromRequest(string $action): void
    {
        $method = new ReflectionMethod(FundraiserController::class, $action);
        $parameters = $method->getParameters();

        self::assertCount(1, $parameters, 'Router invokes controller actions with the Request only.');
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame(Request::class, $parameters[0]->getType()->getName());
    }
}
