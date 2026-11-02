@extends('layouts.site')

@section('title', 'A laundry hub, opened in Matina | SSK Laba Dami')
@section('description', 'The story of SSK Laba Dami, opened by Abner Trinidad in 2019 at Amicitia Complex, Matina Crossing.')

@section('content')
    <main class="editorial-page article-page">
        <header class="article-header page-width">
            <a class="back-link" href="{{ route('journal') }}">Back to journal</a>
            <p class="eyebrow">Company story <span>·</span> Matina, Davao City</p>
            <h1>A laundry hub,<br><em>opened in Matina.</em></h1>
            <p class="article-deck">The people, place and everyday work behind SSK Laba Dami.</p>
            <div class="article-byline">
                <span>By <strong>SSK Laba Dami</strong></span>
                <span>Established <time datetime="2019">2019</time></span>
                <span>2 min read</span>
            </div>
        </header>

        <div class="article-layout page-width">
            <figure class="article-rail">
                <img src="{{ asset('images/ssk-laba-dami-shop.jpg') }}" alt="SSK Laba Dami storefront and washing machines">
                <figcaption>
                    <span>THE SHOP</span>
                    <strong>2019</strong>
                    <span>MATINA<br>DAVAO CITY</span>
                </figcaption>
            </figure>
            <article class="article-body">
                <p class="article-lead">SSK Laba Dami opened in 2019 at Door 5, Amicitia Complex, in Matina Crossing, Davao City. Owner Abner Trinidad established the neighborhood laundry hub, where garment care and related laundry supplies are part of the same shop.</p>

                <h2>Care for the load</h2>
                <p>The shop’s laundry work includes washing, drying and folding customer garments. Customers can choose Wash Only, Dry Only or Wash with Dry, depending on what their load needs.</p>

                <aside class="article-callout" aria-label="The shop in two lines">
                    <span>THE SHOP IN TWO LINES</span>
                    <p>Garment care and the supplies that go with laundry, at the same neighborhood stop.</p>
                </aside>

                <h2>Meet the people at the shop</h2>
                <p>Abner Trinidad owns the business. Jocelyn Tingson works alongside him at SSK Laba Dami. The shop also sells laundry supplies directly to customers, including Ariel, Downy and Zonrox.</p>

                <h2>Find us in Matina</h2>
                <p>SSK Laba Dami is at Door 5, Amicitia Complex, Amolo Street–Tahimik Avenue, Nabua Subdivision, Matina Crossing, Davao City, Philippines, 8000.</p>
                <p>For the current service options and in-store supplies, <a href="{{ route('offerings') }}">see the products and services</a>.</p>
            </article>
        </div>

        <section class="related-reading page-width" aria-labelledby="related-title">
            <p class="section-index">Keep exploring</p>
            <h2 id="related-title">From the shop</h2>
            <div class="related-links">
                <a href="{{ route('company') }}"><span>Company profile</span><strong>Meet the people behind the shop</strong></a>
                <a href="{{ route('offerings') }}"><span>Products &amp; services</span><strong>See what’s available at the counter</strong></a>
            </div>
        </section>
    </main>
@endsection
