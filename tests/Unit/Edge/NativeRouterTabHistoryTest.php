<?php

use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\NativeRouter;
use Native\Mobile\Edge\NavigationIntent;

/*
 * Native tab chrome keeps a history per tab and reconciles each publish by
 * the stack's depth relative to the tab's root. So the router keeps one
 * stack per tab too: moving to another tab parks the shown tab's stack and
 * brings the other's back, as it was left. The loop needs the device
 * bridge, so these drive the seam it calls (switchTabs) directly.
 */

class TabHistoryScreen extends NativeComponent
{
    public bool $unmounted = false;

    public function unmount(): void
    {
        $this->unmounted = true;
    }
}

class TabHistoryLayout extends NativeLayout
{
    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            ->add(Tab::link('Home', '/home'))
            ->add(Tab::link('Shop', '/shop'))
            ->add(Tab::search('Search'));
    }
}

class TabHistoryRouter extends NativeRouter
{
    /** @param  list<string>  $uris */
    public function seed(array $uris): void
    {
        $this->stack = array_map(fn (string $uri) => [
            'component' => $this->createComponent(TabHistoryScreen::class),
            'uri' => $uri,
            'params' => [],
        ], $uris);
    }

    public function move(string $type, string $uri, array $data = []): ?string
    {
        $from = $this->stack[count($this->stack) - 1]['component'] ?? new TabHistoryScreen;

        return $this->switchTabs(new NavigationIntent($type, $uri, $data), $from);
    }

    /** @return list<string> */
    public function uris(): array
    {
        return array_column($this->stack, 'uri');
    }

    /** @return array<string, list<string>> */
    public function parked(): array
    {
        return array_map(fn (array $stack) => array_column($stack, 'uri'), $this->parkedStacks);
    }

    public function top(): NativeComponent
    {
        return $this->stack[count($this->stack) - 1]['component'];
    }
}

beforeEach(function () {
    NativeRouter::clearRoutes();
    NativeRouter::register('/home', TabHistoryScreen::class, TabHistoryLayout::class);
    NativeRouter::register('/home/search', TabHistoryScreen::class, TabHistoryLayout::class);
    NativeRouter::register('/shop', TabHistoryScreen::class, TabHistoryLayout::class);
    NativeRouter::register('/shop/items/{id}', TabHistoryScreen::class, TabHistoryLayout::class);
    NativeRouter::register('/sign-in', TabHistoryScreen::class);
});

afterEach(fn () => NativeRouter::clearRoutes());

it('parks a tab’s history when another tab is tapped, and brings it back as it was left', function () {
    $router = new TabHistoryRouter;
    $router->seed(['/shop', '/shop/items/1', '/shop/items/2']);
    $item = $router->top();

    // Tapping Home, a tab not visited yet, starts it at its root.
    expect($router->move(NavigationIntent::REPLACE, '/home'))->toBe('push')
        ->and($router->uris())->toBe([])
        ->and($router->parked())->toBe(['/shop' => ['/shop', '/shop/items/1', '/shop/items/2']])
        ->and($router->stackDepth())->toBe(0);

    // The caller pushes Home's root; then Shop is tapped again.
    $router->seed(['/home']);

    expect($router->move(NavigationIntent::REPLACE, '/shop'))->toBe('resume')
        ->and($router->uris())->toBe(['/shop', '/shop/items/1', '/shop/items/2'])
        ->and($router->top())->toBe($item)
        ->and($item->unmounted)->toBeFalse()
        ->and($router->parked())->toBe(['/home' => ['/home']]);
});

it('keeps the depth the shown tab’s own, whatever happened in the others', function () {
    $router = new TabHistoryRouter;
    $router->seed(['/home', '/home/search']);
    $router->move(NavigationIntent::REPLACE, '/shop');
    $router->seed(['/shop']);

    expect($router->stackDepth())->toBe(1)
        ->and($router->isRootScreen())->toBeTrue();
});

it('pushes a screen of another tab onto that tab’s history, its root beneath', function () {
    $router = new TabHistoryRouter;
    $router->seed(['/shop', '/shop/items/1']);

    // A search result from the Shop tab opens in Home, which hasn't been visited.
    expect($router->move(NavigationIntent::NAVIGATE, '/home/search?q=lamp'))->toBe('push')
        ->and($router->uris())->toBe(['/home'])
        ->and($router->parked())->toBe(['/shop' => ['/shop', '/shop/items/1']]);
});

it('pushes onto the other tab’s history as it was left', function () {
    $router = new TabHistoryRouter;
    $router->seed(['/home', '/home/search']);
    $router->move(NavigationIntent::REPLACE, '/shop');
    $router->seed(['/shop', '/shop/items/1']);

    expect($router->move(NavigationIntent::NAVIGATE, '/home/search?q=chair'))->toBe('push')
        ->and($router->uris())->toBe(['/home', '/home/search']);
});

it('starts a tab afresh for a replace that carries data', function () {
    $router = new TabHistoryRouter;
    $router->seed(['/shop', '/shop/items/1']);
    $parkedItem = $router->top();
    $router->move(NavigationIntent::REPLACE, '/home');
    $router->seed(['/home', '/home/search']);

    expect($router->move(NavigationIntent::REPLACE, '/shop', ['open' => 2]))->toBe('push')
        ->and($router->uris())->toBe([])
        ->and($parkedItem->unmounted)->toBeTrue();
});

it('navigates as before within a tab, and outside tabbed layouts', function () {
    $router = new TabHistoryRouter;
    $router->seed(['/shop']);

    expect($router->move(NavigationIntent::NAVIGATE, '/shop/items/3'))->toBeNull()
        ->and($router->uris())->toBe(['/shop']);

    $router->seed(['/sign-in']);

    expect($router->move(NavigationIntent::REPLACE, '/home'))->toBeNull();
});

it('lets the parked tabs go when the app replaces its way out of the tabs', function () {
    $router = new TabHistoryRouter;
    $router->seed(['/shop', '/shop/items/1']);
    $parkedItem = $router->top();
    $router->move(NavigationIntent::REPLACE, '/home');
    $router->seed(['/home']);

    expect($router->move(NavigationIntent::REPLACE, '/sign-in'))->toBeNull()
        ->and($router->parked())->toBe([])
        ->and($parkedItem->unmounted)->toBeTrue();
});
