@extends('layouts.site')

@section('title', 'SSK Laba Dami | Laundry care in Matina')
@section('description', 'Wash, dry and fold at SSK Laba Dami, a neighborhood laundry hub in Matina, Davao City. Open since 2019.')

@section('content')
    <main>
        <section class="home-hero page-width" aria-labelledby="hero-title">
            <div class="hero-copy">
                <p class="hero-location">Matina, Davao City · Open since 2019</p>
                <h1 id="hero-title">Laundry day,<br>handled <em>locally.</em></h1>
                <p class="hero-intro">Bring in a load for washing, drying, or both. Pick up everyday laundry supplies while you are here.</p>
                <div class="hero-links">
                    <a class="button button-dark" href="{{ route('offerings') }}">See services &amp; supplies</a>
                    <a class="text-link" href="{{ route('company') }}">Meet the shop</a>
                </div>
                <p class="hero-address">Door 5, Amicitia Complex<br>Amolo Street–Tahimik Avenue, Matina Crossing</p>
            </div>
            <figure class="hero-shop-photo">
                <img src="{{ asset('images/ssk-laba-dami-shop.jpg') }}" alt="The SSK Laba Dami storefront in Matina, with its washing machines visible inside">
                <figcaption><span>SSK LABA DAMI</span><span>DOOR 5 · MATINA</span></figcaption>
            </figure>
        </section>

        <section class="home-intro page-width ruled-section" aria-labelledby="intro-title">
            <div class="intro-copy">
                <h2 id="intro-title">Laundry care and supplies, in one stop.</h2>
                <p>Owner Abner Trinidad opened SSK Laba Dami in 2019. Today, he and Jocelyn Tingson take care of garment washing, drying and folding, with laundry supplies available to buy in-store.</p>
                <a class="text-link" href="{{ route('company') }}">Read our story</a>
            </div>
            <p class="intro-note">A straightforward place to drop off a load and collect the things you need for the next one.</p>
        </section>

        <section class="home-services" aria-labelledby="services-title">
            <div class="page-width">
                <div class="section-heading">
                    <h2 id="services-title">Bring the load. Choose the care.</h2>
                    <p>Three clear service options, all available at our Matina shop.</p>
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
                <h2 id="story-title">The people behind the counter.</h2>
                <p>SSK Laba Dami began with two practical needs in mind: care for customer garments and supplies for the next wash. Learn how the shop came together in Matina.</p>
                <a class="text-link" href="{{ route('journal.story') }}">Read the company story</a>
            </div>
            <a class="story-index-link" href="{{ route('journal') }}">Journal</a>
        </section>

        <section class="home-visit" aria-labelledby="visit-title">
            <div class="page-width visit-inner">
                <h2 id="visit-title">Your next load<br>has a place at <em>Door 5.</em></h2>
                <address>Amicitia Complex, Amolo Street–Tahimik Avenue<br>Nabua Subdivision, Matina Crossing, Davao City 8000</address>
                <a class="button button-paper" href="{{ route('company') }}#visit">Plan your visit</a>
            </div>
        </section>
    </main>
@endsection
