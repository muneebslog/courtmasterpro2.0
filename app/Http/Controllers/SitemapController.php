<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $lastMod = now()->toAtomString();

        $paths = [
            ['path' => route('home'), 'changefreq' => 'daily', 'priority' => 1.0],
            ['path' => route('live.all'), 'changefreq' => 'hourly', 'priority' => 0.6],
        ];

        foreach (range(1, 5) as $court) {
            $paths[] = [
                'path' => route('live.court', ['court' => $court]),
                'changefreq' => 'hourly',
                'priority' => 0.5,
            ];
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $urlset = $doc->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $doc->appendChild($urlset);

        foreach ($paths as $item) {
            $urlEl = $doc->createElement('url');

            $urlEl->appendChild($doc->createElement('loc', url($item['path'])));
            $urlEl->appendChild($doc->createElement('lastmod', $lastMod));
            $urlEl->appendChild($doc->createElement('changefreq', $item['changefreq']));
            $urlEl->appendChild($doc->createElement('priority', (string) $item['priority']));

            $urlset->appendChild($urlEl);
        }

        $xml = $doc->saveXML();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
