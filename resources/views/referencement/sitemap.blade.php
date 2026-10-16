{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('fil.index') }}</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>
    <url><loc>{{ route('fil.emplois') }}</loc><changefreq>hourly</changefreq><priority>0.9</priority></url>
    <url><loc>{{ route('fil.services') }}</loc><changefreq>hourly</changefreq><priority>0.9</priority></url>
    <url><loc>{{ route('fil.formations') }}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
    @foreach ($publications as $publication)
        <url>
            <loc>{{ route('publications.show', $publication['slug']) }}</loc>
            @if ($publication['modifie_le'])<lastmod>{{ $publication['modifie_le'] }}</lastmod>@endif
        </url>
    @endforeach
</urlset>
