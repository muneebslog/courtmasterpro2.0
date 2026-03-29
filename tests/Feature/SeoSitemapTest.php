<?php

use Illuminate\Foundation\Testing\TestCase;

test('robots.txt points to sitemap.xml', function (): void {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Sitemap: /sitemap.xml');
});

test('sitemap.xml lists public score pages', function (): void {
    /** @var TestCase $this */
    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertSee('<urlset', false)
        ->assertSee(url(route('home')), false)
        ->assertSee(url(route('live.all')), false);

    foreach (range(1, 4) as $court) {
        $response->assertSee(url(route('live.court', ['court' => $court])), false);
    }
});

test('home page includes SEO meta tags', function (): void {
    /** @var TestCase $this */
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('name="description"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('rel="canonical"', false);
});

test('live all page includes Open Graph meta tags', function (): void {
    /** @var TestCase $this */
    $response = $this->get(route('live.all'));

    $response->assertOk()
        ->assertSee('property="og:title"', false)
        ->assertSee('rel="canonical"', false);
});

test('live court page includes SEO meta tags', function (): void {
    /** @var TestCase $this */
    $response = $this->get(route('live.court', ['court' => 1]));

    $response->assertOk()
        ->assertSee('name="description"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('rel="canonical"', false);
});
