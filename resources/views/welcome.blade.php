@extends('layouts.site')

@section('title', 'SSK Laba Dami | Laundry care in Matina')
@section('description', 'Wash, dry and fold at SSK Laba Dami, a neighborhood laundry hub in Matina, Davao City. Open since 2019.')

@section('content')
    <main>
        <section class="home-hero page-width" aria-labelledby="hero-title">
            <div class="hero-copy">
                <p class="eyebrow">Matina, Davao City <span>·</span> Open since 2019</p>
                <h1 id="hero-title">Laundry day,<br><em>right here</em> in Matina.</h1>
                <p class="hero-intro">SSK Laba Dami washes, dries and folds your garments—and keeps everyday laundry supplies close at hand.</p>
                <div class="hero-links">
                    <a class="button button-dark" href="{{ route('offerings') }}">See services &amp; supplies</a>
                    <a class="text-link" href="{{ route('company') }}">Meet the shop</a>
                </div>
                <p class="hero-address">Door 5, Amicitia Complex<br>Amolo Street–Tahimik Avenue, Matina Crossing</p>
            </div>
            <aside class="washer-ticket" aria-label="SSK Laba Dami washing machine illustration">
                <div class="washer-controls" aria-hidden="true">
                    <span class="washer-brand">SSK LABA DAMI</span>
                    <span class="washer-display">MATINA · DAVAO</span>
                    <span class="washer-dial"></span>
                </div>
                <div class="washer-door">
                    <img class="ticket-mark" src="{{ asset('images/ssk-laba-dami-logo.jpg') }}" alt="" width="180" height="180">
                </div>
                <p class="ticket-label">A neighborhood laundry hub</p>
                <p class="ticket-services">WASH <i>/</i> DRY <i>/</i> FOLD</p>
                <div class="washer-status"><span>EST.</span><strong>2019</strong><span>ISSUED IN MATINA</span></div>
            </aside>
        </section>

        <section class="home-intro page-width ruled-section" aria-labelledby="intro-title">
            <p class="section-index">01 <span>·</span> The shop</p>
            <div class="intro-copy">
                <h2 id="intro-title">A local laundry, with the essentials on the same stop.</h2>
                <p>Owner Abner Trinidad opened SSK Laba Dami in 2019. Today, he and Jocelyn Tingson take care of garment washing, drying and folding, with laundry supplies available to buy in-store.</p>
                <a class="text-link" href="{{ route('company') }}">Read our story</a>
            </div>
            <p class="intro-note">A straightforward place to drop off a load and pick up the things you use to care for it.</p>
        </section>

        <section class="home-services" aria-labelledby="services-title">
            <div class="page-width">
                <div class="section-heading">
                    <p class="section-index">02 <span>·</span> Laundry services</p>
                    <h2 id="services-title">Choose what your load needs.</h2>
                    <p>Three ways to get laundry through the day. Bring your garments to our Matina shop.</p>
                </div>
                <ol class="service-ledger">
                    <li><span class="ledger-no">01</span><h3>Wash Only</h3><p>For a load that needs a wash before you take it home to dry.</p><span class="ledger-tag">WASH</span></li>
                    <li><span class="ledger-no">02</span><h3>Dry Only</h3><p>For garments that are already washed and need drying.</p><span class="ledger-tag">DRY</span></li>
                    <li><span class="ledger-no">03</span><h3>Wash with Dry</h3><p>For a wash and dry in one visit to the shop.</p><span class="ledger-tag">WASH + DRY</span></li>
                </ol>
                <a class="text-link section-link" href="{{ route('offerings') }}">See all services and supplies</a>
            </div>
        </section>

        <section class="home-story page-width" aria-labelledby="story-title">
            <figure class="story-photo">
                <img src="{{ asset('images/ssk-laba-dami-shop.jpg') }}" alt="SSK Laba Dami storefront and washing machines">
                <figcaption><span>THE SHOP</span><span>MATINA · EST. 2019</span></figcaption>
            </figure>
            <div class="story-copy">
                <p class="section-index">03 <span>·</span> From the journal</p>
                <h2 id="story-title">A laundry hub, opened in Matina.</h2>
                <p>SSK Laba Dami began with two closely linked parts: caring for customer garments and keeping laundry supplies on the shelf. Here’s the story of the shop and the people behind it.</p>
                <a class="text-link" href="{{ route('journal.story') }}">Read the company story</a>
            </div>
            <a class="story-index-link" href="{{ route('journal') }}">Journal</a>
        </section>

        <section class="home-visit" aria-labelledby="visit-title">
            <div class="page-width visit-inner">
                <p class="section-index">04 <span>·</span> Find the shop</p>
                <h2 id="visit-title">Bring your next load<br>to <em>Door 5.</em></h2>
                <address>Amicitia Complex, Amolo Street–Tahimik Avenue<br>Nabua Subdivision, Matina Crossing, Davao City 8000</address>
                <a class="button button-paper" href="{{ route('company') }}#visit">Plan your visit</a>
            </div>
        </section>
    </main>
@endsection
