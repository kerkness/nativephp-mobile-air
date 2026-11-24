<?php

use Native\Mobile\Edge\Layouts\Builders\TabBarOptions;
use Native\Mobile\Testing\Native;
use Tests\Fixtures\Edge\ChromeScreen;
use Tests\Fixtures\Edge\ChromeTabsLayout;

/*
 * iOS hides the tab bar on a pushed level. A screen that wants it kept (a
 * list moved around in) says so with tabBarOptions()->hidden(false), folded
 * onto the native root as `show_tab_bar`.
 */

beforeEach(function () {
    app('view')->addLocation(__DIR__.'/../../Fixtures/views');
});

class KeepsTabBarScreen extends ChromeScreen
{
    public function tabBarOptions(): ?TabBarOptions
    {
        return TabBarOptions::make()->hidden(false);
    }
}

class HidesTabBarScreen extends ChromeScreen
{
    protected bool $hidesTabBar = true;
}

it('asks to keep the tab bar for an explicit hidden(false)', function () {
    $props = Native::test(KeepsTabBarScreen::class, layout: ChromeTabsLayout::class)->tree()['props'];

    expect($props['show_tab_bar'])->toBeTrue()
        ->and($props)->not->toHaveKey('hide_tab_bar');
});

it('sends neither by default, and only the hide for a screen that hides it', function () {
    expect(Native::test(ChromeScreen::class, layout: ChromeTabsLayout::class)->tree()['props'])
        ->not->toHaveKey('show_tab_bar')
        ->not->toHaveKey('hide_tab_bar');

    expect(Native::test(HidesTabBarScreen::class, layout: ChromeTabsLayout::class)->tree()['props'])
        ->not->toHaveKey('show_tab_bar')
        ->hide_tab_bar->toBeTrue();
});
